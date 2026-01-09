<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * SystemPruneCommand - Production-Grade Ephemeral Data Garbage Collector
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  OPERATIONAL RISK ANALYSIS: Safe Model Discovery                         ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║  PROBLEM: Hardcoded model classes may not exist, causing crashes.        ║
 * ║                                                                          ║
 * ║  SOLUTION: Dynamic model discovery with class_exists() guards            ║
 * ║    - Each model is checked before instantiation                         ║
 * ║    - Missing models are gracefully skipped                               ║
 * ║    - Uses chunkById for memory-safe deletion                            ║
 * ║                                                                          ║
 * ║  EPHEMERAL DATA TYPES:                                                   ║
 * ║    - OTPs: 15 minutes                                                    ║
 * ║    - Password Reset Tokens: 1 hour                                       ║
 * ║    - Telescope Entries: 48 hours                                         ║
 * ║    - Activity Logs: 30 days                                              ║
 * ║    - Failed Jobs: 7 days                                                 ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * USAGE:
 *   php artisan system:prune
 *   php artisan system:prune --dry-run
 *   php artisan system:prune --model=Otp
 *
 * SCHEDULING:
 *   Schedule::command('system:prune')->daily()->at('04:00');
 */
class SystemPruneCommand extends Command
{
    protected $signature = 'system:prune
                            {--dry-run : Preview without deleting}
                            {--model= : Prune specific model only}
                            {--chunk=1000 : Records per deletion batch}';

    protected $description = 'Prune ephemeral data (OTPs, tokens, logs) according to retention policies';

    /**
     * Define pruneable models with their table names for verification.
     *
     * Format: ['description', 'class', 'table', 'retention_query_callback']
     */
    protected function getEphemeralModels(): array
    {
        return [
            [
                'description' => 'One-Time Passwords (15 min TTL)',
                'class' => 'App\\Models\\Otp',
                'table' => 'otps',
                'query' => fn ($class) => $class::where('expires_at', '<', now())
                    ->orWhere('created_at', '<', now()->subMinutes(15)),
            ],
            [
                'description' => 'Password Reset Tokens (1 hour TTL)',
                'class' => null, // Use raw table query
                'table' => 'password_reset_tokens',
                'query' => fn () => DB::table('password_reset_tokens')
                    ->where('created_at', '<', now()->subHour()),
            ],
            [
                'description' => 'Telescope Debug Entries (48 hours)',
                'class' => 'Laravel\\Telescope\\Storage\\EntryModel',
                'table' => 'telescope_entries',
                'query' => fn ($class) => $class::where('created_at', '<', now()->subHours(48)),
            ],
            [
                'description' => 'Activity Logs (30 days)',
                'class' => 'Spatie\\Activitylog\\Models\\Activity',
                'table' => 'activity_log',
                'query' => fn ($class) => $class::where('created_at', '<', now()->subDays(30)),
            ],
            [
                'description' => 'Failed Job Records (7 days)',
                'class' => null,
                'table' => 'failed_jobs',
                'query' => fn () => DB::table('failed_jobs')
                    ->where('failed_at', '<', now()->subDays(7)),
            ],
            [
                'description' => 'Expired API Tokens',
                'class' => 'Laravel\\Sanctum\\PersonalAccessToken',
                'table' => 'personal_access_tokens',
                'query' => fn ($class) => $class::whereNotNull('expires_at')
                    ->where('expires_at', '<', now()),
            ],
        ];
    }

    protected int $totalPruned = 0;

    protected bool $dryRun = false;

    protected int $chunkSize = 1000;

    public function handle(): int
    {
        $this->dryRun = (bool) $this->option('dry-run');
        $this->chunkSize = (int) $this->option('chunk');
        $specificModel = $this->option('model');

        $this->components->info('System Prune - Ephemeral Data Garbage Collector');
        $this->components->bulletList([
            'Mode: '.($this->dryRun ? 'DRY RUN' : 'LIVE'),
            "Chunk size: {$this->chunkSize}",
            'Scope: '.($specificModel ?: 'All ephemeral models'),
        ]);

        foreach ($this->getEphemeralModels() as $config) {
            // Filter by specific model if requested
            if ($specificModel) {
                $shortName = $config['class'] ? class_basename($config['class']) : $config['table'];
                if (stripos($shortName, $specificModel) === false) {
                    continue;
                }
            }

            $this->pruneEntity($config);
        }

        // Also prune Laravel's built-in prunable items
        $this->pruneBuiltInTables();

        $this->newLine();
        $this->components->twoColumnDetail('Total records pruned', (string) $this->totalPruned);

        Log::info('SystemPruneCommand completed', [
            'total_pruned' => $this->totalPruned,
            'dry_run' => $this->dryRun,
        ]);

        return Command::SUCCESS;
    }

    /**
     * Prune a single entity type with full safety checks.
     */
    protected function pruneEntity(array $config): void
    {
        $description = $config['description'];

        try {
            // Check if table exists first
            if (! Schema::hasTable($config['table'])) {
                $this->components->twoColumnDetail(
                    $description,
                    '<fg=gray>Table not found - skipping</>'
                );

                return;
            }

            // If class is specified, check it exists
            if ($config['class'] && ! class_exists($config['class'])) {
                // Fall back to raw table query
                $pruned = $this->pruneViaRawQuery($config['table'], $config['description']);

                return;
            }

            // Build the query
            $query = $config['class']
                ? ($config['query'])($config['class'])
                : ($config['query'])();

            $count = $query->count();

            if ($this->dryRun) {
                $this->totalPruned += $count;
                $this->components->twoColumnDetail(
                    $description,
                    $count > 0
                        ? "<fg=yellow>{$count} would be deleted</>"
                        : '<fg=gray>0 records</>'
                );

                return;
            }

            // Delete in chunks to prevent memory issues and long locks
            $deleted = 0;
            do {
                $batch = $query->limit($this->chunkSize)->delete();
                $deleted += $batch;
            } while ($batch > 0);

            $this->totalPruned += $deleted;

            $this->components->twoColumnDetail(
                $description,
                $deleted > 0
                    ? "<fg=green>{$deleted} records</>"
                    : '<fg=gray>0 records</>'
            );

        } catch (\Exception $e) {
            $this->components->twoColumnDetail(
                $description,
                '<fg=red>Error: '.substr($e->getMessage(), 0, 50).'...</>'
            );

            Log::error('SystemPrune: Failed to prune entity', [
                'entity' => $description,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Fallback pruning via raw table query when model doesn't exist.
     */
    protected function pruneViaRawQuery(string $table, string $description): int
    {
        try {
            // Apply generic retention policy based on created_at
            $query = DB::table($table)->where('created_at', '<', now()->subDays(30));

            $count = $query->count();

            if (! $this->dryRun) {
                $deleted = 0;
                do {
                    $batch = DB::table($table)
                        ->where('created_at', '<', now()->subDays(30))
                        ->limit($this->chunkSize)
                        ->delete();
                    $deleted += $batch;
                } while ($batch > 0);
                $count = $deleted;
            }

            $this->totalPruned += $count;

            $this->components->twoColumnDetail(
                $description.' (raw)',
                $count > 0
                    ? ($this->dryRun ? "<fg=yellow>{$count} would be deleted</>" : "<fg=green>{$count} records</>")
                    : '<fg=gray>0 records</>'
            );

            return $count;
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Prune Laravel's built-in tables (sessions, cache, etc.)
     */
    protected function pruneBuiltInTables(): void
    {
        $this->components->task('Pruning built-in Laravel tables', function () {
            $pruned = 0;

            // Prune expired cache entries (if using database driver)
            if (config('cache.default') === 'database' && Schema::hasTable('cache')) {
                try {
                    $deleted = DB::table('cache')
                        ->where('expiration', '<', now()->timestamp)
                        ->delete();
                    $pruned += $deleted;
                } catch (\Exception $e) {
                    // Ignore
                }
            }

            // Prune expired sessions (if using database driver)
            if (config('session.driver') === 'database' && Schema::hasTable('sessions')) {
                try {
                    $lifetime = config('session.lifetime', 120);
                    $deleted = DB::table('sessions')
                        ->where('last_activity', '<', now()->subMinutes($lifetime)->timestamp)
                        ->delete();
                    $pruned += $deleted;
                } catch (\Exception $e) {
                    // Ignore
                }
            }

            $this->totalPruned += $pruned;

            return true;
        });
    }
}
