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
        'handled_by_user_id',
        'note_type',
        'status',
        'priority',
        'source_channel',
        'requires_follow_up',
        'body',
        'next_action_at',
        'handled_at',
        'pinned_at',
        'metadata',
    ];

    protected $casts = [
        'requires_follow_up' => 'boolean',
        'next_action_at' => 'datetime',
        'handled_at' => 'datetime',
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

    public function handledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by_user_id');
    }

    public function getNoteTypeLabelAttribute(): string
    {
        return match ($this->note_type) {
            'follow_up' => 'Seguimiento',
            'service' => 'Servicio',
            'sale' => 'Venta',
            'inbound_reply' => 'Respuesta',
            'opt_in_update' => 'Opt-in',
            default => 'General',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'in_progress' => 'En curso',
            'resolved' => 'Resuelto',
            default => 'Abierto',
        };
    }

    public function getPriorityLabelAttribute(): string
    {
        return match ($this->priority) {
            'urgent' => 'Urgente',
            'high' => 'Alta',
            'low' => 'Baja',
            default => 'Normal',
        };
    }

    public function getSourceChannelLabelAttribute(): string
    {
        return match ($this->source_channel) {
            'whatsapp' => 'WhatsApp',
            'email' => 'Email',
            'phone' => 'Llamada',
            default => $this->source_channel !== null ? ucfirst($this->source_channel) : 'Sistema',
        };
    }
}
