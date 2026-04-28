<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\RuntimeSmokeJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Symfony\Component\Console\Output\BufferedOutput;
use Throwable;

final class OpsRuntimeSmokeCommand extends Command
{
    protected $signature = 'ops:runtime-smoke
        {--env= : Environment to validate, defaults to APP_ENV}
        {--disk= : Storage disk to smoke, defaults to filesystems.default}
        {--skip-worker : Dispatch queue smoke job but do not run queue:work --once}
        {--json : Emit machine-readable JSON}';

    protected $description = 'Run launch runtime smokes for config, storage, cache locks and queue processing.';

    public function handle(): int
    {
        $environment = (string) ($this->option('env') ?: app()->environment());
        $checks = [];

        $checks['config_guard'] = $this->callJson('config:production-guard', [
            '--env' => $environment,
            '--json' => true,
        ]);

        $checks['storage_guard'] = $this->callJson('storage:production-guard', [
            '--env' => $environment,
            '--json' => true,
        ]);

        $checks['storage_smoke'] = $this->callJson('storage:smoke', [
            '--disk' => (string) ($this->option('disk') ?: config('filesystems.default', 'local')),
            '--json' => true,
        ]);

        $checks['cache_lock'] = $this->cacheLockSmoke();
        $checks['queue'] = $this->queueSmoke((bool) $this->option('skip-worker'));

        $failed = collect($checks)->contains(
            static fn (array $check): bool => ($check['exit_code'] ?? 1) !== self::SUCCESS
                || (($check['payload']['status'] ?? 'failed') === 'failed')
        );

        $payload = [
            'status' => $failed ? 'failed' : 'ok',
            'environment' => $environment,
            'queue_connection' => (string) config('queue.default'),
            'cache_store' => (string) config('cache.default'),
            'checks' => $checks,
        ];

        if ($this->option('json')) {
            $this->line((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } elseif ($failed) {
            $this->error('Runtime smoke failed.');
        } else {
            $this->info('Runtime smoke passed.');
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @return array{exit_code:int, payload:array<string,mixed>, raw:string}
     */
    private function callJson(string $command, array $parameters): array
    {
        $output = new BufferedOutput;
        $exitCode = Artisan::call($command, $parameters, $output);
        $raw = trim($output->fetch());
        $payload = [];

        if ($raw !== '') {
            try {
                $payload = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            } catch (Throwable $exception) {
                $payload = [
                    'status' => 'failed',
                    'error' => $exception::class,
                    'message' => 'Command did not emit valid JSON.',
                ];
            }
        }

        return [
            'exit_code' => $exitCode,
            'payload' => $payload,
            'raw' => $raw,
        ];
    }

    /**
     * @return array{exit_code:int, payload:array<string,mixed>}
     */
    private function cacheLockSmoke(): array
    {
        $lockName = 'ops-runtime-smoke:'.Str::uuid();
        $lock = Cache::lock($lockName, 10);

        try {
            $acquired = $lock->get();

            if ($acquired) {
                $lock->release();
            }

            return [
                'exit_code' => $acquired ? self::SUCCESS : self::FAILURE,
                'payload' => [
                    'status' => $acquired ? 'ok' : 'failed',
                    'lock' => $lockName,
                ],
            ];
        } catch (Throwable $exception) {
            return [
                'exit_code' => self::FAILURE,
                'payload' => [
                    'status' => 'failed',
                    'lock' => $lockName,
                    'error' => $exception::class,
                    'message' => $exception->getMessage(),
                ],
            ];
        }
    }

    /**
     * @return array{exit_code:int, payload:array<string,mixed>, raw?:string}
     */
    private function queueSmoke(bool $skipWorker): array
    {
        $cacheKey = 'ops-runtime-smoke:queue:'.Str::uuid();

        try {
            RuntimeSmokeJob::dispatch($cacheKey);

            if (! $skipWorker && config('queue.default') !== 'sync') {
                $output = new BufferedOutput;
                $exitCode = Artisan::call('queue:work', [
                    'connection' => (string) config('queue.default'),
                    '--once' => true,
                    '--tries' => 1,
                    '--timeout' => 30,
                ], $output);
                $raw = trim($output->fetch());
            } else {
                $exitCode = self::SUCCESS;
                $raw = '';
            }

            $processed = Cache::has($cacheKey);

            return [
                'exit_code' => $processed ? $exitCode : self::FAILURE,
                'payload' => [
                    'status' => $processed ? 'ok' : 'failed',
                    'worker_executed' => ! $skipWorker && config('queue.default') !== 'sync',
                    'cache_key' => $cacheKey,
                ],
                'raw' => $raw,
            ];
        } catch (Throwable $exception) {
            return [
                'exit_code' => self::FAILURE,
                'payload' => [
                    'status' => 'failed',
                    'cache_key' => $cacheKey,
                    'error' => $exception::class,
                    'message' => $exception->getMessage(),
                ],
            ];
        } finally {
            Cache::forget($cacheKey);
        }
    }
}
