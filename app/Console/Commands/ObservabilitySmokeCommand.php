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
        {--sentry : Send a test message to Sentry when DSN is configured}
        {--logs : Emit a structured log line for Better Stack/log drains}
        {--json : Emit machine-readable JSON}';

    protected $description = 'Smoke test launch observability without logging secrets.';

    public function handle(): int
    {
        $traceId = (string) Str::uuid();
        $checks = [];
        $failed = false;

        if ($this->option('logs') || (! $this->option('logs') && ! $this->option('sentry'))) {
            Log::info('observability.smoke', [
                'trace_id' => $traceId,
                'provider' => (string) config('observability.logs.provider', 'better_stack'),
                'environment' => (string) config('app.env'),
            ]);

            $checks['logs'] = [
                'status' => 'ok',
                'provider' => (string) config('observability.logs.provider', 'better_stack'),
                'trace_id' => $traceId,
            ];
        }

        if ($this->option('sentry')) {
            $dsn = (string) config('observability.error_tracking.sentry_dsn', '');

            if ($dsn === '') {
                $checks['sentry'] = [
                    'status' => 'skipped',
                    'reason' => 'Sentry DSN is not configured.',
                ];
            } else {
                try {
                    if (function_exists('\\Sentry\\captureMessage')) {
                        \Sentry\captureMessage('Vito Business OS observability smoke '.$traceId);
                    }

                    $checks['sentry'] = [
                        'status' => 'ok',
                        'trace_id' => $traceId,
                    ];
                } catch (Throwable $exception) {
                    $failed = true;
                    $checks['sentry'] = [
                        'status' => 'failed',
                        'error' => $exception::class,
                        'message' => $exception->getMessage(),
                    ];
                }
            }
        }

        $payload = [
            'status' => $failed ? 'failed' : 'ok',
            'environment' => (string) config('app.env'),
            'checks' => $checks,
        ];

        if ($this->option('json')) {
            $this->line((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } elseif ($failed) {
            $this->error('Observability smoke failed.');
        } else {
            $this->info('Observability smoke completed.');
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
