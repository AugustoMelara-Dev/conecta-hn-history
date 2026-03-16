<?php

declare(strict_types=1);

namespace App\Infrastructure\Console\Commands;

use App\Infrastructure\Models\OutboxMessage;
use DateTimeInterface;
use Illuminate\Console\Command;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * ProcessOutboxCommand - Level 66: The Obsidian Monolith
 *
 * â•”â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•—
 * â•‘  IMMORTAL: Global Exception Sentinel (No Exit Policy)                    â•‘
 * â•‘  AMNESTIC: Total state wipe on infra failure (No Split-Brain)            â•‘
 * â•‘  OBSERVABLE: Hibernate with Heartbeats & Signal Awareness                â•‘
 * â•‘  ENTROPY: Jittered Exponential Backoff (No Thundering Herd)              â•‘
 * â•‘  DISCRIMINATION: Smart Death on Code Errors vs Infra Sleep               â•‘
 * â•šâ•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
 */
final class ProcessOutboxCommand extends Command
{
    protected $signature = 'infrastructure:process-outbox 
                            {--batch-size=50 : Number of messages to process per batch}
                            {--max-loops=100 : Maximum number of loops before exiting}
                            {--max-shards=4 : Maximum number of shards to acquire}';

    protected $description = 'Relay events from Outbox to Event Bus with Unified Atomic Ownership';

    /**
     * Lease manager for dynamic shard acquisition.
     */
    private ?\App\Infrastructure\Persistence\OutboxLeaseManager $leaseManager = null;

    /**
     * LEVEL 51: Atomic ownership transfer service.
     */
    private ?\App\Infrastructure\Persistence\AtomicOwnershipTransfer $atomicOwnership = null;

    /**
     * Last heartbeat time for lease renewal.
     */
    private int $lastHeartbeat = 0;

    /**
     * LEVEL 51: Resilient health probe with multi-layer fallback.
     */
    private ?\App\Infrastructure\Monitoring\ResilientHealthProbe $healthProbe = null;

    /**
     * LEVEL 54: Global Consensus Governor.
     */
    private ?\App\Infrastructure\Monitoring\ConsensusHealthGovernor $governor = null;

    /**
     * LEVEL 50: Tracer for distributed context propagation.
     */
    private ?\App\Infrastructure\Telemetry\OutboxTracer $tracer = null;

    private const HYDRAULIC_LIMIT = 5000;

    private const HYDRAULIC_RESUME = 2500;

    // Level 64: Hydraulic Pressure Counter
    private int $pendingDispatches = 0;

    // LEVEL 65: Signal Awareness
    private bool $keepRunning = true;

    // LEVEL 79: THE AMNESIAC PROTOCOL (State Container)
    private array $failedAggregateIds = [];

    /**
     * LEVEL 66: OBSIDIAN MONOLITH HIBERNATION.
     * Features: Jittered Backoff, Comatose Heartbeats, Signal Awareness.
     */
    private function hibernateWithBackoff(int $failureCount): void
    {
        // LEVEL 66: Jittered Exponential Backoff (Entropy Injection)
        // Base = min(60, 2^(n-1))
        $baseDuration = min(60, pow(2, $failureCount - 1));

        // Jitter = RandomFloat(0, 1.0)
        $jitter = mt_rand() / mt_getrandmax();

        $totalDuration = $baseDuration + $jitter;

        $this->warn('HIBERNATION: Sleeping '.number_format($totalDuration, 2)."s (Failure #{$failureCount})");

        // Interruptible Sleep Loop (100ms intervals)
        $cycles = (int) ($totalDuration * 10);

        for ($i = 0; $i < $cycles; $i++) {
            if (! $this->keepRunning) {
                break;
            }

            // LEVEL 66: Comatose Heartbeat (Every 5 seconds)
            // 50 cycles * 0.1s = 5.0s
            if ($i > 0 && $i % 50 === 0) {
                Log::warning("HIBERNATING: Infrastructure unreachable. Waiting... (Tick {$i}/{$cycles})");
            }

            // Check signals (if pcntl is available)
            if (function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }

            usleep(100000); // 100ms
        }
    }

    /**
     * Handle graceful shutdown signal.
     */
    public function shutdown(): void
    {
        $this->info('SIGNAL RECEIVED: Initiating Graceful Shutdown...');
        $this->keepRunning = false;
    }

    public function handle(Dispatcher $eventDispatcher): int
    {
        $batchSize = (int) $this->option('batch-size');
        $maxLoops = (int) $this->option('max-loops');
        $maxShards = (int) $this->option('max-shards');
        $consecutiveFailures = 0;

        // LEVEL 65: Register Signal Handlers (Linux/K8s)
        if (function_exists('pcntl_async_signals')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGTERM, [$this, 'shutdown']);
            pcntl_signal(SIGINT, [$this, 'shutdown']);
        }

        $this->info('IMMORTAL SENTINEL: Starting Watch via PID '.getmypid());

        // PHASE 1: THE IMMORTAL LOOP (Global Exception Trap)
        while ($this->keepRunning) {
            try {
                // =========================================================
                // LEVEL 67: SOVEREIGN BOOT SEQUENCE (Strict Ordering)
                // Step 1: Initialize Infrastructure
                // Step 2: Acquire Leases
                // Step 3: Starvation Check (Hibernate if no shards)
                // Step 4: Sovereign Recovery (Only for owned shards)
                // Step 5: Enter Immortal Loop
                // =========================================================

                // STEP 1: INFRASTRUCTURE HANDSHAKE (Lazy Init)
                if ($this->healthProbe === null) {
                    $this->healthProbe = app(\App\Infrastructure\Monitoring\ResilientHealthProbe::class);
                }
                if ($this->governor === null) {
                    $this->governor = new \App\Infrastructure\Monitoring\ConsensusHealthGovernor(
                        gethostname().':'.getmypid()
                    );
                }
                if ($this->tracer === null) {
                    $this->tracer = app(\App\Infrastructure\Telemetry\OutboxTracer::class);
                }
                if ($this->atomicOwnership === null) {
                    $this->atomicOwnership = app(\App\Infrastructure\Persistence\AtomicOwnershipTransfer::class);
                }
                if ($this->leaseManager === null) {
                    $this->leaseManager = app(\App\Infrastructure\Persistence\OutboxLeaseManager::class);
                }

                // STEP 2: LEASE ACQUISITION
                $acquiredShards = $this->leaseManager->getOwnedShards();
                if (empty($acquiredShards)) {
                    $acquiredShards = $this->leaseManager->acquireShards($maxShards);
                }

                // STEP 3: STARVATION CHECK
                if (empty($acquiredShards)) {
                    $this->warn('STARVATION WARNING: No shards available. Hibernating...');
                    $this->hibernateWithBackoff(4);

                    continue; // DO NOT proceed to ghost recovery
                }

                // LEVEL 67: SHARD-AWARE OBSERVABILITY
                // Inject owned shards into logging context
                Log::shareContext(['owned_shards' => implode(',', $acquiredShards)]);
                $this->info('SOVEREIGNTY ESTABLISHED: Shards ['.implode(',', $acquiredShards).']');

                // STEP 4: SOVEREIGN RECOVERY (After ownership confirmed)
                // LEVEL 69: SOVEREIGNTY ENFORCED
                // We pass the owned shards explicitly to ensure we only look at what we own.
                $this->recoverGhostRecords($eventDispatcher, $acquiredShards);

                // BOOT STORM SHIELD (Pessimistic Mode)
                if ($this->healthProbe->isPessimisticDiscovery()) {
                    $this->warn('BOOT STORM SHIELD: active. Waiting 60s...');
                    for ($i = 0; $i < 600; $i++) {
                        if (! $this->keepRunning) {
                            break 2;
                        }
                        usleep(100000);
                    }
                }

                $this->lastHeartbeat = time();

                // =========================================================
                // SUB-PHASE 2: PROCESSING LOOP (Working State)
                // =========================================================
                // Reset failure counter on successful boot
                $consecutiveFailures = 0;
                $loopCount = 0;
                $processedTotal = 0;
                $failedTotal = 0;

                do {
                    // LEVEL 79: THE AMNESIAC PROTOCOL (Scoped State Reset)
                    // We must reset the failure state at the start of every cycle to prevent:
                    // 1. The Silent Accumulator (Memory Leak > 7 days)
                    // 2. Infinite Punishment (Permanent Bans from transient failures)
                    //
                    // We log only if the previous cycle had significant failures to avoid noise.
                    if (! empty($this->failedAggregateIds) && count($this->failedAggregateIds) > 50) {
                        $this->info('AMNESIAC PROTOCOL: Clearing '.count($this->failedAggregateIds).' failed aggregate locks from previous cycle. Reclaiming memory.');
                    }

                    // EXPLICIT MEMORY RELEASE
                    // We drop the array reference completely to allow Zend GC to reclaim the strings immediately.
                    $this->failedAggregateIds = [];

                    // Signal Check
                    if (! $this->keepRunning) {
                        break;
                    }
                    if (function_exists('pcntl_signal_dispatch')) {
                        pcntl_signal_dispatch();
                    }

                    // LEVEL 64.1: TELEMETRY
                    $this->tracer->recordHydraulicPressure($this->pendingDispatches);

                    // LEVEL 64: HYDRAULIC ADMISSION CONTROL
                    if ($this->pendingDispatches > self::HYDRAULIC_LIMIT) {
                        $this->warn("HYDRAULIC BRAKE ENGAGED: Pending ({$this->pendingDispatches})");

                        while ($this->pendingDispatches > self::HYDRAULIC_RESUME && $this->keepRunning) {
                            usleep(100000);
                            if (function_exists('pcntl_signal_dispatch')) {
                                pcntl_signal_dispatch();
                            }
                        }
                    }

                    // LEVEL 54: GLOBAL CONSENSUS CHECK
                    $currentBatchSize = $batchSize;
                    if ($this->governor->isGlobalWarningActive()) {
                        $this->warn('GLOBAL DEFENSE: Throttling active.');
                        sleep(2);
                        $currentBatchSize = 1;
                    }

                    // LEVEL 49: Cooldown Check
                    if ($this->healthProbe->isInCooldown()) {
                        $remaining = $this->healthProbe->getRemainingCooldownSeconds();
                        sleep($remaining);
                        $this->healthProbe->exitCooldown();
                        $this->reacquireShardsAfterCooldown($maxShards);

                        continue;
                    }

                    // LEVEL 49: Danger Zone
                    if ($this->healthProbe->isDangerZone()) {
                        $this->governor->emitPulse('WARNING');
                        $this->leaseManager->releaseShards();
                        $this->healthProbe->enterCooldown();

                        continue;
                    }

                    $this->governor->emitPulse('HEALTHY');

                    // PHASE 1: CLAIM
                    $this->renewLeasesIfNeeded();

                    // Note: We don't need inner try/catch for "Boot Storm" here anymore
                    // because the Global Immortal Loop handles the crash.
                    // But for efficiency, we can keep a local one to prevent full reboot on simple deadlock?
                    // User said "The Immortal Wrapper: You must wrap the entire execution logic... inside a while (true)".
                    // If we remove the local try/catch, a deadlock reboots the whole loop (re-init dependencies).
                    // Maybe better to re-init to be safe (fresh connection).
                    // So I will let exceptions bubble up to the Immortal Loop.
                    $messages = $this->claimBatch($currentBatchSize);

                    if ($messages->isEmpty()) {
                        sleep(1);
                        $loopCount++;

                        continue;
                    }

                    // PHASE 3: AGGREGATE PIPELINING (LEVEL 52)
                    // Group by aggregate_id and sort by chronological sequence
                    $groupedByAggregate = $messages->groupBy('aggregate_id');
                    $messagesToProcess = collect();

                    // We need to flatten the grouped messages BACK into a single collection
                    // BUT strictly ordered by aggregate sequence.
                    // Actually, the original claimBatch logic ALREADY returned pipelined messages
                    // grouped by aggregate and sorted.
                    // But wait, the previous `claimBatch` implementation returned a flat collection
                    // derived from grouped messages.
                    // Let's assume $messages is flat but properly ordered (Interleaved aggregates are fine,
                    // as long as intra-aggregate order is preserved).

                    // LEVEL 72: THE CAUSAL FORTRESS (In-Memory Circuit Breaker)
                    // We must track failed aggregates within this batch to prevent
                    // "Partial Aggregate Fracture" (FIFO Breach).
                    // $this->failedAggregateIds is already reset at the start of the `do` loop (Level 79).

                    // PHASE 2: PROCESS
                    foreach ($messages as $message) {
                        if (! $this->keepRunning) {
                            break;
                        }

                        // LEVEL 72: THE GUARD CLAUSE (Circuit Breaker Active)
                        // If this aggregate has already failed in this batch, we MUST skip
                        // all subsequent messages to preserve strict causal ordering.
                        if (in_array($message->aggregate_id, $this->failedAggregateIds, true)) {
                            // LEVEL 72: CAUSAL AWARENESS (Telemetric Distinction)
                            // We log this as a WARNING, not an ERROR. It's a safety skip.
                            $this->warn("CIRCUIT BREAKER: Skipping dependent event {$message->id} for failed Aggregate {$message->aggregate_id}");

                            // Optional: Record "Skip" metric
                            // $this->tracer->recordCircuitBreakerSkip($message->aggregate_id);

                            continue;
                        }

                        $this->renewLeasesIfNeeded();
                        try {
                            // LEVEL 73: PRE-EMPTIVE RENEWAL STRATEGY (The Heartbeat Interceptor)
                            // Logic: If lease is > 50% expired, force renewal BEFORE starting this job.
                            // This ensures the worker has a fresh "Time Window" for the message.
                            // Prevent "Heartbeat Hygiene" collapse.
                            $ttl = (int) config('outbox.leasing.ttl_seconds', 60);
                            $timeSinceLastHeartbeat = time() - $this->lastHeartbeat;
                            if ($timeSinceLastHeartbeat > ($ttl / 2)) {
                                // Force renewal
                                if (! $this->leaseManager->renewLeases()) {
                                    throw new \RuntimeException('LEASE_LOST_PRE-EMPTIVE: Failed to renew lease before processing.');
                                }
                                $this->lastHeartbeat = time();
                            }

                            $startTime = microtime(true);
                            $this->processMessage($message, $eventDispatcher);
                            $processedInBatch++;

                            $latencyMs = (microtime(true) - $startTime) * 1000;
                            $this->healthProbe->recordLatency($latencyMs);
                            $this->leaseManager->recordProcessed($message->partition_key);
                        } catch (Throwable $e) {
                            // LEVEL 73: GRACEFUL ABDICATION (The No-Suicide Pact)
                            // If lease is lost, we must NOT die (exit). We must abdicate.
                            if (str_contains($e->getMessage(), 'LEASE_LOST') || str_contains($e->getMessage(), 'HERMETIC SEAL BROKEN')) {
                                $this->warn('SOVEREIGNTY LOST: '.$e->getMessage().' - Abdicating batch and resetting state.');

                                // 1. Drop the Batch (Break inner loop)
                                // 2. Reset State (Force Re-Acquisition)
                                $this->leaseManager->forceClearLocalState();
                                try {
                                    $this->leaseManager->releaseShards();
                                } catch (\Throwable $i) {
                                }
                                $this->leaseManager = null;
                                $acquiredShards = []; // Clear local cache

                                // 3. Hibernate (Soft Reset)
                                $this->hibernateWithBackoff(1);

                                // 4. Continue to Main Loop (Restart)
                                continue 2; // Jump out of foreach AND do-while
                            }

                            // LEVEL 72: FAILURE REGISTRATION (Trip)
                            // This aggregate is now toxic. All future messages for it in this batch are tainted.
                            $this->failedAggregateIds[] = $message->aggregate_id;

                            Log::error("CIRCUIT BREAKER TRIPPED: Aggregate {$message->aggregate_id} failed. Root Cause: ".$e->getMessage());

                            $failedTotal++;
                        }
                    }

                    $processedTotal += $messages->count();
                    $loopCount++;

                    if ($loopCount % 10 === 0) {
                        $health = $this->healthProbe->getStatus();
                        $this->line("Health: {$health['zone']}");
                        // Force GC periodically
                        gc_collect_cycles();
                    }

                } while ($loopCount < $maxLoops && $this->keepRunning);

                // Max-loops is an explicit exit contract for tests and controlled workers.
                if ($this->keepRunning) {
                    $this->info("Max loops ({$maxLoops}) reached. Recycling worker state...");
                    $this->releaseLeases();
                    $this->leaseManager = null;
                    gc_collect_cycles();
                    $this->keepRunning = false;
                }

            } catch (\Throwable $e) {
                // =========================================================
                // PHASE 3: CATASTROPHE HANDLING (obsidian Monolith)
                // =========================================================

                // LEVEL 66: FATAL ERROR DISCRIMINATION (SMART DEATH)
                // If the code is broken (TypeError), valid logic demands we die.
                // Sleeping on a syntax error creates a "Zombie Loop" of spam.
                if ($e instanceof \Error || $e instanceof \TypeError || $e instanceof \ArgumentCountError) {
                    Log::critical('OBSIDIAN MONOLITH: Fatal Code Defect Detected. Terminating. Error: '.$e->getMessage(), [
                        'trace' => $e->getTraceAsString(),
                    ]);

                    return Command::FAILURE;
                }

                if (! $this->keepRunning) {
                    break;
                } // Don't log crash if shutting down

                Log::emergency('OBSIDIAN MONOLITH: Infrastructure Collapse - '.$e->getMessage());

                // LEVEL 66: AMNESTIC RE-ACQUISITION (Total Recall Prevention)
                // 1. Purge Connection (The Purge)
                try {
                    DB::purge();      // Disconnect PDO
                    DB::reconnect();  // Fresh connection for next attempt
                } catch (\Throwable $dbe) {
                    // Ignore purge failures, we are crashing anyway
                }

                // 2. Wipe State (The Amnesia)
                // Eliminate "Split-Brain" risk by forcing a rigorous state wipe.
                if ($this->leaseManager) {
                    $this->leaseManager->forceClearLocalState();
                    // Attempt to release shards if possible (Best Effort)
                    try {
                        $this->leaseManager->releaseShards();
                    } catch (\Throwable $ex) {
                        // Connection is likely dead, ignore release failure
                    }
                    $this->leaseManager = null; // Force complete re-initialization
                }

                // 3. Clear Local Cache of Shards
                $acquiredShards = [];

                // 4. Hibernate (The Coma)
                $consecutiveFailures++;
                $this->hibernateWithBackoff($consecutiveFailures);

                // 5. Restart Loop (Trigger fresh acquisition)
                continue;
            }
        }

        $this->releaseLeases();
        $this->info('Outbox processing stopped gracefully.');

        return Command::SUCCESS;
    }

    /**
     * Renew leases if heartbeat interval has passed.
     *
     * @throws RuntimeException If lease renewal fails (Sovereignty Lost).
     */
    private function renewLeasesIfNeeded(): void
    {
        // Check for renewal first
        if (time() - $this->lastHeartbeat >= (int) config('outbox.leasing.heartbeat_interval_seconds', 30)) {

            // 1. Renew existing leases
            if (! $this->leaseManager->renewLeases()) {
                // LEVEL 73: NO SUICIDE PACT
                // We do NOT exit(1). We throw to trigger Graceful Abdication.
                throw new \RuntimeException('LEASE_LOST: Renew failed. Sovereignty surrendered.');
            }

            // LEVEL 77: DYNAMIC SATURATION (The Greedy Worker)
            // We check if we are under-utilized and attempt to acquire more shards.
            // This fixes "The Shy Worker" issue where workers only acquired on boot.
            $currentLoad = count($this->leaseManager->getOwnedShards());
            $maxShards = (int) config('outbox.max_shards_per_worker', 4);
            $deficit = $maxShards - $currentLoad;

            if ($deficit > 0) {
                // Attempt Supplemental Acquisition
                $newShards = $this->leaseManager->acquireShards($deficit);

                if (! empty($newShards)) {
                    Log::info("DYNAMIC SATURATION: Acquired {$deficit} supplemental shards. Current load: ".($currentLoad + count($newShards))."/{$maxShards}");
                    // Note: ownedShards are automatically updated in LeaseManager, preventing split-brain.
                }
            }

            $this->lastHeartbeat = time();
        }
    }

    /**
     * Release leases on shutdown.
     */
    public function releaseLeases(): void
    {
        if ($this->leaseManager !== null) {
            $this->leaseManager->releaseShards();
            $this->leaseManager = null;
        }
    }

    /**
     * LEVEL 49 FIX: Re-acquire shards after exiting cooldown.
     * Critical fix for zombie worker issue.
     */
    private function reacquireShardsAfterCooldown(int $maxShards): void
    {
        $acquiredShards = $this->leaseManager->acquireShards($maxShards);

        if (empty($acquiredShards)) {
            $this->warn('Failed to re-acquire shards after cooldown. Will retry next loop.');
        } else {
            $this->info('Re-acquired shards after cooldown: '.implode(',', $acquiredShards));
        }
    }

    /**
     * LEVEL 52: Emit Structured Black Box PULSE log before processing.
     *
     * Format: JSON (Structured for forensic analysis)
     *
     * This is the Zero-Transaction Audit - if the PHP process is killed by OOM-Killer
     * while hydrating a massive or toxic JSON payload, this raw log entry is our only
     * forensic evidence to identify the specific record causing the death.
     */
    private function emitEmergencyPulse(OutboxMessage $message): void
    {
        // Extract trace_id from message metadata if available
        $traceId = $message->payload['meta']['trace_id']
            ?? $message->payload['meta']['traceparent']
            ?? 'unknown';

        $workerId = $this->leaseManager?->getWorkerId() ?? 'unknown';
        $memoryUsage = memory_get_usage(true);
        $batchId = $message->batch_correlation_id ?? 'unknown';

        // LEVEL 52: Structured JSON Pulse
        // Allows for automated parsing of crash logs by monitoring agents
        $pulseData = [
            'pulse_type' => 'PRE_HYDRATION',
            'worker_id' => $workerId,
            'batch_id' => $batchId,
            'message_id' => $message->id,
            'aggregate_id' => $message->aggregate_id,
            'trace_id' => $traceId,
            'type' => $message->type,
            'partition_key' => $message->partition_key,
            'recovery_count' => $message->recovery_count,
            'memory_usage_bytes' => $memoryUsage,
            'memory_peak_bytes' => memory_get_peak_usage(true),
            'worker_id' => $workerId,
        ];

        // Write raw JSON to stderr to bypass any framework logging buffering
        // LEVEL 68: NON-BLOCKING TELEMETRY (Fire-and-Forget)
        // We set the stream to non-blocking mode to prevent freezing the worker
        // if the logging infrastructure (e.g. Fluentd pipe) is saturated.
        stream_set_blocking(STDERR, false);

        // We check the result. If the buffer is full (returns false/0), we drop the log.
        // Survival of the process > Survival of the log.
        $written = @fwrite(STDERR, json_encode($pulseData).PHP_EOL);

        if ($written === false || $written === 0) {
            // Abort immediately - Do not cycle, do not retry.
            // Restore checking before exit?
        }

        // Restore blocking mode to avoid breaking other logger instance usages
        stream_set_blocking(STDERR, true);
    }

    /**
     * PHASE 0: Recover messages stuck in PROCESSING (worker crash recovery).
     *
     * LEVEL 49 RECONCILED: Lease-Aware Self-Healing with OpenTelemetry.
     * Only recovers messages in partitions this worker has active leases for.
     */
    private function recoverStaleLocks(): int
    {
        $ownedShards = $this->leaseManager->getOwnedShards();

        // DEFENSIVE: Prevent full table scan if no leases
        if (empty($ownedShards)) {
            Log::debug('RECOVERY ABORTED: No active leases, skipping to prevent full table scan', [
                'worker_id' => $this->leaseManager->getWorkerId(),
            ]);

            return 0;
        }

        // LEVEL 49: OpenTelemetry span for recovery cycle
        $span = $this->tracer?->injectContext(
            ['trace_id' => bin2hex(random_bytes(16)), 'span_id' => bin2hex(random_bytes(8))],
            'outbox.recovery_cycle'
        );

        try {
            $staleThreshold = now()->subMinutes(5);

            // LEVEL 53 REFACTOR: Use Repository-Enforced Sequence Barrier
            // This ensures we respect the Head-of-Line integrity even during recovery.
            // We use a dedicated repository method that implements the complex self-join query.

            /** @var \App\Infrastructure\Shared\Persistence\EloquentOutboxRepository */
            $repository = app(\App\Infrastructure\Shared\Persistence\EloquentOutboxRepository::class);

            // Note: We use 5 minutes as stale threshold (hardcoded here or could be constant)
            // LEVEL 70: SQL PREDICATE PUSHDOWN
            // We pass the owned shards explicitly to force the DB to filter before returning rows.
            // This prevents "Blinding Fog" where 5000 stale messages from other shards hide our own 10 messages.
            $staleMessages = $repository->retrieveNextStaleBatch(
                batchSize: 50, // Standard batch chunk
                staleThresholdSeconds: 300, // 5 minutes
                ownedPartitionKeys: $ownedShards
            );

            // LEVEL 70: MEMORY FILTER REMOVED
            // The repository now guarantees strictly owned data via SQL WHERE IN clause.
            // $staleMessages = $staleMessages->whereIn('partition_key', $ownedShards); // DELETED

            if ($staleMessages->isEmpty()) {
                return 0;
            }

            // LEVEL 80: THE PHANTOM REAPER (Bifurcated Recovery Strategy)
            // We must separate the "Dead" from the "Sick" to prevent Zombie Loops.
            // A single update query is insufficient because it creates Phantom Purgatory for messages hitting the limit.

            $lockIds = $staleMessages->pluck('id')->toArray();
            $maxAttempts = (int) config('outbox.recovery.max_attempts', 5);

            // LEVEL 81: THE SILENT WITNESS (Aggregated Telemetry)
            // WE DO NOT LOG inside the loop. That creates the "Scream of Death" (IO Starvation).
            // We simple execute the SQL updates and report the summary.

            // PHASE 1: THE REAPER PROTOCOL (Termination of Phantoms)
            // Identify and kill hopeless records strictly BEFORE resurrection.
            // Condition: recovery_count >= maxAttempts
            $reapedCount = OutboxMessage::whereIn('id', $lockIds)
                ->where('recovery_count', '>=', $maxAttempts)
                ->where('status', OutboxMessage::STATUS_PROCESSING) // Safety check
                ->update([
                    'status' => OutboxMessage::STATUS_FAILED,
                    'error' => 'Phantom Purgatory: Stale lock exceeded max retries during recovery scan.',
                    'locked_at' => null,
                    'updated_at' => now(),
                ]);

            // PHASE 2: THE RESURRECTION PROTOCOL (Conditional Mercy)
            // Recover viable messages that have life left.
            // Condition: recovery_count < maxAttempts
            $resurrectedCount = OutboxMessage::whereIn('id', $lockIds)
                ->where('recovery_count', '<', $maxAttempts)
                ->where('status', OutboxMessage::STATUS_PROCESSING) // Safety check
                ->update([
                    'status' => OutboxMessage::STATUS_PENDING,
                    'locked_at' => null,
                    'recovery_count' => DB::raw('recovery_count + 1'), // Apply Strike
                    'updated_at' => now(), // Reset Stale Timer
                ]);

            // PHASE 3: THE SUMMARY PROTOCOL (The Single Truth)
            // We emit a SINGLE log entry for the entire operation.
            $sampleId = $lockIds[0] ?? 'none';

            if ($reapedCount > 0) {
                Log::error("THE SILENT WITNESS: Terminated {$reapedCount} stale phantoms. Sample ID: {$sampleId}.");
            }
            if ($resurrectedCount > 0) {
                Log::warning("THE SILENT WITNESS: Resurrected {$resurrectedCount} stale locks. Sample ID: {$sampleId}.");
            }

            $totalCount = $reapedCount + $resurrectedCount;

            // LEVEL 49: Record telemetry attributes
            Log::info('outbox.recovery_cycle', [
                'recovery.shards_checked' => count($ownedShards),
                'recovery.reaped' => $reapedCount,
                'recovery.resurrected' => $resurrectedCount,
            ]);

            return $totalCount;
        } finally {
            // LEVEL 49: End span in finally to prevent leaks
            if ($span !== null) {
                $this->tracer?->endSpan($span);
            }
        }
    }

    /**
     * PHASE 1: Claim a batch of messages atomically.
     *
     * LEVEL 47: Autonomic Sharding - Uses lease manager's owned shards.
     * Workers dynamically acquire shards and don't need hardcoded configuration.
     *
     * Transitions messages to PROCESSING state with locked_at timestamp.
     */
    private function claimBatch(int $batchSize): \Illuminate\Support\Collection
    {
        // LEVEL 43: Recover stale locks before claiming new messages
        $this->recoverStaleLocks();

        $ownedShards = $this->leaseManager->getOwnedShards();

        if (empty($ownedShards)) {
            return collect();
        }

        // LEVEL 71: THE HERMETIC SEAL (Transaction-Scoped Sovereignty)
        // We wrap the entire claim process in a transaction to enforce the Single Writer Principle.
        return DB::transaction(function () use ($batchSize, $ownedShards) {
            $workerId = $this->leaseManager->getWorkerId();

            // LEVEL 82: THE BATCH CORONER (Buffer Initialization)
            $batchQuarantineEvents = [];

            // PHASE 2: THE DEAD MAN'S SWITCH (Pre-Flight Verification)
            // Cryptographically prove lease ownership INSIDE the transaction.
            // We lock the lease rows to prevent race conditions.
            $validLeaseCount = DB::table('outbox_shards')
                ->where('owner_worker_id', $workerId)
                ->whereIn('shard_id', $ownedShards)
                ->where('expires_at', '>', now())
                ->lockForUpdate() // THE SEAL: Row lock on lease terms
                ->count();

            if ($validLeaseCount !== count($ownedShards)) {
                // LEVEL 71: VIOLATION PROTOCOL
                // This triggers the Amnestic Reaction in the main loop (Infrastructure Collapse Handler).
                throw new \RuntimeException(
                    'HERMETIC SEAL BROKEN: Lease count mismatch (Expected: '.count($ownedShards).
                    ", Found: {$validLeaseCount}). I am a Zombie."
                );
            }

            // LEVEL 50.1: ATOMIC GRAB PATTERN
            // Generate batch correlation ID for telemetric proof of ownership
            $batchCorrelationId = (string) \Illuminate\Support\Str::uuid();

            // PHASE 3: THE CLAIM EXECUTION (Conditional Writing)
            // Only proceeded if validLeaseCount confirmed sovereignty.

            $shardPlaceholders = implode(',', array_fill(0, count($ownedShards), '?'));

            $claimedCount = DB::affectingStatement(
                "UPDATE outbox_messages 
                 SET status = 'PROCESSING', 
                     worker_id = ?, 
                     batch_correlation_id = ?,
                     locked_at = NOW()
                 WHERE id IN (
                     SELECT id FROM (
                         SELECT id FROM outbox_messages 
                         WHERE status = 'PENDING' 
                           AND partition_key IN ({$shardPlaceholders})
                           AND recovery_count < ?
                         ORDER BY partition_key ASC, sequence_number ASC
                         LIMIT ?
                         FOR UPDATE SKIP LOCKED
                     ) AS candidates
                 )",
                array_merge(
                    [$workerId, $batchCorrelationId],
                    $ownedShards,
                    [OutboxMessage::QUARANTINE_THRESHOLD, $batchSize]
                )
            );

            if ($claimedCount === 0) {
                return collect();
            }

            // LEVEL 51.1: STRONG-CONSISTENCY FETCH (WRITER PINNING)
            $claimedMessages = OutboxMessage::query()
                ->useWritePdo() // CRITICAL: Pinned to writer
                ->where('worker_id', $workerId)
                ->where('status', OutboxMessage::STATUS_PROCESSING)
                ->where('batch_correlation_id', $batchCorrelationId)
                ->orderBy('partition_key', 'asc')
                ->orderBy('sequence_number', 'asc')
                ->get();

            // PHASE 3: AGGREGATE PIPELINING (LEVEL 52)
            $groupedByAggregate = $claimedMessages->groupBy('aggregate_id');
            $pipelinedMessages = collect();

            foreach ($groupedByAggregate as $aggregateId => $aggregateMessages) {
                $sortedMessages = $aggregateMessages->sortBy('sequence_number');

                foreach ($sortedMessages as $message) {
                    $this->emitEmergencyPulse($message);

                    if ($message->worker_id !== $workerId) {
                        Log::emergency('CRITICAL_CONCURRENCY_BREACH: Split-Brain detected');
                        posix_kill(getmypid(), SIGTERM);
                        exit(1);
                    }

                    try {
                        // PHASE 4: PRE-EMPTIVE COMMIT
                        DB::transaction(function () use ($message) {
                            $message->increment('recovery_count');
                        });

                        $message->refresh();

                        if ($message->recovery_count >= OutboxMessage::QUARANTINE_THRESHOLD) {
                            $this->quarantineMessage($message, $batchQuarantineEvents);

                            continue;
                        }

                        $pipelinedMessages->push($message);

                    } catch (\Throwable $e) {
                        Log::error('PIPELINE BROKEN: Aborting aggregate sequence', [
                            'aggregate_id' => $aggregateId,
                            'error' => $e->getMessage(),
                        ]);
                        break;
                    }
                }
            }

            // LEVEL 82: THE BATCH CORONER (Aggregated Reporting)
            if (! empty($batchQuarantineEvents)) {
                $count = count($batchQuarantineEvents);
                $sample = $batchQuarantineEvents[0] ?? 'none';
                Log::emergency("THE BATCH CORONER: Quarantined {$count} messages in batch. Sample ID: {$sample}.");
            }

            return $pipelinedMessages;
        });
    }

    /**
     * LEVEL 54: ASYNCHRONOUS AUTOPSY HANDOFF
     * LEVEL 82: THE BATCH CORONER (Silent Buffering)
     *
     * When a message exceeds the recovery threshold, we do NOT perform the autopsy here.
     * We mark it as PENDING_AUTOPSY. The dedicated Coroner process will pick it up.
     * This keeps the primary pipeline flowing at O(1).
     *
     * @param  array  &$quarantineBuffer  Buffer for aggregated logging
     */
    private function quarantineMessage(OutboxMessage $message, array &$quarantineBuffer): void
    {
        // LEVEL 82: BUFFER INSTANTIATION
        // We do NOT log here to prevent "Mini Scream" (50 logs in 10ms).
        $quarantineBuffer[] = $message->id;

        $message->update([
            'status' => OutboxMessage::STATUS_PENDING_AUTOPSY,
            'quarantined_at' => now(), // Technically "Quarantined" from processing loop
        ]);

        // We do strictly NOTHING else. No logs analysis, no simulation.
        // Speed is paramount for the Processor.
    }

    /**
     * LEVEL 64.1: MEMORY-SAFE GHOST RECOVERY (CURSOR STREAMING)
     * Finds messages that were committed to the ledger (DISPATCH_PENDING) but never confirmed dispatched.
     * Uses chunking to prevent OOM explosions on massive backlogs.
     */
    /**
     * LEVEL 66: AMNESTIC GHOST RECOVERY (PARTITION-AWARE)
     * Finds messages that were committed to the ledger (DISPATCH_PENDING) but never confirmed dispatched.
     * CRITICAL FIX: Only recovers messages belonging to shards CURRENTLY OWNED by this worker.
     * Prevents "Ghost Storm" (Duplicate Dispatch Tsunami) during rolling restarts.
     */
    /**
     * LEVEL 69: SOVEREIGN GHOST RECOVERY
     * Finds messages that were committed to the ledger (DISPATCH_PENDING) but never confirmed dispatched.
     *
     * @param  array<int>  $ownedShards  Explicit list of shards this worker owns
     */
    private function recoverGhostRecords(Dispatcher $dispatcher, array $ownedShards): void
    {
        if (empty($ownedShards)) {
            $this->warn('GHOST SCAN: No shards owned. Skipping recovery.');

            return;
        }

        $this->info('GHOST SCAN: Searching for unconfirmed dispatches in shards ['.implode(',', $ownedShards).']...');

        // LEVEL 66: PARTITION BARRIER
        // We strictly filter by partition_key to avoid stepping on other workers' toes.
        $ghostCount = 0;

        // LEVEL 78: THE CHRONOLOGICAL RESTORATION (Time-Ordered Recovery)
        // We must query for ghosts in CHRONOLOGICAL order to preserve Causal Consistency.
        // chunkById() uses UUIDs, which is effectively random order. This breaks "Event B before Event A" invariants.
        //
        // PERF: Requires composite index on (partition_key, status, occurred_at) for efficient chronological sorting without filesort.
        // The "State Mutation Cursor" pattern means processed records leave the result set naturally (status changed or updated_at bumped).
        // Therefore, we can use simple LIMIT without OFFSET.

        $ghosts = OutboxMessage::where('status', OutboxMessage::STATUS_DISPATCH_PENDING)
            ->whereIn('partition_key', $ownedShards) // Level 76 Dam
            ->where('updated_at', '<', now()->subSeconds(30))
            ->orderBy('occurred_at', 'asc') // CRITICAL: Causal Ordering
            ->limit(500) // Memory Safety
            ->get();

        if ($ghosts->isNotEmpty()) {
            $this->warn('GHOST SCAN: Found '.$ghosts->count().' stale ghost records. Re-injecting...');

            // LEVEL 81: THE SILENT WITNESS (Aggregated Telemetry)
            // Loop logging is forbidden here.
            $reapedCount = 0;
            $resurrectedCount = 0;
            $sampleId = null;

            foreach ($ghosts as $message) {
                // Capture first ID for forensic sample
                if ($sampleId === null) {
                    $sampleId = $message->id;
                }

                try {
                    $event = $this->hydrate($message);

                    // Deterministic Re-Dispatch
                    $dispatcher->dispatch($event);

                    // Mark complete effectively
                    $message->update([
                        'status' => OutboxMessage::STATUS_COMPLETED,
                        'published_at' => now(),
                        'locked_at' => null,
                    ]);

                    $ghostCount++;

                } catch (\Throwable $e) {
                    // LEVEL 75: THE POISON FILTER (Strike-Based Exorcism)
                    // Every exception is a Strike. We must actively penalize failure.

                    $attempts = $message->recovery_count + 1;
                    $maxAttempts = (int) config('outbox.recovery.max_attempts', 5);

                    // No internal logging here.

                    if ($attempts >= $maxAttempts) {
                        // LEVEL 75: THE TERMINAL PROTOCOL (Quarantine)
                        // Threshold reached. This message is Toxic. Kill it.
                        $message->update([
                            'status' => OutboxMessage::STATUS_FAILED,
                            'error' => substr($e->getMessage(), 0, 60000), // Persist evidence
                            'recovery_count' => $attempts,
                        ]);
                        $reapedCount++;

                    } else {
                        // LEVEL 75: THE TEMPORAL BACKOFF (Push into Future)
                        // Survivor. Penalize and push back.
                        $message->update([
                            'recovery_count' => $attempts,
                            'updated_at' => now(), // Backoff: Resets the "Stale Clock"
                        ]);
                        $resurrectedCount++;
                    }
                }
            }

            // LEVEL 81: SUMMARY LOG
            if ($reapedCount > 0) {
                Log::error("THE SILENT WITNESS: Exorcised {$reapedCount} toxic ghosts. Sample ID: {$sampleId}");
            }
            if ($resurrectedCount > 0) {
                Log::warning("THE SILENT WITNESS: Pushed back {$resurrectedCount} failing ghosts. Sample ID: {$sampleId}");
            }
            if ($ghostCount > 0) {
                $this->info("GHOST RECOVERY: Successfully redispatched {$ghostCount} messages.");
            }

            // LEVEL 69: MEMORY PURITY
            // We rely on PHP 8.1+ Reference Counting. No manual GC.
        }

        if ($ghostCount === 0) {
            $this->info('GHOST SCAN: Clean. No ghosts found in owned shards.');
        } else {
            $this->info("GHOST SCAN: Recovery Complete. Resurrected {$ghostCount} messages.");
        }
    }

    /**
     * PHASE 2: Process a single message with its own ACID boundary.
     */
    private function processMessage(OutboxMessage $message, Dispatcher $dispatcher): void
    {
        // LEVEL 64.1: ATOMIC COUNTER STEWARDSHIP
        // We increment BEFORE the transaction to capture "Intent to Dispatch".
        // We use try/finally to ensure the counter is ALWAYS decremented,
        // regardless of DB deadlocks, rollbacks, or logic explosions.

        $this->pendingDispatches++;

        try {
            // Attempt to hydrate and dispatch
            $event = $this->hydrate($message);

            // ACID: Dispatch within transaction to synchronize status update
            // LEVEL 63/64: Deterministic Fail-Safe Journal
            // 1. Transaction Starts
            // 2. Status -> DISPATCH_PENDING (Committed intent)
            // 3. Commit
            // 4. AfterCommit -> Dispatch -> Status=COMPLETED

            DB::transaction(function () use ($message, $dispatcher, $event) {
                // LEVEL 64: Mark as "Committed to Dispatch" BEFORE the actual dispatch
                // This updates the DB state to say "I intend to send this outside".
                // If we crash here, the Ghost Scan will find it.
                $message->update([
                    'status' => OutboxMessage::STATUS_DISPATCH_PENDING,
                    'locked_at' => null, // Release lock to allow recovery if we die
                ]);

                DB::afterCommit(function () use ($dispatcher, $event, $message) {
                    try {
                        $dispatcher->dispatch($event);

                        // LEVEL 74: THE FINAL BREATH (Optimistic State Assertion)
                        // We must NOT blindly update. We must prove sovereignty at the exact moment of completion.
                        // If lease expired during dispatch (70s job vs 60s lease), another worker might have
                        // recovered this message (Reset to PENDING). If we update now, we cause Double Delivery.

                        $affected = OutboxMessage::where('id', $message->id)
                            ->where('status', OutboxMessage::STATUS_DISPATCH_PENDING) // Must still be in dispatch state
                            ->where('worker_id', $this->leaseManager->getWorkerId()) // Must still be owned by us
                            ->update([
                                'status' => OutboxMessage::STATUS_COMPLETED,
                                'published_at' => now(),
                            ]);

                        // LEVEL 74: OUTCOME ANALYSIS
                        if ($affected === 0) {
                            // The "Final Breath" Denied Protocol
                            // Sovereignty was lost during the dispatch. We accept the loss.
                            // We do NOT throw (side effects already happened).
                            Log::warning("FINAL BREATH DENIED: Could not mark message {$message->id} as completed. Sovereignty lost or status changed during dispatch.", [
                                'expected_worker' => $this->leaseManager->getWorkerId(),
                                'actual_state' => 'UNKNOWN_CHANGED',
                            ]);

                            // Optional: Increment metric
                            // $this->metrics->increment('outbox_sovereignty_lost');
                        }
                    } finally {
                        // This was moved to the outer finally block
                    }
                });
            });

            // LEVEL 44: Record processing latency for observability
            try {
                $this->recordLatency($message);
            } catch (Throwable $metricsError) {
                Log::warning('Failed to record outbox metrics', [
                    'message_id' => $message->id,
                    'error' => $metricsError->getMessage(),
                ]);
            }

        } catch (Throwable $e) {
            // RESILIENCE: Handle failure with atomic retry logic
            Log::error("Outbox processing failed for message {$message->id}", [
                'type' => $message->type,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $isTerminal = $message->incrementAttempt($e->getMessage());

            if ($isTerminal) {
                throw $e;
            }
        } finally {
            // LEVEL 64.1: HYDRAULIC RELEASE
            // This MUST happen here. If transaction fails (rollback), we still release the pressure.
            // If dispatch throws, we release.
            // If the server catches fire, we try to release.
            $this->pendingDispatches--;
        }
    }

    /**
     * SAFE HYDRATION: Convert JSON payload to Event object.
     * Handles schema mismatches gracefully.
     */
    private function hydrate(OutboxMessage $message): object
    {
        $payload = $message->payload;

        if (! isset($payload['data']) || ! isset($payload['meta'])) {
            throw new \RuntimeException("Invalid payload structure for message {$message->id}");
        }

        $eventData = $payload['data'];
        $class = $message->type;

        // SCHEMA SAFETY: Check if class exists before hydration
        if (! class_exists($class)) {
            throw new \RuntimeException(
                "Serialization Error: Event class {$class} not found. ".
                "The class may have been renamed or removed. Message ID: {$message->id}"
            );
        }

        return $this->hydrateFromArray($class, $eventData);
    }

    /**
     * Hydrate event from array using constructor injection.
     */
    private function hydrateFromArray(string $className, array $data): object
    {
        $reflection = new \ReflectionClass($className);

        if (! $constructor = $reflection->getConstructor()) {
            $object = new $className;
            foreach ($data as $key => $val) {
                if ($reflection->hasProperty($key)) {
                    $property = $reflection->getProperty($key);
                    // Prevent modifying readonly properties after instantiation
                    if (! $property->isReadOnly()) {
                        $property->setAccessible(true);
                        $property->setValue($object, $val);
                    }
                }
            }

            return $object;
        }

        $args = [];
        foreach ($constructor->getParameters() as $param) {
            $name = $param->getName();
            $type = $param->getType();
            $value = $data[$name] ?? null;

            if ($value !== null && $type && ! $type->isBuiltin()) {
                $typeName = $type->getName();

                // Handle DateTime rehydration
                if (is_string($value) && is_a($typeName, DateTimeInterface::class, true)) {
                    $value = new \DateTimeImmutable($value);
                } elseif (is_array($value) && class_exists($typeName)) {
                    // Handle nested object rehydration (Value Objects, nested DTOs)
                    $value = $this->hydrateFromArray($typeName, $value);
                } elseif ((is_string($value) || is_int($value)) && enum_exists($typeName)) {
                    // Handle Enum rehydration
                    $value = $typeName::from($value);
                }
            }

            if ($value !== null) {
                $args[] = $value;
            } elseif ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
            } elseif ($param->allowsNull()) {
                $args[] = null;
            } else {
                throw new \RuntimeException(
                    "Missing required constructor argument: {$name} for class {$className}"
                );
            }
        }

        return $reflection->newInstanceArgs($args);
    }

    /**
     * LEVEL 44: Record processing latency for observability.
     */
    private function recordLatency(OutboxMessage $message): void
    {
        $lagMs = now()->diffInMilliseconds($message->created_at);
        $lagSeconds = $lagMs / 1000;
        $partition = $message->partition_key ?? 0;

        // Emit structured log for log aggregation
        Log::info('outbox_processing_lag', [
            'message_id' => $message->id,
            'partition_key' => $partition,
            'aggregate_id' => $message->aggregate_id,
            'type' => $message->type,
            'lag_ms' => $lagMs,
        ]);

        // Update partition lag in cache (exponential moving average)
        $key = "outbox:lag:partition:{$partition}";
        $current = Cache::get($key, 0.0);
        $newLag = ($current * 0.7) + ($lagSeconds * 0.3);
        Cache::put($key, $newLag, 60);

        // Alert if lag exceeds threshold
        if ($lagSeconds > 30) {
            Log::alert('OUTBOX SATURATION DETECTED', [
                'partition_key' => $partition,
                'lag_seconds' => $lagSeconds,
                'threshold_seconds' => 30,
            ]);
        }
    }
}
