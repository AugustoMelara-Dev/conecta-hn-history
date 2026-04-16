<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

final class AuditMigrationsCommand extends Command
{
    protected $signature = 'db:audit-migrations {--json : Output machine-readable JSON}';

    protected $description = 'Audit migration ordering and detect risky duplicates/overlaps.';

    public function handle(): int
    {
        $migrationPath = database_path('migrations');
        $fileEntries = collect(File::files($migrationPath))
            ->map(fn (\SplFileInfo $file): array => [
                'name' => $file->getFilename(),
                'path' => $file->getPathname(),
            ])
            ->sort()
            ->values();

        $files = $fileEntries->pluck('name');

        $timestamps = $files
            ->groupBy(fn (string $filename): string => substr($filename, 0, 17))
            ->filter(fn (Collection $group): bool => $group->count() > 1)
            ->map(fn (Collection $group): array => $group->values()->all())
            ->values()
            ->all();

        $tableMutations = [];
        foreach ($files as $filename) {
            if (preg_match('/^\\d{4}_\\d{2}_\\d{2}_\\d{6}_(add|create|drop|align|fix|optimize)_(.+?)_(to|table|tables|in|with)/', $filename, $matches) === 1) {
                $tableMutations[$matches[2]][] = $filename;
            }
        }

        $highChurnTargets = collect($tableMutations)
            ->map(fn (array $migrations, string $target): array => [
                'target' => $target,
                'count' => count($migrations),
                'migrations' => $migrations,
            ])
            ->filter(fn (array $entry): bool => $entry['count'] >= 6)
            ->sortByDesc('count')
            ->values()
            ->all();

        $createStatements = [];
        $tableStatements = [];

        foreach ($fileEntries as $entry) {
            $content = (string) File::get($entry['path']);

            if (preg_match_all("/Schema::create\\('([^']+)'/m", $content, $createMatches) >= 1) {
                foreach ($createMatches[1] as $table) {
                    $createStatements[(string) $table][] = [
                        'file' => $entry['name'],
                        'content' => $content,
                    ];
                }
            }

            if (preg_match_all("/Schema::table\\('([^']+)'/m", $content, $tableMatches) >= 1) {
                foreach ($tableMatches[1] as $table) {
                    $tableStatements[] = [
                        'table' => (string) $table,
                        'file' => $entry['name'],
                        'content' => $content,
                    ];
                }
            }
        }

        $configuredGovernedTables = (array) config('schema-governance.governed_tables', []);
        $governedTables = array_values(array_filter(
            array_map(static fn (mixed $table): string => is_string($table) ? trim($table) : '', $configuredGovernedTables),
            static fn (string $table): bool => $table !== ''
        ));

        if ($governedTables === []) {
            $governedTables = [
                'orders',
                'favorites',
                'menu_sections',
                'interactions',
                'tenant_visits',
                'notifications',
                'one_time_passwords',
                'inventory_holds',
            ];
        }

        $createViolations = [];
        foreach ($governedTables as $table) {
            $entries = $createStatements[$table] ?? [];
            if (count($entries) <= 1) {
                continue;
            }

            foreach ($entries as $entry) {
                $content = (string) $entry['content'];
                $hasGuard = Str::contains($content, "Schema::hasTable('{$table}')")
                    || Str::contains($content, "Schema::dropIfExists('{$table}')")
                    || Str::contains($content, "SCHEMA_GOVERNANCE_ALLOW_RECREATE {$table}");

                if (! $hasGuard) {
                    $createViolations[] = [
                        'table' => $table,
                        'file' => $entry['file'],
                        'rule' => 'duplicate_create_without_guard',
                    ];
                }
            }
        }

        $tableViolations = collect($tableStatements)
            ->filter(fn (array $entry): bool => in_array($entry['table'], $governedTables, true))
            ->filter(function (array $entry): bool {
                $table = (string) $entry['table'];
                $content = (string) $entry['content'];

                return ! Str::contains($content, "Schema::hasTable('{$table}')")
                    && ! Str::contains($content, "Schema::hasColumn('{$table}'")
                    && ! Str::contains($content, "Schema::hasColumns('{$table}'")
                    && ! Str::contains($content, "SCHEMA_GOVERNANCE_ALLOW_TABLE_MUTATION {$table}");
            })
            ->map(fn (array $entry): array => [
                'table' => (string) $entry['table'],
                'file' => (string) $entry['file'],
                'rule' => 'table_mutation_without_guard',
            ])
            ->values()
            ->all();

        $violations = array_merge($createViolations, $tableViolations);
        $status = empty($timestamps) && $violations === [] ? 'ok' : 'warning';

        $report = [
            'total_migrations' => $files->count(),
            'duplicate_timestamps' => $timestamps,
            'high_churn_targets' => $highChurnTargets,
            'schema_governance' => [
                'governed_tables' => $governedTables,
                'violations' => $violations,
            ],
            'status' => $status,
        ];

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $violations === [] ? self::SUCCESS : self::FAILURE;
        }

        $this->info('Migration Audit');
        $this->line('Total migrations: '.$report['total_migrations']);

        if (! empty($timestamps)) {
            $this->warn('Duplicate timestamps found:');
            foreach ($timestamps as $group) {
                $this->line(' - '.implode(', ', $group));
            }
        } else {
            $this->info('No duplicate migration timestamps detected.');
        }

        if (! empty($highChurnTargets)) {
            $this->warn('High-churn migration targets (6+ touches):');
            foreach ($highChurnTargets as $target) {
                $this->line(" - {$target['target']} ({$target['count']})");
            }
        }

        if ($violations !== []) {
            $this->newLine();
            $this->error('Schema governance violations found:');
            foreach ($violations as $violation) {
                $this->line(" - [{$violation['rule']}] {$violation['table']} in {$violation['file']}");
            }
        } else {
            $this->info('Schema governance checks passed for governed tables.');
        }

        $this->newLine();
        $this->line('Recommendation: when adding new schema changes, prefer align/cleanup migrations over repeated incremental field additions on the same table.');

        return $violations === [] ? self::SUCCESS : self::FAILURE;
    }
}
