<?php

declare(strict_types=1);

/**
 * Outbox Subsystem Configuration - Level 67 (Sharded Sovereignty)
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  SOVEREIGN: Workers only operate on data they explicitly own via lease   ║
 * ║  SCALABLE: Partition count is fully configurable via environment vars    ║
 * ║  OBSERVABLE: Shard-aware telemetry for precise incident correlation      ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Distribution Configuration
    |--------------------------------------------------------------------------
    |
    | Controls how messages are distributed across shards (partitions).
    | The partition_count determines the maximum horizontal scalability.
    | Increase this value to support more concurrent workers.
    |
    */
    'distribution' => [
        'partition_count' => (int) env('OUTBOX_PARTITION_COUNT', 64),
    ],

    /*
    |--------------------------------------------------------------------------
    | Leasing Configuration
    |--------------------------------------------------------------------------
    |
    | Controls the shard leasing mechanism that prevents race conditions.
    | - duration_seconds: How long a lease is valid before expiration.
    | - heartbeat_interval_seconds: How often workers must renew their leases.
    |
    | RULE: heartbeat_interval < duration_seconds (safety margin)
    |
    */
    'leasing' => [
        // LEVEL 73: CONFIGURABLE TEMPORAL ELASTICITY
        // SRE can now tune TTL for heavy jobs via environment variables.
        'ttl_seconds' => (int) env('OUTBOX_LEASE_TTL', 60),
        'duration_seconds' => (int) env('OUTBOX_LEASE_DURATION', 60),
        'heartbeat_interval_seconds' => (int) env('OUTBOX_HEARTBEAT_INTERVAL', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Recovery Configuration
    |--------------------------------------------------------------------------
    |
    | Controls the Ghost Recovery mechanism.
    | - stale_threshold_seconds: Messages older than this are considered ghosts.
    | - chunk_size: Number of messages to process per recovery chunk.
    |
    */
    'recovery' => [
        'stale_threshold_seconds' => (int) env('OUTBOX_GHOST_STALE_THRESHOLD', 30),
        'chunk_size' => (int) env('OUTBOX_RECOVERY_CHUNK_SIZE', 500),
    ],

    /*
    |--------------------------------------------------------------------------
    | Hydraulic Backpressure
    |--------------------------------------------------------------------------
    |
    | Controls the admission control mechanism.
    | - limit: Stop polling when pending dispatches exceed this.
    | - resume: Resume polling when pending dispatches drop below this.
    |
    */
    'hydraulic' => [
        'limit' => (int) env('OUTBOX_HYDRAULIC_LIMIT', 5000),
        'resume' => (int) env('OUTBOX_HYDRAULIC_RESUME', 2500),
    ],

];
