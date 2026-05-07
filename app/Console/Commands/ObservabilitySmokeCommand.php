<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

final class ObservabilitySmokeCommand extends Command
{
    protected $signature = 'observability:smoke
        {--sentry : Require a Sentry smoke signal}
        {--logs : Emit a structured log smoke signal}
        {--json : Emit machine-readable JSON}';

    protected $description = 'Emit safe observability smoke signals for staging or production verification.';

    public function handle(): int
    {
        $probeId = 'obs_'.Str::lower(Str::random(12));
        $checks = [];

        if ($this->option('logs') || ! $this->option('sentry')) {
            $checks['structured_log'] = $this->emitStructuredLog($probeId);
        }

        if ($this->option('sentry')) {
            $checks['sentry'] = $this->emitSentryProbe($probeId);
        }

        $payload = [
            'status' => collect($checks)->every(fn (array $check): bool => $check['ok']) ? 'ok' : 'failed',
            'probe_id' => $probeId,
            'checks' => $checks,
        ];

        if ($this->option('json')) {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } elseif ($payload['status'] === 'ok') {
            $this->info("Observability smoke emitted [{$probeId}].");
        } else {
            $this->error("Observability smoke failed [{$probeId}].");

            foreach ($checks as $name => $check) {
                $status = $check['ok'] ? 'OK' : 'FAIL';
                $this->line("- {$name}: {$status} ({$check['detail']})");
            }
        }

        return $payload['status'] === 'ok' ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @return array{ok: bool, detail: string}
     */
    private function emitStructuredLog(string $probeId): array
    {
        try {
            Log::info('observability.smoke', [
                'probe_id' => $probeId,
                'component' => 'launch-readiness',
                'contains_secret' => false,
            ]);

            return ['ok' => true, 'detail' => 'structured log emitted'];
        } catch (Throwable $exception) {
            return ['ok' => false, 'detail' => $exception->getMessage()];
        }
    }

    /**
     * @return array{ok: bool, detail: string}
     */
    private function emitSentryProbe(string $probeId): array
    {
        $dsn = config('sentry.dsn') ?: env('SENTRY_LARAVEL_DSN') ?: env('SENTRY_DSN');

        if (! filled($dsn)) {
            return ['ok' => false, 'detail' => 'Sentry DSN is not configured.'];
        }

        if (! function_exists('Sentry\captureMessage')) {
            return ['ok' => false, 'detail' => 'Sentry SDK is not installed or not booted.'];
        }

        try {
            \Sentry\captureMessage("conecta-hn observability smoke {$probeId}");

            return ['ok' => true, 'detail' => 'Sentry message captured'];
        } catch (Throwable $exception) {
            return ['ok' => false, 'detail' => $exception->getMessage()];
        }
    }
}
