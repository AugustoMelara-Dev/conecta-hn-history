<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * OutboxMessage - Infrastructure Model for Transactional Outbox
 *
 * This is an INFRASTRUCTURE model, not a Domain entity.
 * It exists solely to persist/retrieve outbox messages.
 *
 * @property string $id
 * @property string $type
 * @property string|null $aggregate_type
 * @property string|null $aggregate_id
 * @property array $payload
 * @property \Carbon\Carbon $occurred_at
 * @property \Carbon\Carbon|null $published_at
 * @property string|null $error_message
 * @property int $attempts
 */
final class OutboxMessage extends Model
{
    use HasUuids;

    protected $table = 'outbox_messages';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'payload' => 'array',
        'occurred_at' => 'datetime',
        'published_at' => 'datetime',
        'attempts' => 'integer',
    ];

    /**
     * Scope for pending (unpublished) messages.
     */
    public function scopePending($query)
    {
        return $query->whereNull('published_at');
    }

    /**
     * Scope for messages that can be retried.
     */
    public function scopeRetryable($query, int $maxAttempts = 3)
    {
        return $query->pending()->where('attempts', '<', $maxAttempts);
    }

    /**
     * Mark message as published.
     */
    public function markAsPublished(): void
    {
        $this->update([
            'published_at' => now(),
            'error_message' => null,
        ]);
    }

    /**
     * Record a failed delivery attempt.
     */
    public function recordFailure(string $errorMessage): void
    {
        $this->update([
            'attempts' => $this->attempts + 1,
            'error_message' => $errorMessage,
        ]);
    }
}
