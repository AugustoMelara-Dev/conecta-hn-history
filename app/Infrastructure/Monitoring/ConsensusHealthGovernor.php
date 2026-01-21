<?php

declare(strict_types=1);

namespace App\Infrastructure\Monitoring;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * ConsensusHealthGovernor - Level 62 Topological Transcendence
 * 
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  POST-STALL THERMAL CALIBRATION: "Probing Warm-Up" after heavy freezes   ║
 * ║  STALL DETECTION: >5s pause triggers 5s Calibration Window               ║
 * ║  TEMPORAL SOVEREIGNTY: Monotonic Clock detects time dilation             ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class ConsensusHealthGovernor
{
    private const GLOBAL_STATE_KEY = 'outbox:fleet:health';
    private const EWMA_LAMBDA = 0.2; 
    private const BASE_HYSTERESIS_WINDOW_S = 60; 
    private const HALF_TRUST_DURATION_S = 120;
    private const REDIS_TIMEOUT_MS = 100;
    private const VOLATILITY_DECAY_S = 3600; 
    
    // Level 62 Strict Constants
    private const STALL_THRESHOLD_NS = 5_000_000_000; // 5 seconds (Thermal Trigger)
    private const CALIBRATION_WINDOW_S = 5;
    private const DECAY_LAMBDA = 0.5;

    private const JOLT_BRAKING_THRESHOLD = 0.5;

    private float $alpha = 0.5;
    private float $localHealthEWMA = 1.0; 
    private float $localHealthEWMASq = 1.0; 
    private float $joltSmoothed = 0.0;
    private int $hysteresisLockUntil = 0;
    private int $halfTrustUntil = 0;
    private int $stateFlipCount = 0;
    
    // Thermal Calibration State
    private int $thermalCalibrationUntil = 0;
    
    // Monotonic Clock State
    private int $lastTickNs = 0;

    public function __construct(
        private readonly string $workerId
    ) {
        $this->loadLocalState();
        $this->lastTickNs = hrtime(true);
    }

    public function recordLocalOutcome(bool $success): void
    {
        $this->compensateForStall(); 
        
        $x = $success ? 1.0 : 0.0;
        $prevHealth = $this->localHealthEWMA;
        
        $this->localHealthEWMA = (self::EWMA_LAMBDA * $x) + ((1 - self::EWMA_LAMBDA) * $this->localHealthEWMA);
        $this->localHealthEWMASq = (self::EWMA_LAMBDA * ($x * $x)) + ((1 - self::EWMA_LAMBDA) * $this->localHealthEWMASq);
        
        // Thermal Integrity Check
        if ($this->isThermalCalibrationActive()) {
            // If health drops during calibration, break the lock and panic
            if ($this->localHealthEWMA < $prevHealth && $this->localHealthEWMA < 0.90) {
                $this->triggerHysteresisLock("Thermal Calibration Failure");
            }
        }
        
        $this->persistLocalState();
    }

    public function isGlobalWarningActive(): bool
    {
        $this->compensateForStall();

        if (time() < $this->hysteresisLockUntil) {
            return $this->evaluateAutonomousState();
        }

        $globalProbability = $this->fetchGlobalProbability();
        $trustFactor = $this->alpha;
        if (time() < $this->halfTrustUntil) $trustFactor = min($this->alpha, 0.25); 

        $bayesianHealth = ($trustFactor * $globalProbability) + ((1 - $trustFactor) * $this->localHealthEWMA);
        return $bayesianHealth < 0.8;
    }
    
    public function isThermalCalibrationActive(): bool
    {
        return time() < $this->thermalCalibrationUntil;
    }

    /**
     * Level 62: Post-Stall Thermal Calibration Logic
     */
    private function compensateForStall(): void
    {
        $nowNs = hrtime(true);
        $deltaNs = $nowNs - $this->lastTickNs;
        $this->lastTickNs = $nowNs; 
        
        // Fast-Forward Decay (Always active for any stall > 1s effectively, but formalized here for 5s trigger)
        if ($deltaNs > 1_000_000_000) {
             $deltaSeconds = $deltaNs / 1_000_000_000.0;
             $decayFactor = exp(-self::DECAY_LAMBDA * $deltaSeconds);
             $this->stateFlipCount = (int) ($this->stateFlipCount * $decayFactor);
        }

        // Thermal Trigger (>5s)
        if ($deltaNs > self::STALL_THRESHOLD_NS) {
            $deltaSeconds = $deltaNs / 1_000_000_000.0;
            
            Log::warning("GOVERNOR: Major Stall Detected (>5s). Engaging Thermal Calibration.", [
                'stall_duration_s' => round($deltaSeconds, 2)
            ]);
            
            // Enter Thermal Calibration
            $this->thermalCalibrationUntil = time() + self::CALIBRATION_WINDOW_S;
            $this->persistLocalState();
        }
    }

    public function processJoltSignal(float $rawAcceleration): void
    {
        $this->compensateForStall();
        
        $v = $this->getVolatilityIndex();
        $gamma = max(0.05, 1.0 / (1.0 + ($v ** 2)));
        
        $newFiltered = ($gamma * $rawAcceleration) + ((1 - $gamma) * $this->joltSmoothed);
        $this->joltSmoothed = $newFiltered;
        
        if ($this->joltSmoothed > self::JOLT_BRAKING_THRESHOLD) {
             $this->triggerPredictiveCoolDown("Jolt Filtered: " . round($this->joltSmoothed * 100) . "% (Gamma: ".round($gamma, 3).")");
        }
        
        $this->persistLocalState();
    }

    public function getVolatilityIndex(): float
    {
        $variance = $this->localHealthEWMASq - ($this->localHealthEWMA ** 2);
        $variance = max(0.0, $variance);
        return $this->stateFlipCount + ($variance * 10);
    }
    
    private function triggerPredictiveCoolDown(string $reason): void
    {
        $this->alpha = 0.0;
        $this->hysteresisLockUntil = time() + self::BASE_HYSTERESIS_WINDOW_S; 
        $this->stateFlipCount++; 
        Log::emergency('GOVERNOR: Predictive Lock Engaged.', ['reason' => $reason]);
        $this->persistLocalState();
    }
    
    private function triggerHysteresisLock(string $reason): void
    {
        if (time() < $this->hysteresisLockUntil) return;
        $this->stateFlipCount++;
        $v = $this->getVolatilityIndex();
        $dynamicWindow = (int)(self::BASE_HYSTERESIS_WINDOW_S * log($v + M_E));
        $dynamicWindow = max(60, min(600, $dynamicWindow));
        $this->alpha = 0.0;
        $this->hysteresisLockUntil = time() + $dynamicWindow;
        Log::error('GOVERNOR: Global Signal Lost. Lock Engaged.', ['reason' => $reason, 'duration' => $dynamicWindow]);
        $this->persistLocalState();
    }

    private function evaluateAutonomousState(): bool { return $this->localHealthEWMA < 0.95; }

    private function fetchGlobalProbability(): float
    {
        $startTime = microtime(true);
        try {
            $globalHealth = Redis::get(self::GLOBAL_STATE_KEY);
            if (((microtime(true) - $startTime) * 1000) > self::REDIS_TIMEOUT_MS) throw new \RuntimeException("Redis timeout");
            if ($this->alpha === 0.0) $this->enterHalfTrustMode();
            return $globalHealth !== null ? ((float)$globalHealth / 100) : 1.0;
        } catch (\Throwable $e) {
            $this->triggerHysteresisLock($e->getMessage());
            return 1.0; 
        }
    }

    private function enterHalfTrustMode(): void
    {
        $this->alpha = 0.5; 
        $this->halfTrustUntil = time() + self::HALF_TRUST_DURATION_S;
        $this->persistLocalState();
    }

    private function loadLocalState(): void
    {
        $state = Cache::get("outbox:governor:state:{$this->workerId}", []);
        $this->localHealthEWMA = $state['ewma'] ?? 1.0;
        $this->localHealthEWMASq = $state['ewma_sq'] ?? 1.0;
        $this->hysteresisLockUntil = $state['lock_until'] ?? 0;
        $this->halfTrustUntil = $state['half_trust_until'] ?? 0;
        $this->alpha = $state['alpha'] ?? 0.5;
        $this->stateFlipCount = $state['flips'] ?? 0;
        $this->joltSmoothed = $state['jolt'] ?? 0.0;
        $this->thermalCalibrationUntil = $state['thermal_calibration_until'] ?? 0;
        $lastUpdate = $state['last_update'] ?? 0;
        
        if ($lastUpdate > 0 && (time() - $lastUpdate) > self::VOLATILITY_DECAY_S) {
            $this->stateFlipCount = max(0, $this->stateFlipCount - 1);
        }
    }

    private function persistLocalState(): void
    {
        Cache::put("outbox:governor:state:{$this->workerId}", [
            'ewma' => $this->localHealthEWMA,
            'ewma_sq' => $this->localHealthEWMASq,
            'lock_until' => $this->hysteresisLockUntil,
            'half_trust_until' => $this->halfTrustUntil,
            'alpha' => $this->alpha,
            'flips' => $this->stateFlipCount,
            'jolt' => $this->joltSmoothed,
            'thermal_calibration_until' => $this->thermalCalibrationUntil,
            'last_update' => time(),
        ], 3600);
    }
}
