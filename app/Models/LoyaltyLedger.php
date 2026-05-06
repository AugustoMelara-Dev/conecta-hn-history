<?php

declare(strict_types=1);

namespace App\Models;

use App\Infrastructure\Persistence\Eloquent\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Loyalty Ledger Model - Transaction History
 * 
 * APPEND-ONLY ledger for loyalty point transactions.
 * Never update existing rows - insert corrections instead.
 * 
 * Transaction Types:
 * - earn: Points awarded from order completion
 * - redeem: Points spent on rewards
 * - expire: Points that have expired
 * - adjustment: Manual corrections by admin
 * 
 * INVARIANT: Points are INTEGER (no floating point math)
 * INVARIANT: User balance = SUM(amount) WHERE tenant_id = X
 */
class LoyaltyLedger extends Model
{
    use BelongsToTenant;

    public const TYPE_EARN = 'earn';
    public const TYPE_REDEEM = 'redeem';
    public const TYPE_EXPIRE = 'expire';
    public const TYPE_ADJUSTMENT = 'adjustment';

    protected $table = 'loyalty_ledger';

    protected $fillable = [
        'user_id',
        'order_id',
        'type',
        'amount',
        'description',
        'expires_at',
    ];

    protected $casts = [
        'amount' => 'integer',
        'expires_at' => 'datetime',
    ];

    /**
     * Get the user (customer) who owns these points.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the order that generated these points (for earn transactions).
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the tenant that issued these points.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Scope to get only active (non-expired) points.
     */
    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
              ->orWhere('expires_at', '>', now());
        });
    }

    /**
     * Scope to get expired points that haven't been processed.
     */
    public function scopeExpired($query)
    {
        return $query->where('type', self::TYPE_EARN)
                     ->whereNotNull('expires_at')
                     ->where('expires_at', '<=', now());
    }
}
