<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class OpsRuntimeSmokeCommand extends Command
{
    protected $signature = 'ops:runtime-smoke
        {--env= : Environment to validate, defaults to APP_ENV}
        {--json : Emit machine-readable JSON}';

    protected $description = 'Smoke runtime dependencies needed for a safe launch: DB, cache locks, queue config and outbox schema.';

    public function handle(): int
    {
        $environment = (string) ($this->option('env') ?: app()->environment());
        $isProduction = $environment === 'production';
        $checks = [
            'database' => $this->checkDatabase(),
            'cache_lock' => $this->checkCacheLock(),
            'queue_async' => [
                'ok' => ! $isProduction || (string) config('queue.default') !== 'sync',
                'detail' => (string) config('queue.default'),
            ],
            'outbox_schema' => [
                'ok' => Schema::hasTable('outbox_messages'),
                'detail' => Schema::hasTable('outbox_messages') ? 'outbox_messages present' : 'outbox_messages missing',
            ],
        ];

        $payload = [
            'status' => collect($checks)->every(fn (array $check): bool => $check['ok']) ? 'ok' : 'failed',
            'environment' => $environment,
            'checks' => $checks,
        ];

        if ($this->option('json')) {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } elseif ($payload['status'] === 'ok') {
            $this->info('Runtime smoke passed.');
        } else {
            $this->error('Runtime smoke failed.');

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
    private function checkDatabase(): array
    {
        try {
            DB::select('select 1');

            return ['ok' => true, 'detail' => 'select 1'];
        } catch (Throwable $exception) {
            return ['ok' => false, 'detail' => $exception->getMessage()];
        }
    }

    /**
     * @return array{ok: bool, detail: string}
     */
    private function checkCacheLock(): array
    {
        try {
            $lock = Cache::lock('ops-runtime-smoke', 10);

            if (! $lock->get()) {
                return ['ok' => false, 'detail' => 'lock unavailable'];
            }

            $lock->release();

            return ['ok' => true, 'detail' => (string) config('cache.default')];
        } catch (Throwable $exception) {
            return ['ok' => false, 'detail' => $exception->getMessage()];
        }
    }
}
