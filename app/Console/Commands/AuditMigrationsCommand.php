<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

final class AuditMigrationsCommand extends Command
{
    protected $signature = 'db:audit-migrations {--json : Output machine-readable JSON}';

    protected $description = 'Audit migration ordering and detect risky duplicates/overlaps.';

    public function handle(): int
    {
        $migrationPath = database_path('migrations');
        $files = collect(File::files($migrationPath))
            ->map(fn (\SplFileInfo $file): string => $file->getFilename())
            ->sort()
            ->values();

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

        $report = [
            'total_migrations' => $files->count(),
            'duplicate_timestamps' => $timestamps,
            'high_churn_targets' => $highChurnTargets,
            'status' => empty($timestamps) ? 'ok' : 'warning',
        ];

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
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

        $this->newLine();
        $this->line('Recommendation: when adding new schema changes, prefer align/cleanup migrations over repeated incremental field additions on the same table.');

        return self::SUCCESS;
    }
}
