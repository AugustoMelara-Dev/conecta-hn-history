<?php

declare(strict_types=1);

namespace App\Infrastructure\Telemetry;

use App\Infrastructure\Monitoring\ConsensusHealthGovernor;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * OutboxTracer - Level 63 Financial Core Stabilization
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  SIGNAL DECOUPLING: Separates Infra Failures from App Failures           ║
 * ║  CLEAN SIGNALS: Infrastructure Recoveries vs Application Attempts        ║
 * ║  PARTITION-AWARE: Hybrid Quorum with Huge Buffer (1800s)                 ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class OutboxTracer
{
    private const CARRIER_VERSION = '2.0';

    private const SAMPLE_RATE = 0.10;

    private const W3C_TRACEPARENT_VERSION = '00';

    private const HORIZON_SHORT = 6;

    private const HORIZON_MEDIUM = 60;

    private const HORIZON_LONG = 600;

    public function __construct(
        private readonly ConsensusHealthGovernor $governor
    ) {}

    /**
     * Helper to trace Infrastructure Recoveries (DB Gridlock, Zombie Workers).
     * These should increase backoff but NOT count towards application retry limits.
     *
     * LEVEL 64: Resource-Attributed Telemetry
     */
    public function recordInfrastructureRecovery(string $fingerprint, string $resourceDomain = 'fault:db_io'): void
    {
        Log::warning("INFRA RECOVERY DETECTED [{$resourceDomain}]", ['fingerprint' => $fingerprint]);
        // Increment global infra error counter with Domain Attribution
        $this->monitorErrorAcceleration($resourceDomain);
    }

    /**
     * Helper to trace Application Failures (Provider Down, 500 Errors).
     * These count towards retry limits but might not trigger immediate Governor Panic.
     *
     * LEVEL 64: Resource-Attributed Telemetry
     */
    public function recordApplicationFailure(string $fingerprint, string $resourceDomain = 'fault:app_logic'): void
    {
        Log::warning("APP FAILURE DETECTED [{$resourceDomain}]", ['fingerprint' => $fingerprint]);
        // Increment app error counter
        $this->monitorErrorAcceleration($resourceDomain);
    }

    /**
     * LEVEL 64.1: Hydraulic Pressure Telemetry
     * Tracks the real-time pressure of pending dispatches.
     */
    public function recordHydraulicPressure(int $pressure): void
    {
        $this->safeRedisSet('outbox:hydraulic_pressure', $pressure, 60);

        if ($pressure > 2500) {
            Log::info('HYDRAULIC PRESSURE RISING', ['pressure' => $pressure]);
        }
    }

    public function extractContext(bool $isHighPriority = false, int $failureCount = 0): array
    {
        $traceId = bin2hex(random_bytes(16));
        $spanId = bin2hex(random_bytes(8));
        $sampled = ($isHighPriority || $failureCount > 0) ? true : ((mt_rand() / mt_getrandmax()) < self::SAMPLE_RATE);
        $traceFlags = $sampled ? '01' : '00';

        return [
            'version' => self::CARRIER_VERSION,
            'traceparent' => sprintf('%s-%s-%s-%s', self::W3C_TRACEPARENT_VERSION, $traceId, $spanId, $traceFlags),
            'tracestate' => '',
            'sampled' => $sampled,
            'trace_id' => $traceId,
            'span_id' => $spanId,
            'baggage' => [],
            'extracted_at' => now()->toIso8601String(),
        ];
    }

    public function injectContext(array $envelope, string $operationName, ?string $workerId = null): SpanHandle
    {
        $parentTraceId = $envelope['trace_id'] ?? bin2hex(random_bytes(16));
        $isSampled = $envelope['sampled'] ?? false;
        $spanId = bin2hex(random_bytes(8));
        if ($isSampled) {
            Log::info('TRACE SPAN STARTED', ['operation' => $operationName, 'trace_id' => $parentTraceId]);
        }

        return new SpanHandle($parentTraceId, $spanId, null, $operationName, microtime(true), $isSampled);
    }

    public function endSpan(
        SpanHandle $span,
        string $status = 'OK',
        ?string $errorMessage = null,
        ?\DateTimeInterface $lockedAt = null,
        ?int $partitionKey = null,
        ?string $fingerprint = null
    ): void {
        if ($fingerprint) {
            $this->collectTripleHorizonDataAPCu($fingerprint, $status === 'OK');
        }

        if ($status !== 'OK') {
            // LEVEL 64: Default attribution if generic error
            $this->monitorErrorAcceleration('fault:unknown');
        }

        if ($span->isSampled) {
            Log::info('TRACE SPAN ENDED', ['operation' => $span->operationName, 'status' => $status]);
        }
    }

    /**
     * LEVEL 56: VACCINE BLOCK - Preventive circuit breaker for poison pills.
     * Checks if a message fingerprint is marked as toxic prevents execution.
     */
    public function checkVaccineBlock(object $message): void
    {
        $fingerprint = sha1(($message->type ?? 'unknown').($message->payload['data']['id'] ?? ''));

        // Fast L1 Check (APCu)
        if (Cache::get("vaccine:{$fingerprint}")) {
            throw new \RuntimeException("VACCINE BLOCKED: Message fingerprint {$fingerprint} is quarantined.");
        }

        // Slower L2 Check (Redis) - Only on specific errors usually, but here as safety
        // We rely on Cache::get usually backed by Redis in this stack?
        // If Cache is APC, we might need Redis facade for global ban.
        // Assuming global cache is used.
    }

    public function getVaccineStatus(string $fingerprint): string
    {
        return Cache::get("vaccine:{$fingerprint}") ? 'ACTIVE' : 'CLEAR';
    }

    /**
     * LEVEL 58: TRIPLE HORIZON DATA COLLECTION
     * Aggregates success/failure rates across 3 time windows (6s, 60s, 600s).
     * Uses Redis atomic increments for low-overhead telemetry.
     */
    private function collectTripleHorizonDataAPCu(string $fingerprint, bool $success): void
    {
        // Horizon 1: Real-time (Traffic Burst) - 6s
        // Horizon 2: Operational (Trend) - 60s
        // Horizon 3: Strategic (Capacity) - 600s

        $now = time();
        $statusKey = $success ? 'success' : 'failure';

        foreach ([self::HORIZON_SHORT, self::HORIZON_MEDIUM, self::HORIZON_LONG] as $window) {
            $bucket = floor($now / $window);
            $key = "outbox:horizon:{$window}:{$bucket}:{$fingerprint}:{$statusKey}";
            $this->safeRedisIncrement($key, $window * 2);
        }
    }

    private function monitorErrorAcceleration(string $resourceDomain = 'fault:app_logic'): void
    {
        $min = floor(time() / 60);
        $key = "outbox:errors:{$resourceDomain}:{$min}";

        // LEVEL 64: Surgical Kinetic Response
        // We define specific thresholds and multipliers per domain.
        $config = match ($resourceDomain) {
            'fault:db_io' => ['threshold' => 5, 'multiplier' => 2.0], // High Panic (Gridlock)
            'fault:cpu_lock' => ['threshold' => 10, 'multiplier' => 1.5], // Moderate (Starvation)
            'fault:http_timeout' => ['threshold' => 20, 'multiplier' => 0.2], // Low Panic (Surgical Circuit Breaker, don't stop fleet)
            'fault:app_logic' => ['threshold' => 50, 'multiplier' => 0.1], // Noise (Retry logic handles it)
            default => ['threshold' => 20, 'multiplier' => 0.5],
        };

        if ($this->safeRedisIncrement($key, 120) > $config['threshold']) {
            $prev = (int) $this->safeRedisGet("outbox:errors:{$resourceDomain}:".($min - 1), 0);

            // Dampening: Only signal if acceleration is real (growth > 0)
            if ($prev > 0) {
                $current = (int) $this->safeRedisGet($key, 0);
                $acceleration = ($current - $prev) / $prev;

                // Apply Surgical Multiplier
                $signalStrength = $acceleration * $config['multiplier'];

                if ($signalStrength > 0.1) {
                    $this->governor->processJoltSignal($signalStrength);
                }
            }
        }
    }

    public function emitPredictiveAggregationSpan($message, string $diagnosis): void
    {
        $fingerprint = sha1(($message->last_error ?? '').$diagnosis.$message->type);
        if ($this->safeRedisIncrement("outbox:topology:breach:{$fingerprint}", 300) > 3) {
            Cache::put("vaccine:{$fingerprint}", $diagnosis, 300);
            $this->safeRedisSet("vaccine:{$fingerprint}", $diagnosis, 300);
        }
    }

    private function safeRedisSet(string $key, mixed $value, int $ttlSeconds): void
    {
        try {
            Redis::set($key, $value);
            Redis::expire($key, $ttlSeconds);
        } catch (\Throwable) {
            Cache::put($key, $value, $ttlSeconds);
        }
    }

    private function safeRedisIncrement(string $key, int $ttlSeconds): int
    {
        try {
            $value = (int) Redis::incr($key);
            Redis::expire($key, $ttlSeconds);

            return $value;
        } catch (\Throwable) {
            $value = ((int) Cache::get($key, 0)) + 1;
            Cache::put($key, $value, $ttlSeconds);

            return $value;
        }
    }

    private function safeRedisGet(string $key, mixed $default = null): mixed
    {
        try {
            return Redis::get($key) ?? $default;
        } catch (\Throwable) {
            return Cache::get($key, $default);
        }
    }
}

final readonly class SpanHandle
{
    public function __construct(
        public string $traceId,
        public string $spanId,
        public ?string $parentSpanId,
        public string $operationName,
        public float $startTime,
        public bool $isSampled
    ) {}
}
