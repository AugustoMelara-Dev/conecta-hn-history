<?php

declare(strict_types=1);

namespace App\Models;

use App\Infrastructure\Persistence\Eloquent\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CustomerConversation extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'customer_profile_id',
        'owner_user_id',
        'location_id',
        'assignment_group',
        'conversation_key',
        'source_channel',
        'status',
        'priority',
        'response_sla_minutes',
        'message_count',
        'internal_note_count',
        'reply_count',
        'opened_at',
        'first_response_at',
        'last_customer_message_at',
        'last_team_message_at',
        'next_action_at',
        'response_due_at',
        'escalation_due_at',
        'closed_at',
        'metadata',
    ];

    protected $casts = [
        'message_count' => 'integer',
        'internal_note_count' => 'integer',
        'reply_count' => 'integer',
        'response_sla_minutes' => 'integer',
        'opened_at' => 'datetime',
        'first_response_at' => 'datetime',
        'last_customer_message_at' => 'datetime',
        'last_team_message_at' => 'datetime',
        'next_action_at' => 'datetime',
        'response_due_at' => 'datetime',
        'escalation_due_at' => 'datetime',
        'closed_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function customerProfile(): BelongsTo
    {
        return $this->belongsTo(CustomerProfile::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(CustomerConversationMessage::class)->latest('occurred_at')->latest('id');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(CustomerNote::class)->latest('created_at');
    }

    public function touchpoints(): HasMany
    {
        return $this->hasMany(CustomerTouchpoint::class)->latest('created_at');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(CustomerTask::class)->latest('created_at');
    }

    public function journeyRun(): HasOne
    {
        return $this->hasOne(CustomerJourneyRun::class)->latest('updated_at');
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'in_progress' => 'En curso',
            'waiting_customer' => 'Esperando cliente',
            'closed' => 'Cerrada',
            default => 'Abierta',
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

    public function getSlaLabelAttribute(): string
    {
        if ($this->status === 'closed') {
            return 'Cerrada';
        }

        if ($this->status === 'waiting_customer') {
            return 'Esperando cliente';
        }

        if ($this->response_due_at === null) {
            return 'Sin SLA';
        }

        if ($this->response_due_at->isPast()) {
            return 'Vencida';
        }

        return 'En tiempo';
    }

    public function getSlaColorAttribute(): string
    {
        return match ($this->sla_label) {
            'Vencida' => 'danger',
            'En tiempo' => 'success',
            'Esperando cliente' => 'info',
            'Cerrada' => 'gray',
            default => 'warning',
        };
    }

    public function getOwnerLabelAttribute(): string
    {
        $owner = $this->owner;

        return $owner instanceof User && $owner->name !== ''
            ? $owner->name
            : 'Sin owner';
    }

    public function getLocationLabelAttribute(): string
    {
        $location = $this->location;

        return $location instanceof Location && $location->name !== ''
            ? $location->name
            : 'Sin sede';
    }

    public function getAssignmentGroupLabelAttribute(): string
    {
        $label = config("customer-operations.groups.{$this->assignment_group}.label");

        if (is_string($label) && $label !== '') {
            return $label;
        }

        return $this->assignment_group !== null && $this->assignment_group !== ''
            ? str((string) $this->assignment_group)->replace('_', ' ')->title()->value()
            : 'Sin equipo';
    }
}
