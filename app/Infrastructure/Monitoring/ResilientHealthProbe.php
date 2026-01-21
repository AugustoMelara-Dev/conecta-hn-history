<?php

declare(strict_types=1);

namespace App\Infrastructure\Monitoring;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * ResilientHealthProbe - Level 52 Zenith True (Persistent Multi-Layer Fallback)
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  LAYER 1 (DISTRIBUTED): Redis/Centralized cache for fleet-wide state     ║
 * ║  LAYER 2 (PERSISTENT LOCAL): File-based counter survives process death   ║
 * ║  AMNESIA SHIELD: Prevents Boot Storms during fleet restart               ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class ResilientHealthProbe
{
    private const WARNING_LATENCY_MS = 2000;
    private const DANGER_LATENCY_MS = 5000;
    private const SLIDING_WINDOW_SIZE = 10;
    private const BASE_COOLDOWN_SECONDS = 10;
    private const MAX_COOLDOWN_SECONDS = 300; // 5 minutes - Pessimistic Defense
    private const MAX_FAILURE_EXPONENT = 6;
    private const CACHE_KEY_PREFIX = 'outbox:health:';
    private const STATE_TTL_SECONDS = 3600;

    /** @var array<float> */
    private array $latencyWindow = [];
    
    private int $cooldownStartedAt = 0;
    private int $cooldownDuration = 0;
    
    /** @var bool Whether we're in fallback mode due to cache failure */
    private bool $usingFallback = false;
    
    /** @var int Local fallback counter (used when Redis fails) */
    private int $localFailureCount = 0;
    
    private readonly string $workerId;
    private readonly PersistentStateStore $stateStore;

    // LEVEL 53: Pessimistic Discovery Mode
    private bool $isPessimisticBoot = false;

    public function __construct(string $workerId = '')
    {
        $this->workerId = $workerId ?: gethostname() . ':' . getmypid();
        
        // LEVEL 53: Hardened State Persistence
        $this->stateStore = new PersistentStateStore($this->workerId);
        
        $this->rehydrateState();
    }

    /**
     * Check if worker is in Pessimistic Discovery Mode (Boot Storm Prevention).
     */
    public function isPessimisticDiscovery(): bool
    {
        return $this->isPessimisticBoot;
    }

    /**
     * LEVEL 52: Get path to local state file.
     */
    private function getLocalStateFilePath(): string
    {
        // Use global state file (not per-worker) for fleet-wide Boot Storm prevention
        return self::LOCAL_STATE_DIR . '/failure_state.json';
    }

    /**
     * Rehydrate failure state from persistent store with fallback.
     */
    private function rehydrateState(): void
    {
        try {
            $cachedFailures = Cache::get($this->getStateKey('consecutive_failures'), 0);
            $this->localFailureCount = (int) $cachedFailures;
            $this->usingFallback = false;
            
            if ($cachedFailures > 0) {
                Log::info('RESILIENT PROBE: Rehydrated state from distributed cache', [
                    'worker_id' => $this->workerId,
                    'consecutive_failures' => $cachedFailures,
                ]);
            }
        } catch (\Throwable $e) {
            // LAYER 2: Fallback to persistent local file
            $this->activateFallbackMode($e);
            $this->rehydrateFromLocalFile();
        }
    }

    /**
     * LEVEL 52: Rehydrate state from persistent local file.
     * Survives process death and container recycling.
     */
    private function rehydrateFromLocalFile(): void
    {
        $filePath = $this->getLocalStateFilePath();
        
        if (!file_exists($filePath)) {
            return;
        }
        
        try {
            $content = file_get_contents($filePath);
            $state = json_decode($content, true);
            
            if (is_array($state)) {
                $this->localFailureCount = (int) ($state['failures'] ?? 0);
                $storedCooldownEnd = (int) ($state['cooldown_ends_at'] ?? 0);
                
                // AMNESIA SHIELD: Enforce remaining cooldown from previous run
                if ($storedCooldownEnd > time()) {
                    $this->cooldownStartedAt = time();
                    $this->cooldownDuration = $storedCooldownEnd - time();
                    
                    Log::warning('RESILIENT PROBE: Enforcing remaining cooldown from previous run', [
                        'worker_id' => $this->workerId,
                        'remaining_seconds' => $this->cooldownDuration,
                        'previous_failures' => $this->localFailureCount,
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::debug('RESILIENT PROBE: Failed to read local state file', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * LEVEL 52: Persist state to local file.
     */
    private function persistToLocalFile(): void
    {
        $filePath = $this->getLocalStateFilePath();
        $cooldownEndsAt = $this->cooldownStartedAt > 0 
            ? $this->cooldownStartedAt + $this->cooldownDuration 
            : 0;
        
        $state = [
            'failures' => $this->localFailureCount,
            'cooldown_ends_at' => $cooldownEndsAt,
            'updated_at' => time(),
            'worker_id' => $this->workerId,
        ];
        
        try {
            file_put_contents($filePath, json_encode($state), LOCK_EX);
        } catch (\Throwable $e) {
            Log::debug('RESILIENT PROBE: Failed to persist local state', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Activate fallback mode when distributed cache fails.
     */
    private function activateFallbackMode(\Throwable $reason): void
    {
        $this->usingFallback = true;
        
        Log::warning('RESILIENT PROBE: Distributed cache unavailable, activating file-based fallback', [
            'worker_id' => $this->workerId,
            'reason' => $reason->getMessage(),
            'fallback_mode' => 'persistent_local_file',
        ]);
    }

    /**
     * Get consecutive failure count from appropriate layer.
     */
    public function getConsecutiveFailures(): int
    {
        if ($this->usingFallback) {
            return $this->localFailureCount;
        }
        
        try {
            return (int) Cache::get($this->getStateKey('consecutive_failures'), 0);
        } catch (\Throwable $e) {
            $this->activateFallbackMode($e);
            return $this->localFailureCount;
        }
    }

    /**
     * Record a dispatch latency sample.
     */
    public function recordLatency(float $milliseconds): void
    {
        $this->latencyWindow[] = $milliseconds;
        
        if (count($this->latencyWindow) > self::SLIDING_WINDOW_SIZE) {
            array_shift($this->latencyWindow);
        }
        
        // Decay failure count on successful operations
        if ($milliseconds < self::WARNING_LATENCY_MS) {
            $currentFailures = $this->getConsecutiveFailures();
            if ($currentFailures > 0) {
                $this->persistFailureCount($currentFailures - 1);
            }
        }
    }

    /**
     * Get the current average dispatch latency.
     */
    public function getAverageLatency(): float
    {
        if (empty($this->latencyWindow)) {
            return 0.0;
        }

        return array_sum($this->latencyWindow) / count($this->latencyWindow);
    }

    /**
     * Check if in warning zone.
     */
    public function isWarningZone(): bool
    {
        return $this->getAverageLatency() >= self::WARNING_LATENCY_MS;
    }

    /**
     * Check if in danger zone.
     */
    public function isDangerZone(): bool
    {
        return $this->getAverageLatency() >= self::DANGER_LATENCY_MS;
    }

    /**
     * Check if in cooldown.
     */
    public function isInCooldown(): bool
    {
        if ($this->cooldownStartedAt === 0) {
            return false;
        }
        return time() - $this->cooldownStartedAt < $this->cooldownDuration;
    }

    /**
     * Enter cooldown with jittered exponential backoff.
     * 
     * Formula: Cooldown = Base × 2^min(failures, 6) × (1 + Random(-0.2, 0.2))
     * 
     * During fallback mode: Forces MAX_COOLDOWN for pessimistic defense.
     */
    public function enterCooldown(): void
    {
        $consecutiveFailures = $this->getConsecutiveFailures() + 1;
        $this->persistFailureCount($consecutiveFailures);
        
        // PESSIMISTIC DEFENSE: Max cooldown during infrastructure instability
        if ($this->usingFallback) {
            $this->cooldownDuration = self::MAX_COOLDOWN_SECONDS;
            $this->cooldownStartedAt = time();
            
            Log::alert('RESILIENT PROBE: Pessimistic defense activated (fallback mode)', [
                'worker_id' => $this->workerId,
                'cooldown_seconds' => self::MAX_COOLDOWN_SECONDS,
                'reason' => 'distributed_cache_unavailable',
            ]);
            return;
        }
        
        // Normal exponential backoff with jitter
        $exponent = min($consecutiveFailures, self::MAX_FAILURE_EXPONENT);
        $baseCooldown = self::BASE_COOLDOWN_SECONDS * pow(2, $exponent);
        $jitterMultiplier = 1 + ((mt_rand(-200, 200) / 1000));
        
        $this->cooldownDuration = (int) min(
            $baseCooldown * $jitterMultiplier,
            self::MAX_COOLDOWN_SECONDS
        );
        $this->cooldownStartedAt = time();
        
        Log::alert('RESILIENT PROBE: Entering exponential cooldown', [
            'worker_id' => $this->workerId,
            'consecutive_failures' => $consecutiveFailures,
            'cooldown_seconds' => $this->cooldownDuration,
            'exponent' => $exponent,
            'jitter_multiplier' => round($jitterMultiplier, 3),
            'using_fallback' => $this->usingFallback,
        ]);
    }

    /**
     * Exit cooldown state.
     */
    public function exitCooldown(): void
    {
        if ($this->cooldownStartedAt > 0) {
            Log::info('RESILIENT PROBE: Exiting cooldown', [
                'worker_id' => $this->workerId,
                'cooldown_duration_actual' => time() - $this->cooldownStartedAt,
            ]);
        }
        
        $this->cooldownStartedAt = 0;
        $this->cooldownDuration = 0;
        
        // Attempt to restore distributed cache connection
        if ($this->usingFallback) {
            $this->attemptDistributedRestore();
        }
    }

    /**
     * Attempt to restore distributed cache connection.
     */
    private function attemptDistributedRestore(): void
    {
        try {
            Cache::put($this->getStateKey('ping'), time(), 5);
            $this->usingFallback = false;
            
            // Sync local count back to distributed cache
            $this->persistFailureCount($this->localFailureCount);
            
            Log::info('RESILIENT PROBE: Distributed cache restored', [
                'worker_id' => $this->workerId,
                'synced_failure_count' => $this->localFailureCount,
            ]);
        } catch (\Throwable $e) {
            // Still in fallback mode
            Log::debug('RESILIENT PROBE: Distributed cache still unavailable', [
                'reason' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get remaining cooldown seconds.
     */
    public function getRemainingCooldownSeconds(): int
    {
        if (!$this->isInCooldown()) {
            return 0;
        }
        return max(0, $this->cooldownDuration - (time() - $this->cooldownStartedAt));
    }

    /**
     * Reset failure counter.
     */
    public function resetFailures(): void
    {
        $this->persistFailureCount(0);
        $this->latencyWindow = [];
        
        Log::info('RESILIENT PROBE: Failure counter reset', [
            'worker_id' => $this->workerId,
        ]);
    }

    /**
     * Persist failure count with multi-layer fallback.
     */
    /**
     * Persist failure count with multi-layer fallback.
     */
    private function persistFailureCount(int $count): void
    {
        $count = max(0, $count);
        $this->localFailureCount = $count;
        
        // LEVEL 52: Always persist to local file as "Amnesia Shield"
        // This ensures checking disk on restart even if Redis is healthy now
        $this->persistToLocalFile();
        
        if ($this->usingFallback) {
            return; // Only persist locally during fallback
        }
        
        try {
            Cache::put(
                $this->getStateKey('consecutive_failures'),
                $count,
                self::STATE_TTL_SECONDS
            );
        } catch (\Throwable $e) {
            $this->activateFallbackMode($e);
        }
    }

    /**
     * Get cache key with prefix.
     */
    private function getStateKey(string $suffix): string
    {
        return self::CACHE_KEY_PREFIX . $suffix;
    }

    /**
     * Get health status summary.
     */
    public function getStatus(): array
    {
        return [
            'worker_id' => $this->workerId,
            'average_latency_ms' => round($this->getAverageLatency(), 2),
            'sample_count' => count($this->latencyWindow),
            'zone' => $this->isDangerZone() ? 'DANGER' : ($this->isWarningZone() ? 'WARNING' : 'NORMAL'),
            'in_cooldown' => $this->isInCooldown(),
            'remaining_cooldown_seconds' => $this->getRemainingCooldownSeconds(),
            'consecutive_failures' => $this->getConsecutiveFailures(),
            'using_fallback' => $this->usingFallback,
            'current_cooldown_duration' => $this->cooldownDuration,
        ];
    }
}
