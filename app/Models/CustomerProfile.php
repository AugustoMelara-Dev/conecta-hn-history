<?php

declare(strict_types=1);

namespace App\Models;

use App\Infrastructure\Persistence\Eloquent\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerProfile extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'normalized_email',
        'normalized_phone',
        'name',
        'email',
        'phone',
        'preferred_channel',
        'lifecycle_stage',
        'customer_segment',
        'manual_segment',
        'crm_notes',
        'tags',
        'marketing_opt_in',
        'whatsapp_opt_in',
        'email_opt_in',
        'orders_count',
        'appointments_count',
        'leads_count',
        'total_spent',
        'average_ticket',
        'first_seen_at',
        'last_seen_at',
        'last_order_at',
        'last_appointment_at',
        'last_lead_at',
        'last_contacted_at',
        'reactivation_score',
    ];

    protected $casts = [
        'tags' => 'array',
        'marketing_opt_in' => 'boolean',
        'whatsapp_opt_in' => 'boolean',
        'email_opt_in' => 'boolean',
        'total_spent' => 'decimal:2',
        'average_ticket' => 'decimal:2',
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'last_order_at' => 'datetime',
        'last_appointment_at' => 'datetime',
        'last_lead_at' => 'datetime',
        'last_contacted_at' => 'datetime',
        'reactivation_score' => 'integer',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    public function touchpoints(): HasMany
    {
        return $this->hasMany(CustomerTouchpoint::class)->latest('created_at');
    }

    public function getSegmentDisplayAttribute(): string
    {
        return $this->manual_segment ?: $this->customer_segment;
    }

    public function getSegmentDisplayLabelAttribute(): string
    {
        return match ($this->segment_display) {
            'vip' => 'VIP',
            'repeat' => 'Recurrente',
            'reactivation' => 'Reactivacion',
            'booked_service' => 'Con citas',
            'active' => 'Activo',
            'prospect' => 'Prospecto',
            'new' => 'Nuevo',
            default => str($this->segment_display)->replace('_', ' ')->title()->value(),
        };
    }

    public function getLifecycleStageLabelAttribute(): string
    {
        return match ($this->lifecycle_stage) {
            'loyal' => 'Leal',
            'active' => 'Activo',
            'dormant' => 'Dormido',
            'prospect' => 'Prospecto',
            default => str($this->lifecycle_stage)->replace('_', ' ')->title()->value(),
        };
    }

    public function getPreferredChannelLabelAttribute(): string
    {
        return match ($this->preferred_channel) {
            'email' => 'Email',
            'phone' => 'Llamada',
            'in_person' => 'Presencial',
            default => 'WhatsApp',
        };
    }

    public function getReactivationLabelAttribute(): string
    {
        $score = (int) $this->reactivation_score;

        return match (true) {
            $score >= 85 => 'Urgente',
            $score >= 65 => 'Alta',
            $score >= 45 => 'Media',
            $score >= 20 => 'Suave',
            default => 'Estable',
        };
    }
}
