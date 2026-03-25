<?php

declare(strict_types=1);

namespace App\Models;

use App\Infrastructure\Persistence\Eloquent\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerNote extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'customer_profile_id',
        'created_by_user_id',
        'note_type',
        'body',
        'next_action_at',
        'pinned_at',
        'metadata',
    ];

    protected $casts = [
        'next_action_at' => 'datetime',
        'pinned_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function customerProfile(): BelongsTo
    {
        return $this->belongsTo(CustomerProfile::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function getNoteTypeLabelAttribute(): string
    {
        return match ($this->note_type) {
            'follow_up' => 'Seguimiento',
            'service' => 'Servicio',
            'sale' => 'Venta',
            'inbound_reply' => 'Respuesta',
            default => 'General',
        };
    }
}
