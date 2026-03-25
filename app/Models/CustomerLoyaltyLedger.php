<?php

declare(strict_types=1);

namespace App\Models;

use App\Infrastructure\Persistence\Eloquent\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerLoyaltyLedger extends Model
{
    use BelongsToTenant;

    public const TYPE_EARN = 'earn';

    public const TYPE_REDEEM = 'redeem';

    public const TYPE_BONUS = 'bonus';

    public const TYPE_ADJUSTMENT = 'adjustment';

    protected $table = 'customer_loyalty_ledger';

    protected $fillable = [
        'tenant_id',
        'customer_profile_id',
        'order_id',
        'loyalty_reward_id',
        'created_by_user_id',
        'type',
        'points_delta',
        'monetary_value',
        'description',
        'metadata',
        'awarded_at',
    ];

    protected $casts = [
        'points_delta' => 'integer',
        'monetary_value' => 'decimal:2',
        'metadata' => 'array',
        'awarded_at' => 'datetime',
    ];

    public function customerProfile(): BelongsTo
    {
        return $this->belongsTo(CustomerProfile::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function reward(): BelongsTo
    {
        return $this->belongsTo(LoyaltyReward::class, 'loyalty_reward_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            self::TYPE_EARN => 'Acumulacion',
            self::TYPE_REDEEM => 'Canje',
            self::TYPE_BONUS => 'Bono',
            default => 'Ajuste',
        };
    }
}
