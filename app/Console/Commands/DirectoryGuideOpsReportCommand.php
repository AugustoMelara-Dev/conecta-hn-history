<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class DirectoryGuideOpsReportCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'directory:guide:ops-report
        {--hours=24 : Time window in hours}
        {--json : Emit JSON output}
        {--fail-on-alert : Exit with code 1 when an alert threshold is exceeded}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate a basic operations dashboard for guide quality, cost, and quota pressure.';

    public function handle(): int
    {
        $hours = max(1, (int) $this->option('hours'));
        $since = now()->subHours($hours);

        $metricsTable = 'directory_guide_request_metrics';
        $usageTable = 'ai_usage_logs';

        if (! Schema::hasTable($metricsTable)) {
            $message = sprintf('Missing required table: %s', $metricsTable);

            if ((bool) $this->option('json')) {
                $this->line((string) json_encode([
                    'status' => 'failed',
                    'error' => $message,
                    'timestamp' => now()->toIso8601String(),
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            } else {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $totalRequests = (int) DB::table($metricsTable)->where('created_at', '>=', $since)->count();
        $status429 = (int) DB::table($metricsTable)->where('created_at', '>=', $since)->where('status_code', 429)->count();
        $status5xx = (int) DB::table($metricsTable)->where('created_at', '>=', $since)->where('status_code', '>=', 500)->count();
        $hybridCount = (int) DB::table($metricsTable)->where('created_at', '>=', $since)->where('ranking_mode', 'hybrid')->count();
        $fallbackCount = (int) DB::table($metricsTable)->where('created_at', '>=', $since)->where('ranking_mode', 'fallback')->count();
        $sqlCount = (int) DB::table($metricsTable)->where('created_at', '>=', $since)->where('ranking_mode', 'sql')->count();

        $openAiTimeout = (int) DB::table($metricsTable)
            ->where('created_at', '>=', $since)
            ->where('degraded_reason', 'openai_timeout')
            ->count();
        $openAiAuthFailed = (int) DB::table($metricsTable)
            ->where('created_at', '>=', $since)
            ->where('degraded_reason', 'openai_auth_failed')
            ->count();
        $qdrantUnavailable = (int) DB::table($metricsTable)
            ->where('created_at', '>=', $since)
            ->where('degraded_reason', 'qdrant_unavailable')
            ->count();
        $runtimeUnavailable = (int) DB::table($metricsTable)
            ->where('created_at', '>=', $since)
            ->where('degraded_reason', 'guide_runtime_unavailable')
            ->count();

        $avgLatencyMs = (float) DB::table($metricsTable)->where('created_at', '>=', $since)->avg('latency_ms');
        $latencySamples = DB::table($metricsTable)
            ->where('created_at', '>=', $since)
            ->whereNotNull('latency_ms')
            ->orderBy('latency_ms')
            ->pluck('latency_ms')
            ->map(static fn (mixed $value): int => max(0, (int) $value))
            ->all();
        $p50LatencyMs = $this->percentile($latencySamples, 0.50);
        $p95LatencyMs = $this->percentile($latencySamples, 0.95);

        $guideCost = Schema::hasTable($usageTable)
            ? (float) DB::table($usageTable)
                ->where('created_at', '>=', $since)
                ->where('provider', 'openai')
                ->whereNull('tenant_id')
                ->sum('cost')
            : 0.0;

        $quotaRate = $totalRequests > 0 ? ($status429 / $totalRequests) : 0.0;
        $timeoutRate = $totalRequests > 0 ? ($openAiTimeout / $totalRequests) : 0.0;
        $qdrantUnavailableRate = $totalRequests > 0 ? ($qdrantUnavailable / $totalRequests) : 0.0;
        $runtimeUnavailableRate = $totalRequests > 0 ? ($runtimeUnavailable / $totalRequests) : 0.0;
        $fallbackRate = $totalRequests > 0 ? ($fallbackCount / $totalRequests) : 0.0;
        $errorRate = $totalRequests > 0 ? ($status5xx / $totalRequests) : 0.0;
        $costPerRequest = $totalRequests > 0 ? ($guideCost / $totalRequests) : 0.0;

        $endpointBreakdown = [];
        if (Schema::hasColumn($metricsTable, 'endpoint')) {
            $endpointBreakdown = DB::table($metricsTable)
                ->where('created_at', '>=', $since)
                ->selectRaw('COALESCE(endpoint, ?) as endpoint_key, COUNT(*) as aggregate_count', ['guide'])
                ->groupBy('endpoint_key')
                ->pluck('aggregate_count', 'endpoint_key')
                ->map(static fn (mixed $count): int => (int) $count)
                ->all();
        }

        $summary = [
            'window_hours' => $hours,
            'since' => $since->toIso8601String(),
            'total_requests' => $totalRequests,
            'status_429' => $status429,
            'status_5xx' => $status5xx,
            'ranking_modes' => [
                'hybrid' => $hybridCount,
                'sql' => $sqlCount,
                'fallback' => $fallbackCount,
            ],
            'degraded_reasons' => [
                'openai_timeout' => $openAiTimeout,
                'openai_auth_failed' => $openAiAuthFailed,
                'qdrant_unavailable' => $qdrantUnavailable,
                'guide_runtime_unavailable' => $runtimeUnavailable,
            ],
            'rates' => [
                'quota_exceeded' => round($quotaRate, 4),
                'openai_timeout' => round($timeoutRate, 4),
                'qdrant_unavailable' => round($qdrantUnavailableRate, 4),
                'guide_runtime_unavailable' => round($runtimeUnavailableRate, 4),
                'fallback' => round($fallbackRate, 4),
                'error_5xx' => round($errorRate, 4),
            ],
            'avg_latency_ms' => round($avgLatencyMs, 2),
            'latency_p50_ms' => round($p50LatencyMs, 2),
            'latency_p95_ms' => round($p95LatencyMs, 2),
            'openai_cost_total' => round($guideCost, 6),
            'openai_cost_per_request' => round($costPerRequest, 6),
            'endpoint_breakdown' => $endpointBreakdown,
            'timestamp' => now()->toIso8601String(),
        ];

        $alerts = $this->detectAlerts($summary);
        $summary['alerts'] = $alerts;

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->line(sprintf('Directory Guide Ops Report (%dh)', $hours));
            $this->line(sprintf('- Requests: %d | 429: %d | 5xx: %d', $totalRequests, $status429, $status5xx));
            $this->line(sprintf('- Ranking: hybrid=%d sql=%d fallback=%d', $hybridCount, $sqlCount, $fallbackCount));
            $this->line(sprintf('- Degraded: timeout=%d auth_failed=%d qdrant_unavailable=%d runtime_unavailable=%d', $openAiTimeout, $openAiAuthFailed, $qdrantUnavailable, $runtimeUnavailable));
            $this->line(sprintf('- Rates: quota=%.2f%% timeout=%.2f%% qdrant=%.2f%% runtime_unavailable=%.2f%% fallback=%.2f%% error_5xx=%.2f%%', $quotaRate * 100, $timeoutRate * 100, $qdrantUnavailableRate * 100, $runtimeUnavailableRate * 100, $fallbackRate * 100, $errorRate * 100));
            $this->line(sprintf('- Latency: avg=%.2fms p50=%.2fms p95=%.2fms', $avgLatencyMs, $p50LatencyMs, $p95LatencyMs));
            $this->line(sprintf('- OpenAI cost (window): $%.6f', $guideCost));
            $this->line(sprintf('- OpenAI cost/request: $%.6f', $costPerRequest));

            if ($alerts === []) {
                $this->line('ALERTS: none');
            } else {
                foreach ($alerts as $alert) {
                    $this->warn('ALERT: '.$alert);
                }
            }
        }

        if ((bool) $this->option('fail-on-alert') && $alerts !== []) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $summary
     * @return list<string>
     */
    private function detectAlerts(array $summary): array
    {
        $alerts = [];
        $rates = (array) ($summary['rates'] ?? []);
        $degraded = (array) ($summary['degraded_reasons'] ?? []);

        $runtimeThreshold = (float) config('directory.guide.alerts.guide_runtime_unavailable_rate', 0.05);
        $authCountThreshold = (int) config('directory.guide.alerts.openai_auth_failed_count', 1);
        $timeoutThreshold = (float) config('directory.guide.alerts.openai_timeout_rate', 0.25);
        $qdrantThreshold = (float) config('directory.guide.alerts.qdrant_unavailable_rate', 0.30);
        $quotaThreshold = (float) config('directory.guide.alerts.quota_exceeded_rate', 0.40);

        if ((float) ($rates['guide_runtime_unavailable'] ?? 0.0) >= $runtimeThreshold) {
            $alerts[] = 'guide_runtime_unavailable_rate_exceeded';
        }

        if ((int) ($degraded['openai_auth_failed'] ?? 0) >= $authCountThreshold) {
            $alerts[] = 'openai_auth_failed_count_exceeded';
        }

        if ((float) ($rates['openai_timeout'] ?? 0.0) >= $timeoutThreshold) {
            $alerts[] = 'openai_timeout_rate_exceeded';
        }

        if ((float) ($rates['qdrant_unavailable'] ?? 0.0) >= $qdrantThreshold) {
            $alerts[] = 'qdrant_unavailable_rate_exceeded';
        }

        if ((float) ($rates['quota_exceeded'] ?? 0.0) >= $quotaThreshold) {
            $alerts[] = 'quota_exceeded_rate_exceeded';
        }

        return $alerts;
    }

    /**
     * @param  list<int>  $values
     */
    private function percentile(array $values, float $percentile): float
    {
        if ($values === []) {
            return 0.0;
        }

        sort($values);

        $normalized = max(0.0, min(1.0, $percentile));
        $index = (int) ceil($normalized * count($values)) - 1;
        $index = max(0, min(count($values) - 1, $index));

        return (float) $values[$index];
    }
}
