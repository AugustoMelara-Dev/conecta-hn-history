<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * QueryStrategist - Level 62 Topological Transcendence
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  DERIVATIVE-BASED GATING: Velocity Tracking (dL/dt) vs Static Thresholds ║
 * ║  LATENCY GRADIENT: Proactive Load Shedding if Latency Doubles in <10s    ║
 * ║  MULTIVARIATE MATRIX: IO vs CPU separation remains active                ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class QueryStrategist
{
    private const CACHE_KEY_PREFIX = 'outbox:query_strategy:';

    private const HEATMAP_KEY = 'outbox:strategy:heatmap';

    private const VELOCITY_HISTORY_KEY = 'outbox:latency:velocity_window';

    // Strategy Definitions
    public const STRATEGY_COMPOSITE_INDEX = 'FORCE INDEX (idx_outbox_partition_sequence)';

    public const STRATEGY_PARTITION_SCAN = 'FORCE INDEX (idx_outbox_partition_query)';

    public const STRATEGY_SECONDARY_FAILSAFE = 'IGNORE INDEX (idx_outbox_partition_sequence)';

    private const RESOURCE_CPU = 'cpu';

    private const RESOURCE_IO = 'io';

    // Level 62 Constants
    private const IO_SATURATION_THRESHOLD_BLOCK = 85;

    private const CPU_HEALTHY_THRESHOLD = 50;

    private const VELOCITY_WINDOW_S = 10;

    private const MAX_HISTORY_POINTS = 20;

    private array $strategies = [
        self::STRATEGY_COMPOSITE_INDEX,
        self::STRATEGY_PARTITION_SCAN,
        self::STRATEGY_SECONDARY_FAILSAFE,
    ];

    private array $costMatrix = [
        self::STRATEGY_COMPOSITE_INDEX => self::RESOURCE_IO,
        self::STRATEGY_PARTITION_SCAN => self::RESOURCE_IO,
        self::STRATEGY_SECONDARY_FAILSAFE => self::RESOURCE_CPU,
    ];

    public function __construct() {}

    public function getOptimizationHint(string $contextIdentifier, array $contextMetrics = []): string
    {
        // 1. Calculate Derivative-Based Epsilon
        // If dL/dt exceeds critical threshold (The Wall), we force epsilon=0 globally or per resource
        $velocityState = $this->analyzeLatencyVelocity();

        if ($velocityState['is_collapsing']) {
            Log::critical("STRATEGIST: LATENCY WALL IMMINENT. dL/dt = {$velocityState['slope']} ms/s. Engaging Emergency Braking.");

            return $this->getLegacyStrategy($contextIdentifier); // Fallback to safest known
        }

        // 2. Resource-Specific Epsilon (Multivariate)
        $epsilonVector = $this->calculateMultivariateEpsilon($velocityState);

        // 3. Exploration Gate
        if ((mt_rand() / mt_getrandmax()) < 0.05) {
            $candidateIndex = array_rand($this->strategies);
            $candidate = $this->strategies[$candidateIndex];
            $resource = $this->costMatrix[$candidate];

            if (($epsilonVector[$resource] ?? 0.0) > 0.0) {
                return $candidate;
            }
        }

        // 4. Heatmap Optimization
        if (! empty($contextMetrics)) {
            $optimalIndex = $this->lookupOptimalStrategy($contextMetrics);
            if ($optimalIndex !== null) {
                return $this->strategies[$optimalIndex];
            }
        }

        return $this->getLegacyStrategy($contextIdentifier);
    }

    public function recordExecutionTime(string $contextIdentifier, float $latencyMs, array $contextMetrics = []): void
    {
        $this->updateVelocityHistory($latencyMs);

        if (empty($contextMetrics)) {
            $this->updateLegacyState($contextIdentifier, $latencyMs);

            return;
        }
        $this->updateHeatmap($contextMetrics, $contextIdentifier, $latencyMs);
    }

    private function updateVelocityHistory(float $latencyMs): void
    {
        // Zero-Allocation constraint: Minimize array churn.
        // We use a small Rolling Window in Cache.
        // Structure: [ [timestamp, latency], ... ]
        $history = Cache::get(self::VELOCITY_HISTORY_KEY, []);
        $now = microtime(true);
        $history[] = [$now, $latencyMs];

        // Prune older than 10s
        $cutoff = $now - self::VELOCITY_WINDOW_S;
        $history = array_filter($history, fn ($p) => $p[0] >= $cutoff);

        // Cap size to avoid memory explosion
        // Reset keys
        $history = array_values(array_slice($history, -self::MAX_HISTORY_POINTS));

        Cache::put(self::VELOCITY_HISTORY_KEY, $history, 30);
    }

    private function analyzeLatencyVelocity(): array
    {
        $history = Cache::get(self::VELOCITY_HISTORY_KEY, []);
        if (count($history) < 5) {
            return ['slope' => 0.0, 'is_collapsing' => false];
        }

        // Simple Linear Regression slope calculation (dL/dt)
        // or just diff min/max? Regression is cleaner.
        // X = time offset, Y = latency
        // Normalize time to starts at 0
        $t0 = $history[0][0];
        $sumX = 0;
        $sumY = 0;
        $sumXY = 0;
        $sumXX = 0;
        $n = count($history);

        foreach ($history as $p) {
            $x = $p[0] - $t0;
            $y = $p[1];
            $sumX += $x;
            $sumY += $y;
            $sumXY += ($x * $y);
            $sumXX += ($x * $x);
        }

        // Slope = (N*SumXY - SumX*SumY) / (N*SumXX - SumX^2)
        $denominator = ($n * $sumXX) - ($sumX * $sumX);
        if ($denominator == 0) {
            return ['slope' => 0.0, 'is_collapsing' => false];
        }

        $slope = (($n * $sumXY) - ($sumX * $sumY)) / $denominator;

        // Check for "Doubling in < 10s"
        // If initial latency was L0, and slope is M.
        // L(10) = L0 + 10M.
        // If 10M > L0, we are doubling.
        // M > L0 / 10.

        $avgL = $sumY / $n;
        $doublingThresholdSlope = max(10, $avgL / 10.0);

        return [
            'slope' => $slope,
            'is_collapsing' => ($slope > $doublingThresholdSlope),
        ];
    }

    private function calculateMultivariateEpsilon(array $velocityState): array
    {
        $cpuLoad = (int) Cache::get('system:load:cpu', 0);
        $ioSaturation = (int) Cache::get('system:load:io', 0);

        $eps = [];

        // CPU Logic
        if ($cpuLoad < self::CPU_HEALTHY_THRESHOLD) {
            $eps[self::RESOURCE_CPU] = 0.05;
        } elseif ($cpuLoad > 90) {
            $eps[self::RESOURCE_CPU] = 0.0;
        } else {
            $eps[self::RESOURCE_CPU] = 0.005;
        }

        // IO Logic (Gated by IO Saturation OR Latency Velocity)
        if ($ioSaturation > self::IO_SATURATION_THRESHOLD_BLOCK || $velocityState['is_collapsing']) {
            $eps[self::RESOURCE_IO] = 0.0; // Hard Block
        } else {
            $eps[self::RESOURCE_IO] = 0.05;
        }

        return $eps;
    }

    private function lookupOptimalStrategy(array $metrics): ?int
    {
        $batch = $metrics['batch_size'] ?? 0;
        $age = $metrics['queue_age'] ?? 0;
        $bb = $batch < 100 ? 'SMALL' : ($batch < 1000 ? 'MEDIUM' : 'LARGE');
        $ab = $age < 60 ? 'FRESH' : ($age < 600 ? 'STALE' : 'DEAD');

        $heatmap = Cache::get(self::HEATMAP_KEY, []);
        $cell = $heatmap[$bb][$ab] ?? [];
        if (empty($cell)) {
            return null;
        }
        asort($cell);

        return array_key_first($cell);
    }

    private function updateHeatmap(array $metrics, string $strategyMsg, float $latencyMs): void
    {
        $batch = $metrics['batch_size'] ?? 0;
        $age = $metrics['queue_age'] ?? 0;
        $bb = $batch < 100 ? 'SMALL' : ($batch < 1000 ? 'MEDIUM' : 'LARGE');
        $ab = $age < 60 ? 'FRESH' : ($age < 600 ? 'STALE' : 'DEAD');

        $idx = array_search($strategyMsg, $this->strategies);
        if ($idx === false) {
            return;
        }

        $heatmap = Cache::get(self::HEATMAP_KEY, []);
        $curr = $heatmap[$bb][$ab][$idx] ?? $latencyMs;
        $heatmap[$bb][$ab][$idx] = (0.2 * $latencyMs) + (0.8 * $curr);
        Cache::put(self::HEATMAP_KEY, $heatmap, 3600);
    }

    private function getLegacyStrategy(string $id): string
    {
        $key = self::CACHE_KEY_PREFIX.$id;
        $state = Cache::get($key.':state', []);
        $idx = $state['index'] ?? 0;

        return $this->strategies[$idx] ?? $this->strategies[0];
    }

    private function updateLegacyState(string $id, float $lat): void
    {
        if ($lat > 250) {
            $key = self::CACHE_KEY_PREFIX.$id;
            $state = Cache::get($key.':state', []);
            if (time() < ($state['pinned_until'] ?? 0)) {
                return;
            }
            $state['index'] = (($state['index'] ?? 0) + 1) % 3;
            $state['pinned_until'] = time() + 600;
            Cache::put($key.':state', $state, 3600);
        }
    }
}
