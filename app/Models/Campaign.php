<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Marketing\Enums\AudienceType;
use App\Infrastructure\Persistence\Eloquent\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class Campaign extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'subject',
        'content',
        'type',
        'audience_type',
        'customer_segment',
        'loyalty_tier',
        'only_redeemable_rewards',
        'location_id',
        'owner_user_id',
        'loyalty_reward_id',
        'requires_marketing_opt_in',
        'whatsapp_template_name',
        'whatsapp_template_language',
        'status',
        'scheduled_at',
        'is_recurring',
        'cadence_interval_days',
        'next_run_at',
        'last_dispatched_at',
        'sent_at',
        'recipients_count',
    ];

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'audience_type' => AudienceType::class,
        'only_redeemable_rewards' => 'boolean',
        'requires_marketing_opt_in' => 'boolean',
        'scheduled_at' => 'datetime',
        'is_recurring' => 'boolean',
        'cadence_interval_days' => 'integer',
        'next_run_at' => 'datetime',
        'last_dispatched_at' => 'datetime',
        'sent_at' => 'datetime',
        'recipients_count' => 'integer',
    ];

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function ownerUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function loyaltyReward(): BelongsTo
    {
        return $this->belongsTo(LoyaltyReward::class, 'loyalty_reward_id');
    }

    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            'whatsapp' => 'WhatsApp',
            default => 'Email',
        };
    }

    public function getRewardLabelAttribute(): string
    {
        return $this->loyaltyReward?->name ?? 'Sin recompensa';
    }

    public function getCadenceLabelAttribute(): string
    {
        if (! $this->is_recurring || ! $this->cadence_interval_days) {
            return $this->scheduled_at instanceof Carbon ? 'Programada' : 'Manual';
        }

        return match ((int) $this->cadence_interval_days) {
            1 => 'Diaria',
            3 => 'Cada 3 dias',
            7 => 'Semanal',
            14 => 'Quincenal',
            30 => 'Mensual',
            default => 'Cada '.$this->cadence_interval_days.' dias',
        };
    }

    public function getAudienceSummaryAttribute(): string
    {
        $parts = [];

        if ($this->customer_segment !== null) {
            $parts[] = 'Segmento '.str($this->customer_segment)->replace('_', ' ')->title()->value();
        }

        if ($this->loyalty_tier !== null) {
            $parts[] = 'Tier '.str($this->loyalty_tier)->upper()->value();
        }

        if ($this->only_redeemable_rewards) {
            $parts[] = 'Con canje listo';
        }

        if ($this->loyaltyReward?->name) {
            $parts[] = 'Reward '.$this->loyaltyReward->name;
        }

        if ($this->location?->name) {
            $parts[] = 'Sede '.$this->location->name;
        }

        if ($this->ownerUser?->name) {
            $parts[] = 'Owner '.$this->ownerUser->name;
        }

        if ($this->is_recurring && $this->cadence_interval_days) {
            $parts[] = $this->cadence_label;
        }

        return $parts !== []
            ? implode(' | ', $parts)
            : 'Audiencia general';
    }

    public function usesCadence(): bool
    {
        return (bool) $this->is_recurring && (int) ($this->cadence_interval_days ?? 0) > 0;
    }

    public function isDueForDispatch(?Carbon $moment = null): bool
    {
        $moment ??= now();

        if ($this->usesCadence() && $this->next_run_at instanceof Carbon) {
            return $this->next_run_at->lessThanOrEqualTo($moment);
        }

        return $this->scheduled_at instanceof Carbon
            ? $this->scheduled_at->lessThanOrEqualTo($moment)
            : false;
    }
}
