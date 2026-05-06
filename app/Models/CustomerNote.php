<?php

declare(strict_types=1);

namespace App\Models;

use App\Infrastructure\Persistence\Eloquent\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class CustomerNote extends Model
{
    use BelongsToTenant, LogsActivity;

    protected $fillable = [
        'tenant_id',
        'customer_profile_id',
        'crm_deal_id',
        'customer_conversation_id',
        'created_by_user_id',
        'handled_by_user_id',
        'assigned_user_id',
        'location_id',
        'note_type',
        'conversation_key',
        'status',
        'priority',
        'source_channel',
        'requires_follow_up',
        'body',
        'next_action_at',
        'response_due_at',
        'first_response_at',
        'last_customer_message_at',
        'last_team_message_at',
        'reply_count',
        'handled_at',
        'pinned_at',
        'metadata',
    ];

    protected $casts = [
        'requires_follow_up' => 'boolean',
        'next_action_at' => 'datetime',
        'response_due_at' => 'datetime',
        'first_response_at' => 'datetime',
        'last_customer_message_at' => 'datetime',
        'last_team_message_at' => 'datetime',
        'reply_count' => 'integer',
        'handled_at' => 'datetime',
        'pinned_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function customerProfile(): BelongsTo
    {
        return $this->belongsTo(CustomerProfile::class);
    }

    public function deal(): BelongsTo
    {
        return $this->belongsTo(CrmDeal::class, 'crm_deal_id');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(CustomerConversation::class, 'customer_conversation_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function handledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by_user_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function task(): HasOne
    {
        return $this->hasOne(CustomerTask::class, 'customer_note_id');
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
            'pending_customer' => 'Esperando cliente',
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

    public function getSlaLabelAttribute(): string
    {
        if ($this->status === 'resolved') {
            return 'Resuelto';
        }

        if ($this->status === 'pending_customer') {
            return 'Esperando respuesta';
        }

        if ($this->response_due_at === null) {
            return 'Sin SLA';
        }

        if ($this->response_due_at->isPast()) {
            return 'Vencido';
        }

        return 'En tiempo';
    }

    public function getSlaColorAttribute(): string
    {
        return match ($this->sla_label) {
            'Vencido' => 'danger',
            'En tiempo' => 'success',
            'Esperando respuesta' => 'info',
            'Resuelto' => 'gray',
            default => 'warning',
        };
    }

    public function getAssignedOwnerLabelAttribute(): string
    {
        $assignedUser = $this->assignedUser;

        return $assignedUser instanceof User && $assignedUser->name !== ''
            ? $assignedUser->name
            : 'Sin asignar';
    }

    public function getLocationLabelAttribute(): string
    {
        $location = $this->location;

        return $location instanceof Location && $location->name !== ''
            ? $location->name
            : 'Sin sede';
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('crm_notes')
            ->logOnly([
                'tenant_id',
                'customer_profile_id',
                'crm_deal_id',
                'created_by_user_id',
                'note_type',
                'status',
                'priority',
                'next_action_at',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName): string => "crm_note.{$eventName}");
    }
}
