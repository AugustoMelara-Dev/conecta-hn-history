<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Identity\Entities\UserEntity;
use App\Domain\TenantGovernance\Entities\TenantEntity;
use App\Exceptions\InvalidCouponException;
use App\Models\Coupon;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
 * 6. STRICT CONTRACTS: Validation runs with explicit user and tenant context
 *
 * USAGE:
 *   $service = app(CouponValidatorService::class);
 *   $coupon = $service->validate('SAVE10', $user, 150.00, $tenant);
 *   $service->redeem($coupon, $user, $orderId);
 */
final class CouponValidatorService implements \App\Application\Shared\Contracts\CouponValidatorInterface
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
     * Validate a coupon code for a user and cart total.
     *
     * @param  string  $code  Coupon code (case-insensitive)
     * @param  UserEntity|User  $user  User attempting to use the coupon
     * @param  float  $cartTotal  Current cart subtotal
     * @param  TenantEntity|Tenant  $tenant  Tenant context for coupon isolation
     * @return Coupon Valid coupon ready for redemption
     *
     * @throws InvalidCouponException If coupon is invalid for any reason
     */
    public function validate(string $code, UserEntity|User $user, float $cartTotal, TenantEntity|Tenant $tenant): Coupon
    {
        $tenantId = $this->resolveTenantId($tenant);

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
            'user_id' => $this->resolveUserId($user),
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
     * CRITICAL: This method MUST use DB::transaction() and lockForUpdate()
     * to prevent race conditions during high-traffic checkout scenarios.
     *
     * @param  Coupon  $coupon  Previously validated coupon
     * @param  UserEntity|User  $user  User redeeming the coupon
     * @param  int|null  $orderId  Order ID (null during checkout, set on payment)
     *
     * @throws InvalidCouponException If coupon depleted during lock acquisition
     * @throws \Throwable On transaction failure
     */
    public function redeem(Coupon $coupon, UserEntity|User $user, ?int $orderId = null): void
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

            if ($this->hasCouponUserTable()) {
                $maxUsesPerUser = $this->getMaxUsesPerUser($lockedCoupon);
                $userId = $this->resolveUserId($user);

                $userUsageCount = DB::table('coupon_user')
                    ->where('coupon_id', $lockedCoupon->id)
                    ->where('user_id', $userId)
                    ->count();

                if ($userUsageCount >= $maxUsesPerUser) {
                    throw InvalidCouponException::userLimitReached($lockedCoupon->code);
                }

                // Record per-user redemption in pivot table
                DB::table('coupon_user')->insert([
                    'coupon_id' => $lockedCoupon->id,
                    'user_id' => $userId,
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
                'user_id' => $this->resolveUserId($user),
                'order_id' => $orderId,
                'new_usage_count' => $lockedCoupon->usage_count,
            ]);
        });
    }

    /**
     * Link a pending redemption to a completed order.
     *
     * @param  Coupon  $coupon  The redeemed coupon
     * @param  UserEntity|User  $user  The user who redeemed it
     * @param  int  $orderId  The completed order ID
     */
    public function linkToOrder(Coupon $coupon, UserEntity|User $user, int $orderId): void
    {
        if (! $this->hasCouponUserTable()) {
            return;
        }

        $userId = $this->resolveUserId($user);

        DB::table('coupon_user')
            ->where('coupon_id', $coupon->id)
            ->where('user_id', $userId)
            ->whereNull('order_id')
            ->orderByDesc('redeemed_at')
            ->limit(1)
            ->update(['order_id' => $orderId]);
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // TENANT RESOLUTION - DEFENSIVE (GUEST COMPATIBLE)
    // ═══════════════════════════════════════════════════════════════════════════════

    private function resolveTenantId(TenantEntity|Tenant $tenant): string
    {
        if ($tenant instanceof TenantEntity) {
            return $tenant->id();
        }

        return (string) $tenant->getKey();
    }

    private function resolveUserId(UserEntity|User $user): string
    {
        return $user instanceof UserEntity ? $user->id() : (string) $user->getKey();
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // SCHEMA HELPERS - DEFENSIVE PROGRAMMING
    // ═══════════════════════════════════════════════════════════════════════════════

    /**
     * Check if coupon_user tracking is enabled.
     *
     * Optimization: Runtime Schema::hasTable() removed for production performance.
     * Schema state is deterministic after migrations. Use config toggle instead.
     */
    private function hasCouponUserTable(): bool
    {
        if (self::$hasCouponUserTable === null) {
            self::$hasCouponUserTable = (bool) config('conecta.features.coupon_user_tracking', true);
        }

        return self::$hasCouponUserTable;
    }

    /**
     * Check if max_uses_per_user column exists on coupons table.
     *
     * Optimization: Runtime Schema::hasColumn() removed for production performance.
     * Column presence is deterministic after migrations.
     */
    private function hasMaxUsesPerUserColumn(): bool
    {
        if (self::$hasMaxUsesPerUserColumn === null) {
            self::$hasMaxUsesPerUserColumn = true;
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
     * @throws InvalidCouponException
     */
    private function validatePerUserUsage(Coupon $coupon, UserEntity|User $user): void
    {
        $maxUsesPerUser = $this->getMaxUsesPerUser($coupon);

        if ($maxUsesPerUser === 0) {
            return;
        }

        $userUsageCount = DB::table('coupon_user')
            ->where('coupon_id', $coupon->id)
            ->where('user_id', $this->resolveUserId($user))
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
