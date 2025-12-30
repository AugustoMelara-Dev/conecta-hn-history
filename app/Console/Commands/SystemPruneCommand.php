<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Support\Facades\Log;

/**
 * SystemPruneCommand - Centralized Ephemeral Data Garbage Collector
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  OPERATIONAL RISK ANALYSIS: Crontab Complexity Reduction                 ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║  PROBLEM: Micro-commands for each cleanup task create:                   ║
 * ║    - Crontab sprawl (10+ separate scheduled entries)                     ║
 * ║    - Inconsistent cleanup policies                                       ║
 * ║    - Hard to audit what gets deleted when                                ║
 * ║                                                                          ║
 * ║  SOLUTION: Single entry point that orchestrates all ephemeral cleanup    ║
 * ║    - One cron entry: Schedule::command('system:prune')->daily();        ║
 * ║    - Each model defines its own retention policy via MassPrunable        ║
 * ║    - Centralized logging for audit trail                                 ║
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
                            {--model= : Prune specific model only}';

    protected $description = 'Prune ephemeral data (OTPs, tokens, logs) according to retention policies';

    /**
     * Ephemeral models to prune with their retention policies.
     *
     * Format: 'ModelClass' => 'Human-readable description'
     *
     * Each model should implement MassPrunable trait with prunable() method
     * defining the retention policy query.
     */
    protected array $ephemeralModels = [
        \App\Models\Otp::class => 'One-Time Passwords (15 min TTL)',
        \App\Models\PasswordResetToken::class => 'Password Reset Tokens (1 hour TTL)',
        \Laravel\Telescope\Storage\EntryModel::class => 'Telescope Debug Entries (48 hours)',
        \Spatie\Activitylog\Models\Activity::class => 'Activity Logs (30 days)',
        \App\Models\FailedJob::class => 'Failed Job Records (7 days)',
        \Laravel\Sanctum\PersonalAccessToken::class => 'Expired API Tokens (expired_at)',
    ];

    protected int $totalPruned = 0;
    protected bool $dryRun = false;

    public function handle(): int
    {
        $this->dryRun = (bool) $this->option('dry-run');
        $specificModel = $this->option('model');

        $this->components->info('System Prune - Ephemeral Data Garbage Collector');
        $this->components->bulletList([
            "Mode: " . ($this->dryRun ? 'DRY RUN' : 'LIVE'),
            "Scope: " . ($specificModel ?: 'All ephemeral models'),
        ]);

        foreach ($this->ephemeralModels as $modelClass => $description) {
            // Filter by specific model if requested
            if ($specificModel) {
                $shortName = class_basename($modelClass);
                if (strtolower($shortName) !== strtolower($specificModel)) {
                    continue;
                }
            }

            $this->pruneModel($modelClass, $description);
        }

        // Also prune Laravel's built-in prunable items
        $this->pruneBuiltInTables();

        // ═══════════════════════════════════════════════════════════════════════
        // SUMMARY
        // ═══════════════════════════════════════════════════════════════════════
        $this->newLine();
        $this->components->twoColumnDetail('Total records pruned', (string) $this->totalPruned);

        Log::info('SystemPruneCommand completed', [
            'total_pruned' => $this->totalPruned,
            'dry_run' => $this->dryRun,
        ]);

        return Command::SUCCESS;
    }

    /**
     * Prune a single model type.
     */
    protected function pruneModel(string $modelClass, string $description): void
    {
        // Check if model exists
        if (!class_exists($modelClass)) {
            $this->components->twoColumnDetail(
                $description,
                '<fg=yellow>Model not found - skipping</>'
            );
            return;
        }

        try {
            $model = new $modelClass;
            
            // Check if model uses MassPrunable trait
            if (!$this->usesMassPrunable($model)) {
                // Fallback to manual pruning for models without MassPrunable
                $pruned = $this->manualPrune($modelClass);
            } else {
                // Use Laravel's built-in pruning
                if ($this->dryRun) {
                    $pruned = $model->prunable()->count();
                } else {
                    $pruned = $model->pruneAll();
                }
            }

            $this->totalPruned += $pruned;

            $this->components->twoColumnDetail(
                $description,
                $pruned > 0 
                    ? "<fg=green>{$pruned} records</>" 
                    : '<fg=gray>0 records</>'
            );

        } catch (\Exception $e) {
            $this->components->twoColumnDetail(
                $description,
                "<fg=red>Error: {$e->getMessage()}</>"
            );
            
            Log::error('SystemPrune: Failed to prune model', [
                'model' => $modelClass,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Check if a model uses the MassPrunable trait.
     */
    protected function usesMassPrunable($model): bool
    {
        return in_array(MassPrunable::class, class_uses_recursive($model));
    }

    /**
     * Manual pruning for models without MassPrunable trait.
     */
    protected function manualPrune(string $modelClass): int
    {
        $shortName = class_basename($modelClass);

        // Define custom retention policies for each model
        $query = match ($shortName) {
            'Otp' => $modelClass::where('expires_at', '<', now())
                        ->orWhere('created_at', '<', now()->subMinutes(15)),
            
            'PasswordResetToken' => $modelClass::where('created_at', '<', now()->subHour()),
            
            'EntryModel' => $modelClass::where('created_at', '<', now()->subHours(48)),
            
            'Activity' => $modelClass::where('created_at', '<', now()->subDays(30)),
            
            'FailedJob' => $modelClass::where('failed_at', '<', now()->subDays(7)),
            
            'PersonalAccessToken' => $modelClass::whereNotNull('expires_at')
                                        ->where('expires_at', '<', now()),
            
            default => null,
        };

        if (!$query) {
            return 0;
        }

        if ($this->dryRun) {
            return $query->count();
        }

        return $query->delete();
    }

    /**
     * Prune Laravel's built-in tables (sessions, cache, etc.)
     */
    protected function pruneBuiltInTables(): void
    {
        $this->components->task('Pruning built-in Laravel tables', function () {
            $pruned = 0;

            // Prune expired cache entries (if using database driver)
            if (config('cache.default') === 'database') {
                try {
                    $deleted = \DB::table('cache')
                        ->where('expiration', '<', now()->timestamp)
                        ->delete();
                    $pruned += $deleted;
                } catch (\Exception $e) {
                    // Cache table may not exist
                }
            }

            // Prune expired sessions (if using database driver)
            if (config('session.driver') === 'database') {
                try {
                    $lifetime = config('session.lifetime', 120);
                    $deleted = \DB::table('sessions')
                        ->where('last_activity', '<', now()->subMinutes($lifetime)->timestamp)
                        ->delete();
                    $pruned += $deleted;
                } catch (\Exception $e) {
                    // Sessions table may not exist
                }
            }

            $this->totalPruned += $pruned;

            return true;
        });
    }
}
