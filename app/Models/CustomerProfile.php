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
        'acquisition_channel',
        'first_conversion_channel',
        'first_conversion_value',
        'initial_ltv',
        'orders_count',
        'appointments_count',
        'repeat_purchase_count',
        'loyalty_points_balance',
        'loyalty_tier',
        'leads_count',
        'total_spent',
        'average_ticket',
        'first_seen_at',
        'last_seen_at',
        'last_order_at',
        'last_appointment_at',
        'last_lead_at',
        'last_contacted_at',
        'last_engaged_channel',
        'last_reactivated_at',
        'last_inbound_reply_at',
        'reactivation_score',
    ];

    protected $casts = [
        'tags' => 'array',
        'marketing_opt_in' => 'boolean',
        'whatsapp_opt_in' => 'boolean',
        'email_opt_in' => 'boolean',
        'first_conversion_value' => 'decimal:2',
        'initial_ltv' => 'decimal:2',
        'repeat_purchase_count' => 'integer',
        'loyalty_points_balance' => 'integer',
        'total_spent' => 'decimal:2',
        'average_ticket' => 'decimal:2',
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'last_order_at' => 'datetime',
        'last_appointment_at' => 'datetime',
        'last_lead_at' => 'datetime',
        'last_contacted_at' => 'datetime',
        'last_reactivated_at' => 'datetime',
        'last_inbound_reply_at' => 'datetime',
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

    public function notes(): HasMany
    {
        return $this->hasMany(CustomerNote::class)->latest('pinned_at')->latest('created_at');
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

    public function getAcquisitionChannelLabelAttribute(): string
    {
        return match ($this->acquisition_channel) {
            'whatsapp', 'whatsapp_click' => 'WhatsApp',
            'email' => 'Email',
            'website', 'web' => 'Sitio web',
            'appointments', 'booking' => 'Agenda',
            'catalog' => 'Catalogo',
            'manual' => 'Manual',
            default => $this->acquisition_channel !== null
                ? str($this->acquisition_channel)->replace('_', ' ')->title()->value()
                : 'Sin datos',
        };
    }

    public function getLoyaltyTierLabelAttribute(): string
    {
        return match ($this->loyalty_tier) {
            'vip' => 'VIP',
            'gold' => 'Gold',
            'silver' => 'Silver',
            default => 'Base',
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
