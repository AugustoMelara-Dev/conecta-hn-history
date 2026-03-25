<?php

declare(strict_types=1);

namespace App\Models;

use App\Infrastructure\Persistence\Eloquent\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerConversationMessage extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'customer_conversation_id',
        'customer_profile_id',
        'created_by_user_id',
        'customer_touchpoint_id',
        'customer_note_id',
        'direction',
        'message_kind',
        'channel',
        'body',
        'status',
        'occurred_at',
        'metadata',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(CustomerConversation::class, 'customer_conversation_id');
    }

    public function customerProfile(): BelongsTo
    {
        return $this->belongsTo(CustomerProfile::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function touchpoint(): BelongsTo
    {
        return $this->belongsTo(CustomerTouchpoint::class, 'customer_touchpoint_id');
    }

    public function note(): BelongsTo
    {
        return $this->belongsTo(CustomerNote::class, 'customer_note_id');
    }

    public function getDirectionLabelAttribute(): string
    {
        return match ($this->direction) {
            'customer' => 'Cliente',
            'internal' => 'Interno',
            'system' => 'Sistema',
            default => 'Equipo',
        };
    }
}
