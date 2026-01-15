<?php

declare(strict_types=1);

namespace App\Infrastructure\Common\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * OutboxMessage Model - Titanium Standard
 * 
 * Supports privacy (encrypted payload) and dead-letter handling (error column).
 */
class OutboxMessage extends Model
{
    protected $table = 'outbox_messages';

    protected $fillable = [
        'event_type',
        'payload',
        'occurred_at',
        'processed',
        'processed_at',
        'error',
    ];

    protected $casts = [
        'processed' => 'boolean',
        'occurred_at' => 'datetime',
        'processed_at' => 'datetime',
    ];
}
