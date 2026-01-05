<?php

namespace App\Models;

use App\DataTransferObjects\CouponValidationResult;
use App\Models\Traits\BelongsToTenant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
    use HasFactory;

    // Discount types
    public const TYPE_FIXED = 'fixed';

    public const TYPE_PERCENT = 'percent';

    public const TYPE_FREE_SHIPPING = 'free_shipping';

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
        // Scope restrictions
        'restricted_to_categories',
        'restricted_to_products',
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
        // Scope restrictions (JSON arrays)
        'restricted_to_categories' => 'array',
        'restricted_to_products' => 'array',
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
            return $this->value.'%';
        }

        return 'L. '.number_format($this->value, 2);
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
        if (! $this->is_active) {
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
     * @param  float  $cartSubtotal  The cart subtotal to validate against
     * @return CouponValidationResult Immutable validation result object
     */
    public function validate(float $cartSubtotal): CouponValidationResult
    {
        $now = Carbon::now();

        // Check if active
        if (! $this->is_active) {
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
                'Agrega L. '.number_format($remaining, 2).' más para usar este cupón',
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
     * Calculate the discount amount based on coupon type.
     *
     * PRECISION: All calculations use round() with 2 decimal places
     * to prevent floating-point rounding errors.
     *
     * NOTE: Includes safety checks. For pure math without validation,
     * use calculateDiscountUnsafe() instead.
     *
     * TYPES:
     * - percent: ($subtotal * $value) / 100, capped by max_discount
     * - fixed: Returns $value, but never exceeds $subtotal
     * - free_shipping: Returns $shippingCost exactly
     *
     * @param  float  $cartSubtotal  The cart subtotal
     * @param  float  $shippingCost  The shipping cost (for free_shipping type)
     * @return float The calculated discount amount
     */
    public function calculateDiscount(float $cartSubtotal, float $shippingCost = 0.0): float
    {
        // Safety check: If coupon is not valid, return 0
        if (! $this->isCurrentlyValid()) {
            return 0.0;
        }

        return $this->calculateDiscountUnsafe($cartSubtotal, $shippingCost);
    }

    /**
     * Check if coupon is currently valid (active + within date range).
     *
     * Separated from calculateDiscount for single responsibility.
     */
    public function isCurrentlyValid(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $now = Carbon::now();

        if ($this->starts_at && $now->lt($this->starts_at)) {
            return false;
        }

        if ($this->expires_at && $now->gt($this->expires_at)) {
            return false;
        }

        return true;
    }

    /**
     * Calculate discount without validation checks.
     *
     * USE CASE: When you've already validated the coupon (e.g., in validate())
     * and need pure math calculation.
     *
     * @param  float  $cartSubtotal  The cart subtotal
     * @param  float  $shippingCost  The shipping cost (for free_shipping type)
     * @return float The calculated discount amount
     */
    public function calculateDiscountUnsafe(float $cartSubtotal, float $shippingCost = 0.0): float
    {
        $discount = match ($this->type) {
            self::TYPE_PERCENT => $this->calculatePercentDiscount($cartSubtotal),
            self::TYPE_FIXED => $this->calculateFixedDiscount($cartSubtotal),
            self::TYPE_FREE_SHIPPING => round((float) $shippingCost, 2),
            default => 0.0,
        };

        return round($discount, 2);
    }

    /**
     * Calculate percentage discount with optional cap.
     */
    private function calculatePercentDiscount(float $cartSubtotal): float
    {
        $discount = ($cartSubtotal * (float) $this->value) / 100;
        $discount = round($discount, 2);

        // Apply max discount cap if set
        if ($this->max_discount !== null && $discount > (float) $this->max_discount) {
            $discount = (float) $this->max_discount;
        }

        // Never exceed subtotal
        return min($discount, $cartSubtotal);
    }

    /**
     * Calculate fixed amount discount.
     * CRITICAL: Never exceed the subtotal to prevent negative totals.
     */
    private function calculateFixedDiscount(float $cartSubtotal): float
    {
        return min((float) $this->value, $cartSubtotal);
    }

    /**
     * Calculate discount with item-level context awareness.
     *
     * ╔══════════════════════════════════════════════════════════════════════════╗
     * ║  CONTEXT-AWARE DISCOUNT: Only eligible items contribute to discount     ║
     * ╚══════════════════════════════════════════════════════════════════════════╝
     *
     * STRATEGY:
     * 1. Iterate through cart items
     * 2. Check each item against coupon restrictions
     * 3. Sum only eligible item prices (the "Discountable Base")
     * 4. Apply discount formula to this filtered sum
     *
     * RESTRICTION RULES:
     * - If both restricted_to_categories AND restricted_to_products are null: ALL items eligible
     * - If restricted_to_products is set: ONLY those specific products are eligible
     * - If restricted_to_categories is set: ONLY products in those categories are eligible
     * - If BOTH are set: Product must match EITHER restriction (OR logic)
     *
     * @param  array  $cartItems  Array of items with product_id, category_id (or menu_section_id), and line_total
     * @return array ['eligible_subtotal' => float, 'discount' => float, 'ineligible_subtotal' => float]
     */
    public function calculateDiscountWithContext(array $cartItems): array
    {
        $restrictedCategories = $this->restricted_to_categories;
        $restrictedProducts = $this->restricted_to_products;

        // If no restrictions, all items are eligible
        $hasRestrictions = ! empty($restrictedCategories) || ! empty($restrictedProducts);

        $eligibleSubtotal = 0.0;
        $ineligibleSubtotal = 0.0;

        foreach ($cartItems as $item) {
            $itemTotal = (float) ($item['line_total'] ?? ($item['unit_price'] ?? 0) * ($item['quantity'] ?? 1));
            $productId = $item['product_id'] ?? null;
            $categoryId = $item['category_id'] ?? $item['menu_section_id'] ?? null;

            // Custom items (no product_id) are never eligible for restricted coupons
            if ($hasRestrictions && ! $productId) {
                $ineligibleSubtotal += $itemTotal;

                continue;
            }

            // Check eligibility
            $isEligible = false;

            if (! $hasRestrictions) {
                // No restrictions = all items eligible
                $isEligible = true;
            } else {
                // Check product restriction
                $productMatch = ! empty($restrictedProducts) && in_array($productId, $restrictedProducts);

                // Check category restriction
                $categoryMatch = ! empty($restrictedCategories) && in_array($categoryId, $restrictedCategories);

                // OR logic: eligible if ANY restriction matches
                $isEligible = $productMatch || $categoryMatch;
            }

            if ($isEligible) {
                $eligibleSubtotal += $itemTotal;
            } else {
                $ineligibleSubtotal += $itemTotal;
            }
        }

        // Calculate discount based ONLY on eligible items
        $discount = 0.0;

        if ($eligibleSubtotal > 0) {
            if ($this->type === self::TYPE_PERCENT) {
                $discount = $eligibleSubtotal * ((float) $this->value / 100);

                // Apply max discount cap if set
                if ($this->max_discount !== null && $discount > $this->max_discount) {
                    $discount = (float) $this->max_discount;
                }
            } else {
                // Fixed amount - applies fully if eligible subtotal covers it
                $discount = min((float) $this->value, $eligibleSubtotal);
            }
        }

        return [
            'eligible_subtotal' => round($eligibleSubtotal, 2),
            'ineligible_subtotal' => round($ineligibleSubtotal, 2),
            'discount' => round($discount, 2),
        ];
    }

    /**
     * Check if this coupon has any product/category restrictions.
     */
    public function hasRestrictions(): bool
    {
        return ! empty($this->restricted_to_categories) || ! empty($this->restricted_to_products);
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
     *
     * @throws \RuntimeException If coupon not found
     */
    public function incrementUsage(): bool
    {
        return \Illuminate\Support\Facades\DB::transaction(function () {
            // Acquire exclusive lock on this coupon row
            $coupon = static::lockForUpdate()->find($this->id);

            if (! $coupon) {
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
