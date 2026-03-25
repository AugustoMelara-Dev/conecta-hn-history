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
        'assigned_user_id',
        'location_id',
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
        'lifetime_points_earned',
        'loyalty_tier',
        'redeemable_rewards_count',
        'points_to_next_reward',
        'next_reward_name',
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
        'last_loyalty_activity_at',
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
        'lifetime_points_earned' => 'integer',
        'redeemable_rewards_count' => 'integer',
        'points_to_next_reward' => 'integer',
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
        'last_loyalty_activity_at' => 'datetime',
        'reactivation_score' => 'integer',
    ];

    public function setEmailAttribute(mixed $value): void
    {
        $normalized = self::normalizeEmailValue($value);

        $this->attributes['email'] = $normalized;
        $this->attributes['normalized_email'] = $normalized;
    }

    public function setNormalizedEmailAttribute(mixed $value): void
    {
        $this->attributes['normalized_email'] = self::normalizeEmailValue($value);
    }

    public function setPhoneAttribute(mixed $value): void
    {
        $phone = self::sanitizeTextValue($value);
        $normalized = self::normalizePhoneValue($value);

        $this->attributes['phone'] = $phone;
        $this->attributes['normalized_phone'] = $normalized;
    }

    public function setNormalizedPhoneAttribute(mixed $value): void
    {
        $this->attributes['normalized_phone'] = self::normalizePhoneValue($value);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
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

    public function conversations(): HasMany
    {
        return $this->hasMany(CustomerConversation::class)->latest('updated_at')->latest('id');
    }

    public function conversationMessages(): HasMany
    {
        return $this->hasMany(CustomerConversationMessage::class)->latest('occurred_at')->latest('id');
    }

    public function journeyRuns(): HasMany
    {
        return $this->hasMany(CustomerJourneyRun::class)->latest('updated_at')->latest('id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(CustomerTask::class)->latest('sla_due_at')->latest('created_at');
    }

    public function loyaltyLedger(): HasMany
    {
        return $this->hasMany(CustomerLoyaltyLedger::class)->latest('awarded_at')->latest('created_at');
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

    public function getNextRewardCopyAttribute(): string
    {
        if ($this->next_reward_name === null) {
            return 'Crea recompensas y usa puntos para mover continuidad, recompra y valor percibido.';
        }

        if ((int) $this->redeemable_rewards_count > 0) {
            return 'Ya puede canjear "'.$this->next_reward_name.'".';
        }

        return 'Le faltan '.max(0, (int) $this->points_to_next_reward).' puntos para desbloquear "'.$this->next_reward_name.'".';
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

    public function getAssignedOwnerLabelAttribute(): string
    {
        return $this->assignedUser?->name ?? 'Sin owner';
    }

    public function getLocationLabelAttribute(): string
    {
        return $this->location?->name ?? 'Sin sede';
    }

    private static function normalizeEmailValue(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = mb_strtolower(trim($value));

        return $value !== '' ? $value : null;
    }

    private static function normalizePhoneValue(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $value);

        return is_string($digits) && $digits !== '' ? $digits : null;
    }

    private static function sanitizeTextValue(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value !== '' ? $value : null;
    }
}
