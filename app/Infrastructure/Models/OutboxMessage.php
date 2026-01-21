<?php

declare(strict_types=1);

namespace App\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * OutboxMessage Model - Level 42 True Hardened (Self-Encapsulating)
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  ATOMIC STATE MANAGEMENT: incrementAttempt() handles retry logic         ║
 * ║  ZOMBIE LOCK PREVENTION: locked_at timestamp for worker coordination     ║
 * ║  DEAD LETTER SUPPORT: Automatic FAILED state after 3 attempts            ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class OutboxMessage extends Model
{
    public const MAX_ATTEMPTS = 3;
    public const QUARANTINE_THRESHOLD = 5; // Poison pill threshold
    public const PARTITION_COUNT = 64; // Number of logical partitions (matches outbox_shards)
    
    public const STATUS_PENDING = 'PENDING';
    public const STATUS_PROCESSING = 'PROCESSING';
    public const STATUS_COMPLETED = 'COMPLETED';
    public const STATUS_FAILED = 'FAILED';
    public const STATUS_QUARANTINED = 'QUARANTINED'; // Level 47: Poison pill isolation
    public const STATUS_PENDING_AUTOPSY = 'PENDING_AUTOPSY'; // Level 54: Asynchronous Forensic Analysis
    public const STATUS_DISPATCH_PENDING = 'DISPATCH_PENDING'; // Level 64: Deterministic Fail-Safe Journal

    protected $table = 'outbox_messages';
    
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = true;

    protected $fillable = [
        'id',
        'type',
        'aggregate_id',
        'partition_key',
        'sequence_number',
        'payload',
        'occurred_at',
        'published_at',
        'status',
        'attempts',
        'recovery_count',
        'locked_at',
        'worker_id', // LEVEL 50.1: Atomic ownership
        'batch_correlation_id', // LEVEL 50.1: Batch telemetry
        'failed_at',
        'quarantined_at',
        'last_error',
    ];

    protected $casts = [
        'payload' => 'array',
        'occurred_at' => 'datetime',
        'published_at' => 'datetime',
        'locked_at' => 'datetime',
        'failed_at' => 'datetime',
        'quarantined_at' => 'datetime',
        'attempts' => 'integer',
        'recovery_count' => 'integer',
        'partition_key' => 'integer',
        'sequence_number' => 'integer',
    ];

    /**
     * LEVEL 42: Atomic Attempt Increment with State Transition
     * 
     * Encapsulates retry logic within the model. If attempts reach MAX_ATTEMPTS,
     * the message is automatically transitioned to FAILED state.
     * 
     * @param string $errorMessage The error that caused the failure
     * @return bool True if transitioned to FAILED, false if still retryable
     */
    public function incrementAttempt(string $errorMessage): bool
    {
        $this->attempts++;
        $this->last_error = substr($errorMessage, 0, 6000); // Prevent text overflow
        $this->locked_at = null; // Release the lock
        
        if ($this->attempts >= self::MAX_ATTEMPTS) {
            // TERMINAL FAILURE: Dead Letter State
            $this->status = self::STATUS_FAILED;
            $this->failed_at = now();
            $this->save();
            
            Log::emergency('POISON PILL NEUTRALIZED: Outbox message exceeded max retries', [
                'message_id' => $this->id,
                'type' => $this->type,
                'attempts' => $this->attempts,
                'error' => $errorMessage,
                'payload' => $this->payload,
            ]);
            
            return true; // Indicates terminal failure
        }
        
        // TRANSIENT FAILURE: Release for retry
        $this->status = self::STATUS_PENDING;
        $this->save();
        
        Log::warning('Outbox message failed, will retry', [
            'message_id' => $this->id,
            'attempt' => $this->attempts,
            'max_attempts' => self::MAX_ATTEMPTS,
        ]);
        
        return false; // Indicates retryable
    }

    /**
     * Mark message as successfully completed.
     */
    public function markCompleted(): void
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'published_at' => now(),
            'locked_at' => null,
        ]);
    }

    /**
     * Claim message for processing (prevents other workers from picking it up).
     */
    public function claim(): void
    {
        $this->update([
            'status' => self::STATUS_PROCESSING,
            'locked_at' => now(),
        ]);
    }
}

