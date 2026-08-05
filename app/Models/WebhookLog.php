<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * WebhookLog - Audit Trail for External Webhooks
 *
 * Stores all incoming webhook events for:
 * - Idempotency (prevent duplicate processing)
 * - Forensic audit trail
 * - Debugging payment issues
 *
 * @property int $id
 * @property string $provider
 * @property string $external_event_id
 * @property string $event_type
 * @property array $payload
 * @property string $status
 * @property int $attempts
 * @property string|null $payload_hash
 * @property string|null $processing_token
 * @property \Carbon\Carbon $created_at
 */
class WebhookLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'provider',
        'external_event_id',
        'event_type',
        'payload',
        'status',
        'attempts',
        'processing_started_at',
        'processed_at',
        'failed_at',
        'last_error',
        'payload_hash',
        'processing_token',
        'created_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'created_at' => 'datetime',
        'processing_started_at' => 'datetime',
        'processed_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (WebhookLog $model) {
            $model->created_at = now();
        });
    }
}
