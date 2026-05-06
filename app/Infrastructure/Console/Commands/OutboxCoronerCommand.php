<?php

declare(strict_types=1);

namespace App\Infrastructure\Console\Commands;

use App\Infrastructure\Models\OutboxMessage;
use App\Infrastructure\Monitoring\ConsensusHealthGovernor;
use Illuminate\Console\Command;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

/**
 * OutboxCoronerCommand - Level 62 Topological Transcendence
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  TRUE GAUSSIAN JITTER: Box-Muller Transform for Bell Curve Distribution  ║
 * ║  THE ENTROPY PROTOCOL: Seed = Hostname + HrTime + RandomBytes            ║
 * ║  STOCHASTIC QUOTA: Q = Limit / ((Fleet * 1.25) + GaussianJitter)         ║
 * ║  THERMAL COMPLIANCE: Caps throughput at 10% during Governor Calibration  ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class OutboxCoronerCommand extends Command
{
    protected $signature = 'infrastructure:outbox-coroner {--limit=10}';

    protected $description = 'Perform forensic analysis and kinetic recovery (Level 62 Topological Transcendence)';

    private const KP = 2.0;

    private const KI = 0.5;

    private const KD = 1.0;

    private const PID_CACHE_KEY = 'outbox:coroner:pid_state';

    private const FLEET_STATE_KEY = 'outbox:fleet:active_count';

    private const DB_IO_LIMIT = 1000;

    // Level 62 Strict Constants
    private const SAFETY_VARIANCE_SIGMA = 1.25;

    private const MAX_FLEET_SIZE_FALLBACK = 100;

    private const SIGNAL_STALE_THRESHOLD_S = 5;

    // Box-Muller Constants
    private const JITTER_WINDOW_MS = 500;

    // Sovereign Identity
    private string $sovereignSeed;

    public function __construct()
    {
        parent::__construct();
        // The Entropy Protocol: High-Entropy Instance Seed
        // SHA-256 of Hostname + HrTime + RandomBytes
        $this->sovereignSeed = hash('sha256', gethostname().hrtime(true).bin2hex(random_bytes(16)));
    }

    public function handle(Dispatcher $dispatcher, ConsensusHealthGovernor $governor): int
    {
        $limit = (int) $this->option('limit');
        $this->registerFleetPresence();

        $pendingCount = OutboxMessage::where('status', OutboxMessage::STATUS_PENDING_AUTOPSY)->count();
        if ($pendingCount > 50000) {
            $this->handleStormCondition($pendingCount);

            return Command::SUCCESS;
        }

        // Critical: Check Thermal Calibration State
        // Even if no global warning, we must respect the thermal cap if active
        if ($governor->isThermalCalibrationActive()) {
            $this->processThermalCalibration($governor);
        } elseif (! $governor->isGlobalWarningActive()) {
            $this->processKineticReEntry($governor);
        }

        $cadavers = OutboxMessage::where('status', OutboxMessage::STATUS_PENDING_AUTOPSY)
            ->orderBy('failed_at', 'asc')->limit($limit)->get();

        foreach ($cadavers as $message) {
            $this->performAutopsy($message, $dispatcher);
        }

        return Command::SUCCESS;
    }

    private function processThermalCalibration(ConsensusHealthGovernor $governor): void
    {
        // Thermal Cap: 10% of DB_IO_LIMIT
        $cap = (int) (self::DB_IO_LIMIT * 0.10);
        $this->info("CORONER: Thermal Calibration Active. Capping at {$cap} IOPS.");
        $this->executeReEntryBatch($cap);
    }

    private function processKineticReEntry(ConsensusHealthGovernor $governor): void
    {
        $isolatedCount = OutboxMessage::where('last_error', 'LIKE', 'STORM_ISOLATED%')->count();
        if ($isolatedCount === 0) {
            return;
        }

        // Local PID Logic
        $volatility = $governor->getVolatilityIndex();
        $errorSignal = $volatility > 0 ? -1.0 * ($volatility * 2) : 1.0;

        $pidState = Cache::get(self::PID_CACHE_KEY, ['rate' => 5.0, 'integral' => 0.0, 'prev_error' => 0.0]);

        $isSaturatedMax = ($pidState['rate'] >= self::DB_IO_LIMIT);
        $isSaturatedMin = ($pidState['rate'] <= 1.0);
        $shouldIntegrate = ! ($isSaturatedMax && $errorSignal > 0) && ! ($isSaturatedMin && $errorSignal < 0);

        if ($shouldIntegrate) {
            $pidState['integral'] += $errorSignal;
        }

        $mutingFactor = 1.0 / (1.0 + $volatility);
        $delta = ((self::KP * $mutingFactor) * $errorSignal) +
                 (self::KI * $pidState['integral']) +
                 ((self::KD * $mutingFactor) * ($errorSignal - $pidState['prev_error']));

        $localRate = max(1.0, min(self::DB_IO_LIMIT, $pidState['rate'] + $delta));
        Cache::put(self::PID_CACHE_KEY, ['rate' => $localRate, 'integral' => $pidState['integral'], 'prev_error' => $errorSignal], 3600);

        // LEVEL 62: True Gaussian Jitter (Box-Muller)
        $fleetSize = $this->getFleetSize();

        // Generate Box-Muller Delay
        $delayMs = $this->calculateBoxMullerDelay();
        $jitterFactor = $delayMs / 1000.0;

        // Stochastic Quota Formula
        $denominator = ($fleetSize * self::SAFETY_VARIANCE_SIGMA) + $jitterFactor;
        $globalQuota = self::DB_IO_LIMIT / $denominator;

        $finalRate = min($localRate, $globalQuota);

        // Re-check thermal cap just in case (though handle() splits paths)
        if ($governor->isThermalCalibrationActive()) {
            $finalRate = min($finalRate, self::DB_IO_LIMIT * 0.10);
        }

        // Apply Physical Gaussian Delay for De-Synchronization
        if ($isolatedCount > 0 && $delayMs > 0) {
            usleep((int) ($delayMs * 1000));
        }

        $batchSize = (int) round($finalRate);
        $this->info("CORONER: Transcendence Rate: {$batchSize} (Local:".round($localRate, 1).', Global:'.round($globalQuota, 1).", Fleet:{$fleetSize}) [Delay:{$delayMs}ms]");

        $this->executeReEntryBatch($batchSize);
    }

    private function calculateBoxMullerDelay(): float
    {
        $hash = hash('sha256', $this->sovereignSeed.microtime());
        $u1 = (hexdec(substr($hash, 0, 8)) / 0xFFFFFFFF);
        $u2 = (hexdec(substr($hash, 8, 8)) / 0xFFFFFFFF);
        $u1 = max($u1, 1e-10);

        $z = sqrt(-2.0 * log($u1)) * cos(2.0 * M_PI * $u2);

        $mu = self::JITTER_WINDOW_MS / 2.0;
        $sigma = self::JITTER_WINDOW_MS / 6.0;

        $delay = $mu + ($z * $sigma);

        return max(0.0, min((float) self::JITTER_WINDOW_MS, $delay));
    }

    private function getFleetSize(): int
    {
        try {
            $count = Redis::zcount(self::FLEET_STATE_KEY, time() - self::SIGNAL_STALE_THRESHOLD_S, '+inf');

            return max(1, $count);
        } catch (\Throwable $e) {
            return self::MAX_FLEET_SIZE_FALLBACK;
        }
    }

    private function registerFleetPresence(): void
    {
        try {
            Redis::zadd(self::FLEET_STATE_KEY, time(), $this->sovereignSeed);
            Redis::expire(self::FLEET_STATE_KEY, 30);
        } catch (\Throwable $e) {
        }
    }

    private function executeReEntryBatch(int $batchSize): void
    {
        if ($batchSize < 1) {
            return;
        }
        OutboxMessage::where('last_error', 'LIKE', 'STORM_ISOLATED%')
            ->orderBy('failed_at', 'asc')->limit($batchSize)
            ->update(['status' => OutboxMessage::STATUS_PENDING, 'last_error' => null, 'failed_at' => null, 'attempts' => 0]);
    }

    private function handleStormCondition(int $pendingCount): void
    {
        OutboxMessage::where('status', OutboxMessage::STATUS_PENDING_AUTOPSY)
            ->limit(500)->update(['status' => OutboxMessage::STATUS_QUARANTINED, 'last_error' => 'STORM_ISOLATED']);
    }

    private function performAutopsy(OutboxMessage $message, Dispatcher $dispatcher): void
    {
        $message->update(['status' => OutboxMessage::STATUS_QUARANTINED]);
    }
}
