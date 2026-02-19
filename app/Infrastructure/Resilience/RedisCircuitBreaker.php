<?php

declare(strict_types=1);

namespace App\Infrastructure\Resilience;

use App\Application\Shared\Common\Contracts\CircuitBreakerInterface;
use App\Application\Shared\Common\Contracts\MetricsCollectorInterface;
use Illuminate\Redis\Connections\Connection;
use Illuminate\Support\Facades\Log;

final readonly class RedisCircuitBreaker implements CircuitBreakerInterface
{
    private const FAILURE_THRESHOLD = 5;

    private const FAILURE_WINDOW = 60;

    private const COOL_DOWN_PERIOD = 30;

    public function __construct(
        private Connection $redis,
        private MetricsCollectorInterface $metrics
    ) {}

    public function isAvailable(string $serviceName): bool
    {
        $stateKey = $this->getStateKey($serviceName);

        // 1. CHECK EXPLICIT OPEN STATE
        $state = $this->redis->get($stateKey);
        if ($state === 'OPEN') {
            $this->metrics->increment('circuit_breaker.rejected', ['service' => $serviceName]);

            return false;
        }

        // 2. CHECK IMPLICIT HALF-OPEN STATE
        $failures = (int) $this->redis->get($this->getFailuresKey($serviceName));

        if ($failures < self::FAILURE_THRESHOLD) {
            return true;
        }

        // 3. HALF-OPEN: BATTLE FOR THE PROBE
        $probeKey = "circuit:{$serviceName}:probe_lock";

        // DRIVER AGNOSTIC LUA SCRIPT FOR 'SET ... NX PX'
        // Increased Probe TTL to 2000ms to safely cover slow backend responses
        // without leaking multiple probes in high-latency scenarios.
        $script = <<<'LUA'
            return redis.call('SET', KEYS[1], ARGV[1], 'NX', 'PX', ARGV[2])
        LUA;

        $isProbe = $this->redis->eval($script, 1, $probeKey, 'PROBE', 2000);

        if ($isProbe === true || $isProbe === 'OK' || $isProbe === 1) {
            Log::info("CIRCUIT BREAKER: Probing service {$serviceName} (Half-Open)");

            return true;
        }

        $this->metrics->increment('circuit_breaker.rejected_half_open', ['service' => $serviceName]);

        return false;
    }

    public function reportSuccess(string $serviceName): void
    {
        $this->redis->del([
            $this->getFailuresKey($serviceName),
            $this->getStateKey($serviceName),
            "circuit:{$serviceName}:probe_lock",
        ]);

        $this->metrics->increment('circuit_breaker.success', ['service' => $serviceName]);
    }

    public function reportFailure(string $serviceName): void
    {
        $failuresKey = $this->getFailuresKey($serviceName);

        $script = <<<'LUA'
            local current = redis.call('INCR', KEYS[1])
            if current == 1 then
                redis.call('EXPIRE', KEYS[1], tonumber(ARGV[1]))
            end
            return current
        LUA;

        $failures = (int) $this->redis->eval($script, 1, $failuresKey, self::FAILURE_WINDOW);

        if ($failures >= self::FAILURE_THRESHOLD) {
            $this->tripCircuit($serviceName);
        }

        $this->metrics->increment('circuit_breaker.failure', ['service' => $serviceName]);
    }

    private function tripCircuit(string $serviceName): void
    {
        $stateKey = $this->getStateKey($serviceName);
        $this->redis->setex($stateKey, self::COOL_DOWN_PERIOD, 'OPEN');

        $this->metrics->increment('circuit_breaker.tripped', ['service' => $serviceName]);

        Log::critical("CIRCUIT BREAKER TRIPPED: {$serviceName}", [
            'threshold' => self::FAILURE_THRESHOLD,
            'cool_down' => self::COOL_DOWN_PERIOD,
        ]);
    }

    private function getStateKey(string $service): string
    {
        return "circuit:{$service}:state";
    }

    private function getFailuresKey(string $service): string
    {
        return "circuit:{$service}:failures";
    }
}
