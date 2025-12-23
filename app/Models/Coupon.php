<?php

namespace App\Models;

use App\DataTransferObjects\CouponValidationResult;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

/**
 * Coupon Model - Promotional Discount Engine
 * 
 * Supports:
 * - Fixed amount discounts (L. 50 off)
 * - Percentage discounts (10% off with optional cap)
 * - Business rules (min_spend, usage_limit, date range)
 * - Atomic usage tracking
 * 
 * SECURITY: Uses BelongsToTenant trait for automatic tenant isolation
 */
class Coupon extends Model
{
    use BelongsToTenant;
    // Discount types
    public const TYPE_FIXED = 'fixed';
    public const TYPE_PERCENT = 'percent';

    protected $fillable = [
        'code',
        'name',
        'description',
        'type',
        'value',
        'max_discount',
        'min_spend',
        'usage_limit',
        'usage_count',
        'starts_at',
        'expires_at',
        'is_active',
    ];

    /**
     * SECURITY: Immutable ownership fields.
     * tenant_id is set automatically by BelongsToTenant trait on creation.
     */
    protected $guarded = ['id', 'tenant_id', 'created_at', 'updated_at'];

    protected $casts = [
        'value' => 'decimal:2',
        'max_discount' => 'decimal:2',
        'min_spend' => 'decimal:2',
        'usage_limit' => 'integer',
        'usage_count' => 'integer',
        'is_active' => 'boolean',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    // ═══════════════════════════════════════════════════════════════════════
    // RELATIONSHIPS
    // Note: tenant() relationship is provided by BelongsToTenant trait
    // ═══════════════════════════════════════════════════════════════════════

    // ═══════════════════════════════════════════════════════════════════════
    // BOOT: Auto-transform code to uppercase
    // ═══════════════════════════════════════════════════════════════════════

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($coupon) {
            $coupon->code = strtoupper(trim($coupon->code));
        });
    }

    // ═══════════════════════════════════════════════════════════════════════
    // SCOPES
    // Note: forTenant() is provided by BelongsToTenant trait
    // ═══════════════════════════════════════════════════════════════════════

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeByCode(Builder $query, string $code): Builder
    {
        return $query->where('code', strtoupper(trim($code)));
    }

    public function scopeValid(Builder $query): Builder
    {
        $now = Carbon::now();

        return $query
            ->where('is_active', true)
            ->where(function ($q) use ($now) {
                $q->whereNull('starts_at')
                  ->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>=', $now);
            })
            ->where(function ($q) {
                $q->whereNull('usage_limit')
                  ->orWhereColumn('usage_count', '<', 'usage_limit');
            });
    }

    // ═══════════════════════════════════════════════════════════════════════
    // ACCESSORS
    // ═══════════════════════════════════════════════════════════════════════

    public function getFormattedValueAttribute(): string
    {
        if ($this->type === self::TYPE_PERCENT) {
            return $this->value . '%';
        }
        return 'L. ' . number_format($this->value, 2);
    }

    public function getUsageDisplayAttribute(): string
    {
        if ($this->usage_limit === null) {
            return "{$this->usage_count} / ∞";
        }
        return "{$this->usage_count} / {$this->usage_limit}";
    }

    public function getStatusAttribute(): string
    {
        if (!$this->is_active) {
            return 'inactive';
        }

        $now = Carbon::now();

        if ($this->starts_at && $now->lt($this->starts_at)) {
            return 'scheduled';
        }

        if ($this->expires_at && $now->gt($this->expires_at)) {
            return 'expired';
        }

        if ($this->usage_limit !== null && $this->usage_count >= $this->usage_limit) {
            return 'depleted';
        }

        return 'active';
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'active' => 'success',
            'scheduled' => 'info',
            'expired' => 'gray',
            'depleted' => 'warning',
            'inactive' => 'danger',
            default => 'gray',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'active' => 'Activo',
            'scheduled' => 'Programado',
            'expired' => 'Expirado',
            'depleted' => 'Agotado',
            'inactive' => 'Inactivo',
            default => 'Desconocido',
        };
    }

    // ═══════════════════════════════════════════════════════════════════════
    // VALIDATION METHODS
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Validate coupon against cart subtotal.
     * 
     * Returns a strongly-typed DTO instead of magic arrays.
     * Use toArray() for backwards compatibility with JSON responses.
     * 
     * @param float $cartSubtotal The cart subtotal to validate against
     * @return CouponValidationResult Immutable validation result object
     */
    public function validate(float $cartSubtotal): CouponValidationResult
    {
        $now = Carbon::now();

        // Check if active
        if (!$this->is_active) {
            return CouponValidationResult::error(
                'Este cupón no está activo',
                'INACTIVE'
            );
        }

        // Check start date
        if ($this->starts_at && $now->lt($this->starts_at)) {
            return CouponValidationResult::error(
                'Este cupón aún no está disponible',
                'NOT_STARTED'
            );
        }

        // Check expiry
        if ($this->expires_at && $now->gt($this->expires_at)) {
            return CouponValidationResult::error(
                'Este cupón ha expirado',
                'EXPIRED'
            );
        }

        // Check usage limit
        if ($this->usage_limit !== null && $this->usage_count >= $this->usage_limit) {
            return CouponValidationResult::error(
                'Este cupón ya alcanzó su límite de usos',
                'DEPLETED'
            );
        }

        // Check minimum spend
        if ($this->min_spend !== null && $cartSubtotal < $this->min_spend) {
            $remaining = $this->min_spend - $cartSubtotal;
            return CouponValidationResult::minSpendError(
                "Agrega L. " . number_format($remaining, 2) . " más para usar este cupón",
                (float) $this->min_spend,
                $remaining
            );
        }

        // Calculate discount
        $discountAmount = $this->calculateDiscount($cartSubtotal);

        return CouponValidationResult::success(
            discountAmount: $discountAmount,
            newTotal: $cartSubtotal - $discountAmount,
            couponCode: $this->code,
            couponType: $this->type,
            couponValue: (float) $this->value,
        );
    }

    /**
     * Calculate the discount amount
     */
    public function calculateDiscount(float $cartSubtotal): float
    {
        if ($this->type === self::TYPE_PERCENT) {
            $discount = $cartSubtotal * ($this->value / 100);
            
            // Apply max discount cap if set
            if ($this->max_discount !== null && $discount > $this->max_discount) {
                $discount = $this->max_discount;
            }
        } else {
            // Fixed amount
            $discount = $this->value;
        }

        // Never exceed subtotal
        return min($discount, $cartSubtotal);
    }

    /**
     * Increment usage count atomically with pessimistic locking.
     * 
     * CONCURRENCY SAFETY:
     * Uses lockForUpdate() to acquire exclusive lock before incrementing.
     * This implements "double-check locking" pattern:
     * 1. Lock the row exclusively
     * 2. Verify limit hasn't been reached (with fresh data)
     * 3. Increment and commit
     * 
     * This prevents "overselling" coupons during Black Friday scenarios.
     * 
     * @return bool True if increment succeeded, false if limit reached
     * @throws \RuntimeException If coupon not found
     */
    public function incrementUsage(): bool
    {
        return \Illuminate\Support\Facades\DB::transaction(function () {
            // Acquire exclusive lock on this coupon row
            $coupon = static::lockForUpdate()->find($this->id);
            
            if (!$coupon) {
                throw new \RuntimeException("Coupon {$this->id} not found during increment");
            }

            // Double-check: verify limit hasn't been reached (with locked, fresh data)
            if ($coupon->usage_limit !== null && $coupon->usage_count >= $coupon->usage_limit) {
                return false; // Coupon depleted - rollback and return false
            }

            // Safe to increment - we have exclusive lock
            $coupon->increment('usage_count');
            
            // Refresh local instance
            $this->usage_count = $coupon->usage_count;
            
            return true;
        });
    }
}
