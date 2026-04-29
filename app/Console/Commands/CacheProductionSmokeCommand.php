<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

final class CacheProductionSmokeCommand extends Command
{
    protected $signature = 'cache:production-smoke
        {--ttl=60 : Smoke key TTL in seconds}
        {--json : Emit machine-readable JSON}';

    protected $description = 'Verify the configured cache store can write, read, and delete a short-lived key.';

    public function handle(): int
    {
        $ttl = max(5, (int) $this->option('ttl'));
        $key = 'production-cache-smoke:'.Str::uuid();
        $value = 'conecta-hn-cache-smoke:'.Str::uuid();
        $payload = [
            'status' => 'failed',
            'cache_store' => (string) config('cache.default'),
            'key' => $key,
        ];

        try {
            Cache::put($key, $value, now()->addSeconds($ttl));
            $readValue = Cache::get($key);
            Cache::forget($key);

            $matches = hash_equals($value, is_string($readValue) ? $readValue : '');

            $payload['status'] = $matches ? 'ok' : 'failed';
            $payload['read_matches_write'] = $matches;

            $exitCode = $matches ? self::SUCCESS : self::FAILURE;
        } catch (Throwable $exception) {
            $payload['error'] = $exception::class;
            $payload['message'] = $this->friendlyMessage($exception);

            $exitCode = self::FAILURE;
        }

        if ($this->option('json')) {
            $this->line((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } elseif ($exitCode === self::SUCCESS) {
            $this->info('Production cache smoke passed.');
        } else {
            $this->error('Production cache smoke failed.');
            $this->line((string) ($payload['message'] ?? 'Cache read did not match written value.'));
        }

        return $exitCode;
    }

    private function friendlyMessage(Throwable $exception): string
    {
        $message = $exception->getMessage();

        if (str_contains($message, 'ERR DB index is out of range')) {
            return 'Redis rejected the configured cache database. Set REDIS_CACHE_DB=0 or point REDIS_CACHE_CONNECTION to default for single-database providers.';
        }

        return $message;
    }
}
