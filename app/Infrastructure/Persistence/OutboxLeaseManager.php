<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * OutboxLeaseManager - Level 67 (Sharded Sovereignty)
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  SOVEREIGN: Workers acquire exclusive ownership of data partitions       ║
 * ║  CONFIGURABLE: Partition count loaded from config/outbox.php             ║
 * ║  SELF-HEALING: Expired leases are automatically adopted by other workers ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class OutboxLeaseManager
{
    private const TABLE = 'outbox_shards';
    
    // LEVEL 67: Configuration-driven values
    private readonly int $leaseDurationSeconds;
    private readonly int $heartbeatIntervalSeconds;
    private readonly int $partitionCount;

    private readonly string $workerId;
    
    /** @var array<int> Shards currently owned by this worker */
    private array $ownedShards = [];

    public function __construct()
    {
        // LEVEL 67: Configuration Injection (Dynamic Scaling)
        // LEVEL 73: Configurable TTL (Physics Constant Removal)
        $this->leaseDurationSeconds = (int) config('outbox.leasing.ttl_seconds', 60); 
        $this->heartbeatIntervalSeconds = (int) config('outbox.leasing.heartbeat_interval_seconds', 30);
        $this->partitionCount = (int) config('outbox.distribution.partition_count', 64);
        
        // Generate unique worker ID for this process
        $this->workerId = gethostname() . ':' . getmypid() . ':' . Str::random(8);
    }

    /**
     * Get this worker's unique identifier.
     */
    public function getWorkerId(): string
    {
        return $this->workerId;
    }

    /**
     * Acquire available shards up to the specified limit.
     * 
     * @param int $maxShards Maximum number of shards to acquire
     * @return array<int> Array of acquired shard IDs
     */
    public function acquireShards(int $maxShards = 4): array
    {
        // LEVEL 48: Acquisition Jitter - desynchronize workers
        $this->applyJitter();
        
        $now = now();
        $expiresAt = $now->copy()->addSeconds($this->leaseDurationSeconds);
        
        // LEVEL 49: O(1) SEED-BASED OFFSET SELECTION
        // Instead of inRandomOrder() which is O(N log N), we use a deterministic
        // seed derived from WorkerID + daily salt to calculate a starting offset.
        // This ensures distributed spread without expensive sorting.
        $seed = $this->calculateShardSeed();
        // LEVEL 67: DYNAMIC SCALABILITY (Config Injection)
        $totalShards = $this->partitionCount; 
        $startOffset = $seed % $totalShards;
        
        // LEVEL 49: Wrap entire acquire in transaction for atomicity
        return DB::transaction(function () use ($now, $expiresAt, $maxShards, $startOffset, $totalShards) {
            // Try from startOffset to end, then wrap around from 0
            // This creates a deterministic but distributed selection pattern
            $availableShards = DB::table(self::TABLE)
                ->where(function ($q) use ($now) {
                    $q->whereNull('owner_worker_id')
                      ->orWhere('expires_at', '<', $now);
                })
                ->where('shard_id', '>=', $startOffset)
                ->orderBy('shard_id', 'asc') // O(1) with primary key index
                ->limit($maxShards)
                ->lockForUpdate()
                ->skipLocked()
                ->pluck('shard_id')
                ->toArray();
            
            // If we didn't get enough, wrap around to beginning
            if (count($availableShards) < $maxShards) {
                $remaining = $maxShards - count($availableShards);
                $wrapShards = DB::table(self::TABLE)
                    ->where(function ($q) use ($now) {
                        $q->whereNull('owner_worker_id')
                          ->orWhere('expires_at', '<', $now);
                    })
                    ->where('shard_id', '<', $startOffset)
                    ->whereNotIn('shard_id', $availableShards)
                    ->orderBy('shard_id', 'asc')
                    ->limit($remaining)
                    ->lockForUpdate()
                    ->skipLocked()
                    ->pluck('shard_id')
                    ->toArray();
                
                $availableShards = array_merge($availableShards, $wrapShards);
            }

            if (empty($availableShards)) {
                return [];
            }

            // Attempt to claim them atomically (inside same transaction)
            $claimed = DB::table(self::TABLE)
                ->whereIn('shard_id', $availableShards)
                ->where(function ($q) use ($now) {
                    $q->whereNull('owner_worker_id')
                      ->orWhere('expires_at', '<', $now);
                })
                ->update([
                    'owner_worker_id' => $this->workerId,
                    'expires_at' => $expiresAt,
                    'acquired_at' => $now,
                    'messages_processed' => 0,
                    'updated_at' => $now,
                ]);

            if ($claimed > 0) {
                // Fetch the actually claimed shards
                $this->ownedShards = DB::table(self::TABLE)
                    ->where('owner_worker_id', $this->workerId)
                    ->pluck('shard_id')
                    ->toArray();

                Log::info('SHARD LEASE ACQUIRED', [
                    'worker_id' => $this->workerId,
                    'shards' => $this->ownedShards,
                    'expires_at' => $expiresAt->toIso8601String(),
                    'seed_offset' => $startOffset,
                ]);
            }

            return $this->ownedShards;
        });
    }

    /**
     * Renew leases on owned shards (heartbeat).
     * Must be called every HEARTBEAT_INTERVAL_SECONDS.
     */
    public function renewLeases(): bool
    {
        if (empty($this->ownedShards)) {
            return false;
        }

        $now = now();
        $expiresAt = $now->copy()->addSeconds($this->leaseDurationSeconds);

        $renewed = DB::table(self::TABLE)
            ->whereIn('shard_id', $this->ownedShards)
            ->where('owner_worker_id', $this->workerId)
            ->update([
                'expires_at' => $expiresAt,
                'updated_at' => $now,
            ]);

        if ($renewed === 0) {
            // We lost our leases (someone else took them)
            Log::warning('SHARD LEASE LOST', [
                'worker_id' => $this->workerId,
                'shards' => $this->ownedShards,
            ]);
            $this->ownedShards = [];
            return false;
        }

        return true;
    }

    /**
     * Release all owned shards (graceful shutdown).
     */
    public function releaseShards(): void
    {
        if (empty($this->ownedShards)) {
            return;
        }

        DB::table(self::TABLE)
            ->whereIn('shard_id', $this->ownedShards)
            ->where('owner_worker_id', $this->workerId)
            ->update([
                'owner_worker_id' => null,
                'expires_at' => null,
                'updated_at' => now(),
            ]);

        Log::info('SHARD LEASE RELEASED', [
            'worker_id' => $this->workerId,
            'shards' => $this->ownedShards,
        ]);

        $this->ownedShards = [];
    }

    /**
     * Get currently owned shards.
     * 
     * @return array<int>
     */
    public function getOwnedShards(): array
    {
        return $this->ownedShards;
    }

    /**
     * LEVEL 65: Force clear local state (The Purge).
     * Clears internal memory of owned shards without attempting DB release.
     * Used when DB connection is dead and we are resetting the worker state.
     */
    public function forceClearLocalState(): void
    {
        $this->ownedShards = [];
        Log::info('SHARD LEASE: Local state forced clear (Zombie Purge).');
    }

    /**
     * Increment message counter for a shard.
     */
    public function recordProcessed(int $shardId): void
    {
        DB::table(self::TABLE)
            ->where('shard_id', $shardId)
            ->where('owner_worker_id', $this->workerId)
            ->increment('messages_processed');
    }

    /**
     * Get cluster health status.
     */
    public function getClusterStatus(): array
    {
        $now = now();
        
        $shards = DB::table(self::TABLE)->get();
        
        $active = 0;
        $expired = 0;
        $available = 0;
        
        foreach ($shards as $shard) {
            if ($shard->owner_worker_id === null) {
                $available++;
            } elseif ($shard->expires_at < $now) {
                $expired++;
            } else {
                $active++;
            }
        }

        return [
            'total_shards' => count($shards),
            'active_leases' => $active,
            'expired_leases' => $expired,
            'available_shards' => $available,
            'this_worker' => [
                'id' => $this->workerId,
                'owned_shards' => $this->ownedShards,
            ],
        ];
    }

    /**
     * LEVEL 50: Calculate deterministic shard seed for O(1) selection.
     * Uses worker ID hash + worker-instance salt (persisted at boot).
     */
    private function calculateShardSeed(): int
    {
        // LEVEL 50: Worker-Instance-Salt generated at boot, persisted in cache
        $instanceSalt = $this->getInstanceSalt();
        $seedInput = $this->workerId . ':' . $instanceSalt;
        
        return abs(crc32($seedInput));
    }

    /**
     * LEVEL 50: Get or create worker instance salt.
     * Persisted in cache for process lifetime, regenerated on restart.
     */
    private function getInstanceSalt(): string
    {
        $cacheKey = 'outbox:worker_salt:' . $this->workerId;
        
        return \Illuminate\Support\Facades\Cache::remember($cacheKey, 3600, function () {
            return Str::random(16);
        });
    }

    /**
     * LEVEL 48: Apply jitter to desynchronize workers.
     * Prevents all workers from hitting the database at the exact same millisecond.
     */
    private function applyJitter(): void
    {
        // Calculate ±15% of heartbeat interval (30s * 0.15 = 4.5s max jitter)
        $jitterRange = (int) (self::HEARTBEAT_INTERVAL_SECONDS * 0.15 * 1000); // milliseconds
        $jitter = random_int(0, $jitterRange);
        
        usleep($jitter * 1000); // Convert to microseconds
    }
}
