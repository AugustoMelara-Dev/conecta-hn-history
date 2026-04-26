<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

final class HttpPerformanceP95ReportCommand extends Command
{
    protected $signature = 'performance:http-p95-report
        {--log= : Path to the Laravel log file}
        {--json : Emit machine-readable JSON}';

    protected $description = 'Report p95 latency from http.request.completed and guide.request.completed logs.';

    public function handle(): int
    {
        $path = (string) ($this->option('log') ?: storage_path('logs/laravel.log'));

        if (! is_file($path)) {
            $this->error('Log file not found: '.$path);

            return self::FAILURE;
        }

        $http = [];
        $guide = [];

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            $this->error('Unable to open log file: '.$path);

            return self::FAILURE;
        }

        while (($line = fgets($handle)) !== false) {
            if (str_contains($line, 'http.request.completed')) {
                $duration = $this->intFromLog($line, 'duration_ms');
                $surface = $this->stringFromLog($line, 'surface') ?: 'unknown';
                $route = $this->stringFromLog($line, 'route_name') ?: $this->stringFromLog($line, 'route_uri') ?: 'unknown';

                if ($duration !== null) {
                    $http['surface:'.$surface][] = $duration;
                    $http['route:'.$route][] = $duration;
                }
            }

            if (str_contains($line, 'guide.request.completed')) {
                $duration = $this->intFromLog($line, 'latency_ms');
                $endpoint = $this->stringFromLog($line, 'endpoint') ?: 'guide';

                if ($duration !== null) {
                    $guide['endpoint:'.$endpoint][] = $duration;
                }
            }
        }

        fclose($handle);

        $report = [
            'http' => $this->summaries($http),
            'guide' => $this->summaries($guide),
            'generated_at' => now()->toIso8601String(),
            'source_log' => $path,
        ];

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info('HTTP p95');
        $this->table(['scope', 'count', 'p50_ms', 'p95_ms', 'max_ms'], $report['http']);

        $this->info('Directory guide p95');
        $this->table(['scope', 'count', 'p50_ms', 'p95_ms', 'max_ms'], $report['guide']);

        return self::SUCCESS;
    }

    private function intFromLog(string $line, string $key): ?int
    {
        return preg_match('/"'.preg_quote($key, '/').'":(\d+)/', $line, $matches) === 1
            ? (int) $matches[1]
            : null;
    }

    private function stringFromLog(string $line, string $key): ?string
    {
        return preg_match('/"'.preg_quote($key, '/').'":"([^"]*)"/', $line, $matches) === 1
            ? (string) $matches[1]
            : null;
    }

    /**
     * @param  array<string, list<int>>  $samples
     * @return list<array{scope:string,count:int,p50_ms:int,p95_ms:int,max_ms:int}>
     */
    private function summaries(array $samples): array
    {
        $rows = [];

        foreach ($samples as $scope => $durations) {
            sort($durations);
            $count = count($durations);
            if ($count === 0) {
                continue;
            }

            $rows[] = [
                'scope' => $scope,
                'count' => $count,
                'p50_ms' => $this->percentile($durations, 0.50),
                'p95_ms' => $this->percentile($durations, 0.95),
                'max_ms' => max($durations),
            ];
        }

        usort($rows, static fn (array $left, array $right): int => $right['p95_ms'] <=> $left['p95_ms']);

        return $rows;
    }

    /**
     * @param  list<int>  $values
     */
    private function percentile(array $values, float $percentile): int
    {
        $index = max(0, (int) ceil(count($values) * $percentile) - 1);

        return $values[$index] ?? 0;
    }
}
