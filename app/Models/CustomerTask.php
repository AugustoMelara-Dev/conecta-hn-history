<?php

declare(strict_types=1);

namespace App\Models;

use App\Infrastructure\Persistence\Eloquent\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerTask extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'customer_profile_id',
        'customer_conversation_id',
        'customer_note_id',
        'created_by_user_id',
        'assigned_user_id',
        'location_id',
        'assignment_group',
        'task_type',
        'title',
        'body',
        'status',
        'priority',
        'response_sla_minutes',
        'source_channel',
        'due_at',
        'sla_due_at',
        'completed_at',
        'metadata',
    ];

    protected $casts = [
        'due_at' => 'datetime',
        'sla_due_at' => 'datetime',
        'completed_at' => 'datetime',
        'response_sla_minutes' => 'integer',
        'metadata' => 'array',
    ];

    public function customerProfile(): BelongsTo
    {
        return $this->belongsTo(CustomerProfile::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(CustomerConversation::class, 'customer_conversation_id');
    }

    public function note(): BelongsTo
    {
        return $this->belongsTo(CustomerNote::class, 'customer_note_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function getTaskTypeLabelAttribute(): string
    {
        return match ($this->task_type) {
            'reply_follow_up' => 'Respuesta',
            'reactivation' => 'Reactivacion',
            'retention' => 'Retencion',
            'sale' => 'Venta',
            'service' => 'Servicio',
            default => 'Seguimiento',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'in_progress' => 'En curso',
            'waiting_customer' => 'Esperando cliente',
            'completed' => 'Completada',
            'cancelled' => 'Cancelada',
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
        if (in_array($this->status, ['completed', 'cancelled'], true)) {
            return 'Cerrada';
        }

        if ($this->status === 'waiting_customer') {
            return 'Esperando cliente';
        }

        if ($this->sla_due_at === null) {
            return 'Sin SLA';
        }

        if ($this->sla_due_at->isPast()) {
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
