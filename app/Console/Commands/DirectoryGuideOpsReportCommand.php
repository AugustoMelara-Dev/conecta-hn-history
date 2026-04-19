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
        $runtimeUnavailable = (int) DB::table($metricsTable)
            ->where('created_at', '>=', $since)
            ->where('degraded_reason', 'guide_runtime_unavailable')
            ->count();

        $avgLatencyMs = (float) DB::table($metricsTable)->where('created_at', '>=', $since)->avg('latency_ms');

        $guideCost = Schema::hasTable($usageTable)
            ? (float) DB::table($usageTable)
                ->where('created_at', '>=', $since)
                ->where('provider', 'openai')
                ->whereNull('tenant_id')
                ->sum('cost')
            : 0.0;

        $quotaRate = $totalRequests > 0 ? ($status429 / $totalRequests) : 0.0;
        $timeoutRate = $totalRequests > 0 ? ($openAiTimeout / $totalRequests) : 0.0;
        $runtimeUnavailableRate = $totalRequests > 0 ? ($runtimeUnavailable / $totalRequests) : 0.0;

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
                'guide_runtime_unavailable' => $runtimeUnavailable,
            ],
            'rates' => [
                'quota_exceeded' => round($quotaRate, 4),
                'openai_timeout' => round($timeoutRate, 4),
                'guide_runtime_unavailable' => round($runtimeUnavailableRate, 4),
            ],
            'avg_latency_ms' => round($avgLatencyMs, 2),
            'openai_cost_total' => round($guideCost, 6),
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
            $this->line(sprintf('- Degraded: timeout=%d auth_failed=%d runtime_unavailable=%d', $openAiTimeout, $openAiAuthFailed, $runtimeUnavailable));
            $this->line(sprintf('- Rates: quota=%.2f%% timeout=%.2f%% runtime_unavailable=%.2f%%', $quotaRate * 100, $timeoutRate * 100, $runtimeUnavailableRate * 100));
            $this->line(sprintf('- Avg latency: %.2fms', $avgLatencyMs));
            $this->line(sprintf('- OpenAI cost (window): $%.6f', $guideCost));

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

        if ((float) ($rates['quota_exceeded'] ?? 0.0) >= $quotaThreshold) {
            $alerts[] = 'quota_exceeded_rate_exceeded';
        }

        return $alerts;
    }
}
