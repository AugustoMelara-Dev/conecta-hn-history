<?php

declare(strict_types=1);

namespace App\Infrastructure\Common\Idempotency;

use App\Application\Common\Contracts\LockInterface;
use App\Application\Common\Exceptions\LockTimeoutException;
use Illuminate\Redis\Connections\Connection;
use Illuminate\Support\Str;

final readonly class RedisLockAdapter implements LockInterface
{
    private string $ownerToken;

    public function __construct(
        private Connection $redis,
        private string $key
    ) {
        $this->ownerToken = Str::random(64);
    }

    public function get(callable|null $callback = null): bool
    {
        // ACQUIRE LOCK (LUA SCRIPT)
        // Uses Lua to guarantee behavior across different Redis drivers (Predis vs PhpRedis)
        // KEYS[1]: Lock Key
        // ARGV[1]: Owner Token
        // ARGV[2]: TTL in Milliseconds (10,000ms = 10s)
        $script = <<<'LUA'
            return redis.call('SET', KEYS[1], ARGV[1], 'NX', 'PX', ARGV[2])
        LUA;

        $result = $this->redis->eval($script, 1, $this->getLockKey(), $this->ownerToken, 10000);

        // Redis SET ... NX PX returns OK (string) or 1 (true) on success, nil (null) or false on failure.
        $acquired = ($result === true || $result === 'OK' || $result === 1);

        if (! $acquired) {
            return false;
        }

        if ($callback === null) {
            return true;
        }

        try {
            $callback();
            return true;
        } finally {
            $this->release();
        }
    }

    public function block(int $seconds, callable|null $callback = null): mixed
    {
        $starting = microtime(true);

        while (! $this->get()) {
            if ((microtime(true) - $starting) >= $seconds) {
                throw new LockTimeoutException("Unable to acquire lock for key: {$this->key} after {$seconds} seconds.");
            }

            // High-throughput spin-lock
            usleep(25_000);
        }

        if ($callback === null) {
            return true;
        }

        try {
            return $callback();
        } finally {
            $this->release();
        }
    }

    public function release(): void
    {
        // ATOMIC RELEASE (Verify Ownership)
        $script = <<<'LUA'
            if redis.call("get", KEYS[1]) == ARGV[1] then
                return redis.call("del", KEYS[1])
            else
                return 0
            end
        LUA;

        $this->redis->eval($script, 1, $this->getLockKey(), $this->ownerToken);
    }

    private function getLockKey(): string
    {
        return "lock:{$this->key}";
    }
}
