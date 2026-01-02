<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\InvalidCouponException;
use App\Models\Coupon;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * CouponValidatorService - Race-Condition-Safe Coupon Validation & Redemption
 *
 * CRITICAL SECURITY PATTERNS:
 * ════════════════════════════════════════════════════════════════════════════
 * 1. PESSIMISTIC LOCKING: Uses lockForUpdate() to prevent overselling
 * 2. PER-USER LIMITS: Tracks individual user redemptions via coupon_user pivot
 * 3. TIMEZONE COMPLIANCE: All datetime checks use America/Tegucigalpa
 * 4. ATOMIC REDEMPTION: DB::transaction() ensures integrity
 * 5. DEFENSIVE CHECKS: Schema verification for optional columns
 * 6. GUEST SUPPORT: Null-safe user handling for anonymous checkout
 *
 * USAGE:
 *   $service = app(CouponValidatorService::class);
 *   $coupon = $service->validate('SAVE10', $user, 150.00, $tenant);
 *   $service->redeem($coupon, $user, $orderId);
 */
final class CouponValidatorService
{
    /** @var string Honduras timezone for all datetime operations */
    private const TIMEZONE = 'America/Tegucigalpa';

    /** @var int Default max uses per user if column doesn't exist (0 = guest-friendly) */
    private const DEFAULT_MAX_USES_PER_USER = 0;

    /** @var bool Cache for schema check (performance optimization) */
    private static ?bool $hasMaxUsesPerUserColumn = null;

    /** @var bool Cache for coupon_user table existence */
    private static ?bool $hasCouponUserTable = null;

    // ═══════════════════════════════════════════════════════════════════════════════
    // VALIDATION - GUEST COMPATIBLE
    // ═══════════════════════════════════════════════════════════════════════════════

    /**
     * Validate a coupon code for a user (or guest) and cart total.
     *
     * GUEST SUPPORT: User can be null for anonymous checkout flows.
     * Per-user limits are handled defensively - if a coupon requires
     * per-user tracking and user is null, validation fails with REQUIRE_LOGIN.
     *
     * @param  string  $code  Coupon code (case-insensitive)
     * @param  User|null  $user  User attempting to use the coupon (null for guests)
     * @param  float  $cartTotal  Current cart subtotal
     * @param  Tenant|null  $tenant  Tenant context (required if user is null)
     * @return Coupon Valid coupon ready for redemption
     *
     * @throws InvalidCouponException If coupon is invalid for any reason
     */
    public function validate(string $code, ?User $user, float $cartTotal, ?Tenant $tenant = null): Coupon
    {
        // Resolve tenant ID (defensive: from user or explicit tenant)
        $tenantId = $this->resolveTenantId($user, $tenant);

        // STEP 1: Find coupon by code (case-insensitive via model boot)
        $coupon = Coupon::where('code', strtoupper(trim($code)))
            ->where('tenant_id', $tenantId)
            ->first();

        if ($coupon === null) {
            throw InvalidCouponException::notFound($code);
        }

        // STEP 2: Check active status
        if (! $coupon->is_active) {
            throw InvalidCouponException::inactive($coupon->code);
        }

        // STEP 3: Timezone-aware date validation
        $this->validateDateRange($coupon);

        // STEP 4: Global usage limit check
        $this->validateGlobalUsage($coupon);

        // STEP 5: Per-user usage limit check (if table exists)
        if ($this->hasCouponUserTable()) {
            $this->validatePerUserUsage($coupon, $user);
        }

        // STEP 6: Minimum order amount check
        $this->validateMinimumAmount($coupon, $cartTotal);

        Log::info('CouponValidatorService: Validation passed', [
            'context' => 'coupon_validated',
            'coupon_id' => $coupon->id,
            'coupon_code' => $coupon->code,
            'user_id' => $user?->id,
            'is_guest' => $user === null,
            'tenant_id' => $tenantId,
            'cart_total' => $cartTotal,
        ]);

        return $coupon;
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // REDEMPTION - RACE CONDITION PROTECTED, GUEST COMPATIBLE
    // ═══════════════════════════════════════════════════════════════════════════════

    /**
     * Redeem a coupon atomically with pessimistic locking.
     *
     * GUEST SUPPORT: If user is null, skips coupon_user pivot insertion
     * but still increments global usage_count to track inventory.
     *
     * CRITICAL: This method MUST use DB::transaction() and lockForUpdate()
     * to prevent race conditions during high-traffic checkout scenarios.
     *
     * @param  Coupon  $coupon  Previously validated coupon
     * @param  User|null  $user  User redeeming the coupon (null for guests)
     * @param  int|null  $orderId  Order ID (null during checkout, set on payment)
     *
     * @throws InvalidCouponException If coupon depleted during lock acquisition
     * @throws \Throwable On transaction failure
     */
    public function redeem(Coupon $coupon, ?User $user = null, ?int $orderId = null): void
    {
        DB::transaction(function () use ($coupon, $user, $orderId) {
            // STEP 1: Acquire exclusive lock on coupon row
            $lockedCoupon = Coupon::lockForUpdate()->find($coupon->id);

            if ($lockedCoupon === null) {
                throw InvalidCouponException::notFound($coupon->code);
            }

            // STEP 2: Re-validate global limit with locked fresh data
            if ($lockedCoupon->usage_limit !== null &&
                $lockedCoupon->usage_count >= $lockedCoupon->usage_limit) {
                throw InvalidCouponException::depleted($lockedCoupon->code);
            }

            // STEP 3: Per-user tracking (ONLY if user is authenticated)
            if ($user !== null && $this->hasCouponUserTable()) {
                $maxUsesPerUser = $this->getMaxUsesPerUser($lockedCoupon);

                $userUsageCount = DB::table('coupon_user')
                    ->where('coupon_id', $lockedCoupon->id)
                    ->where('user_id', $user->id)
                    ->count();

                if ($userUsageCount >= $maxUsesPerUser) {
                    throw InvalidCouponException::userLimitReached($lockedCoupon->code);
                }

                // Record per-user redemption in pivot table
                DB::table('coupon_user')->insert([
                    'coupon_id' => $lockedCoupon->id,
                    'user_id' => $user->id,
                    'order_id' => $orderId,
                    'redeemed_at' => CarbonImmutable::now(self::TIMEZONE),
                ]);
            }

            // STEP 4: Increment global usage counter (ALWAYS, even for guests)
            $lockedCoupon->increment('usage_count');

            Log::info('CouponValidatorService: Coupon redeemed', [
                'context' => 'coupon_redeemed',
                'coupon_id' => $lockedCoupon->id,
                'coupon_code' => $lockedCoupon->code,
                'user_id' => $user?->id,
                'is_guest' => $user === null,
                'order_id' => $orderId,
                'new_usage_count' => $lockedCoupon->usage_count,
            ]);
        });
    }

    /**
     * Link a pending redemption to a completed order.
     *
     * @param  Coupon  $coupon  The redeemed coupon
     * @param  User|null  $user  The user who redeemed it (null for guests - no-op)
     * @param  int  $orderId  The completed order ID
     */
    public function linkToOrder(Coupon $coupon, ?User $user, int $orderId): void
    {
        // Guests don't have pivot records to update
        if ($user === null) {
            return;
        }

        if (! $this->hasCouponUserTable()) {
            return;
        }

        DB::table('coupon_user')
            ->where('coupon_id', $coupon->id)
            ->where('user_id', $user->id)
            ->whereNull('order_id')
            ->orderByDesc('redeemed_at')
            ->limit(1)
            ->update(['order_id' => $orderId]);
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // TENANT RESOLUTION - DEFENSIVE (GUEST COMPATIBLE)
    // ═══════════════════════════════════════════════════════════════════════════════

    /**
     * Resolve tenant ID from user or explicit tenant using multiple fallback strategies.
     *
     * GUEST SUPPORT: When user is null, requires explicit $tenant parameter.
     *
     * @throws InvalidCouponException If no tenant context can be determined
     */
    private function resolveTenantId(?User $user, ?Tenant $tenant = null): int
    {
        // Strategy 0: Explicit tenant parameter (highest priority for guests)
        if ($tenant !== null) {
            return (int) $tenant->id;
        }

        // Strategy 1: Filament context (works regardless of user)
        if (class_exists(\Filament\Facades\Filament::class)) {
            try {
                $filamentTenant = \Filament\Facades\Filament::getTenant();
                if ($filamentTenant !== null) {
                    return (int) $filamentTenant->id;
                }
            } catch (\Throwable) {
                // Filament not initialized
            }
        }

        // Strategy 2: App container binding (set by TenantMiddleware)
        if (app()->has('currentTenant')) {
            $containerTenant = app('currentTenant');
            if ($containerTenant !== null) {
                return (int) $containerTenant->id;
            }
        }

        // Strategy 3: User-based resolution (only if user is present)
        if ($user !== null) {
            // Direct tenant_id attribute on User
            $tenantId = $user->getAttribute('tenant_id');
            if ($tenantId !== null) {
                return (int) $tenantId;
            }

            // User has tenants() relationship
            if (method_exists($user, 'tenants')) {
                $tenantId = $user->tenants()->value('id');
                if ($tenantId !== null) {
                    return (int) $tenantId;
                }
            }

            // User has tenant() singular relationship
            if (method_exists($user, 'tenant') && $user->tenant !== null) {
                return (int) $user->tenant->id;
            }
        }

        throw InvalidCouponException::noTenantContext();
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // SCHEMA HELPERS - DEFENSIVE PROGRAMMING
    // ═══════════════════════════════════════════════════════════════════════════════

    /**
     * Check if coupon_user pivot table exists.
     */
    private function hasCouponUserTable(): bool
    {
        if (self::$hasCouponUserTable === null) {
            self::$hasCouponUserTable = Schema::hasTable('coupon_user');
        }

        return self::$hasCouponUserTable;
    }

    /**
     * Check if max_uses_per_user column exists on coupons table.
     */
    private function hasMaxUsesPerUserColumn(): bool
    {
        if (self::$hasMaxUsesPerUserColumn === null) {
            self::$hasMaxUsesPerUserColumn = Schema::hasColumn('coupons', 'max_uses_per_user');
        }

        return self::$hasMaxUsesPerUserColumn;
    }

    /**
     * Get max uses per user from coupon (with fallback).
     */
    private function getMaxUsesPerUser(Coupon $coupon): int
    {
        if ($this->hasMaxUsesPerUserColumn() && isset($coupon->max_uses_per_user)) {
            return (int) $coupon->max_uses_per_user;
        }

        return self::DEFAULT_MAX_USES_PER_USER;
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // PRIVATE VALIDATION HELPERS
    // ═══════════════════════════════════════════════════════════════════════════════

    /**
     * Validate coupon date range using Honduras timezone.
     *
     * @throws InvalidCouponException
     */
    private function validateDateRange(Coupon $coupon): void
    {
        $now = CarbonImmutable::now(self::TIMEZONE);

        if ($coupon->starts_at !== null && $now->isBefore($coupon->starts_at)) {
            throw InvalidCouponException::notStarted($coupon->code);
        }

        if ($coupon->expires_at !== null && $now->isAfter($coupon->expires_at)) {
            throw InvalidCouponException::expired($coupon->code);
        }
    }

    /**
     * Validate global usage limit.
     *
     * @throws InvalidCouponException
     */
    private function validateGlobalUsage(Coupon $coupon): void
    {
        if ($coupon->usage_limit === null) {
            return; // Unlimited
        }

        if ($coupon->usage_count >= $coupon->usage_limit) {
            throw InvalidCouponException::depleted($coupon->code);
        }
    }

    /**
     * Validate per-user usage limit.
     *
     * GUEST HANDLING:
     * - If user is null AND coupon has per-user limits, REQUIRE LOGIN
     * - If user is null AND coupon has NO per-user limits, ALLOW (guest-friendly)
     *
     * @throws InvalidCouponException
     */
    private function validatePerUserUsage(Coupon $coupon, ?User $user): void
    {
        $maxUsesPerUser = $this->getMaxUsesPerUser($coupon);

        // GUEST GUARD: If coupon has per-user limits and user is null, require login
        if ($user === null) {
            // Check if this coupon actually has per-user restrictions
            if ($maxUsesPerUser > 0) {
                throw InvalidCouponException::requireLogin();
            }

            // No per-user limits, guests can proceed
            return;
        }

        // If max_uses_per_user is 0, treat as unlimited for authenticated users
        if ($maxUsesPerUser === 0) {
            return; // No per-user limit
        }

        // Authenticated user: check actual usage
        $userUsageCount = DB::table('coupon_user')
            ->where('coupon_id', $coupon->id)
            ->where('user_id', $user->id)
            ->count();

        if ($userUsageCount >= $maxUsesPerUser) {
            throw InvalidCouponException::userLimitReached($coupon->code);
        }
    }

    /**
     * Validate minimum order amount.
     *
     * @throws InvalidCouponException
     */
    private function validateMinimumAmount(Coupon $coupon, float $cartTotal): void
    {
        if ($coupon->min_spend === null) {
            return; // No minimum
        }

        if ($cartTotal < (float) $coupon->min_spend) {
            throw InvalidCouponException::minSpendNotMet((float) $coupon->min_spend, $cartTotal);
        }
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // TESTING HELPERS
    // ═══════════════════════════════════════════════════════════════════════════════

    /**
     * Reset schema cache (for testing).
     */
    public static function resetSchemaCache(): void
    {
        self::$hasMaxUsesPerUserColumn = null;
        self::$hasCouponUserTable = null;
    }
}
