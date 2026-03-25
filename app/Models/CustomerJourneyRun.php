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
        'current_step_index',
        'next_action_at',
        'last_transition_at',
        'completed_at',
        'metadata',
    ];

    protected $casts = [
        'current_step_index' => 'integer',
        'next_action_at' => 'datetime',
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

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'completed' => 'Completado',
            'paused' => 'Pausado',
            default => 'Activo',
        };
    }
}
