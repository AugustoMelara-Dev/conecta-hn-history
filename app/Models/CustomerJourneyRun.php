<?php

declare(strict_types=1);

namespace App\Models;

use App\Infrastructure\Persistence\Eloquent\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerJourneyRun extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'customer_conversation_id',
        'customer_profile_id',
        'journey_key',
        'segment_key',
        'business_segment_key',
        'status',
        'current_step_key',
        'next_template_key',
        'sequence_state',
        'due_state',
        'current_step_index',
        'next_action_at',
        'step_due_at',
        'current_step_executed_at',
        'auto_step_count',
        'last_auto_touchpoint_id',
        'last_transition_at',
        'completed_at',
        'metadata',
    ];

    protected $casts = [
        'current_step_index' => 'integer',
        'next_action_at' => 'datetime',
        'step_due_at' => 'datetime',
        'current_step_executed_at' => 'datetime',
        'auto_step_count' => 'integer',
        'last_transition_at' => 'datetime',
        'completed_at' => 'datetime',
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

    public function lastAutoTouchpoint(): BelongsTo
    {
        return $this->belongsTo(CustomerTouchpoint::class, 'last_auto_touchpoint_id');
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'completed' => 'Completado',
            'paused' => 'Pausado',
            default => 'Activo',
        };
    }

    public function getSequenceStateLabelAttribute(): string
    {
        return match ($this->sequence_state) {
            'waiting_customer' => 'Esperando reply',
            'stalled' => 'Requiere rescate',
            'completed' => 'Completado',
            default => 'Listo para mover',
        };
    }

    public function getDueStateLabelAttribute(): string
    {
        return match ($this->due_state) {
            'breached' => 'Paso vencido',
            'scheduled' => 'Programado',
            'completed' => 'Cerrado',
            default => 'En tiempo',
        };
    }
}
