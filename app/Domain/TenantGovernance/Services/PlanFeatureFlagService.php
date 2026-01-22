<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

use App\Models\Tenant;

/**
 * PlanFeatureFlagService - Domain Service for SaaS Plan Capabilities
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  PURE DOMAIN SERVICE: Zero framework dependencies                        ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║  This service encapsulates ALL business rules related to SaaS plans.    ║
 * ║  It answers questions like:                                              ║
 * ║  - "Can this tenant show reviews?"                                       ║
 * ║  - "How many products can they display?"                                 ║
 * ║  - "Are modifier groups enabled?"                                        ║
 * ║                                                                           ║
 * ║  PHILOSOPHY: Single source of truth for plan capabilities.               ║
 * ║  If a rule changes (e.g., Semilla can now show 15 products), change it  ║
 * ║  HERE, not in 50 scattered files.                                         ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * @see \App\Application\Tenant\Transformers\TenantMicrositeTransformer - Uses this service
 */
final class PlanFeatureFlagService
{
    /**
     * Plan tier constants.
     */
    public const PLAN_SEMILLA = 'semilla';

    public const PLAN_PRO = 'pro';

    public const PLAN_ENTERPRISE = 'enterprise';

    /**
     * Default product limits by plan.
     */
    private const PRODUCT_LIMITS = [
        self::PLAN_SEMILLA => 12,
        self::PLAN_PRO => 100,
        self::PLAN_ENTERPRISE => PHP_INT_MAX,
    ];

    /**
     * Determine the tenant's subscription plan.
     *
     * Business Logic:
     * - is_pro = false → 'semilla' (Free tier, stateless WhatsApp)
     * - is_pro = true  → 'pro' (Paid tier, stateful cart)
     * - Future: enterprise based on subscription table
     */
    public function determinePlan(Tenant $tenant): string
    {
        // Future: Check subscription relationship for enterprise tier
        // if ($tenant->subscription?->plan === 'enterprise') {
        //     return self::PLAN_ENTERPRISE;
        // }

        return $tenant->is_pro ? self::PLAN_PRO : self::PLAN_SEMILLA;
    }

    /**
     * Check if tenant is on the free (Semilla) plan.
     */
    public function isSemilla(Tenant $tenant): bool
    {
        return $this->determinePlan($tenant) === self::PLAN_SEMILLA;
    }

    /**
     * Check if tenant is on the Pro plan.
     */
    public function isPro(Tenant $tenant): bool
    {
        return $this->determinePlan($tenant) === self::PLAN_PRO;
    }

    /**
     * Check if tenant is on the Enterprise plan.
     */
    public function isEnterprise(Tenant $tenant): bool
    {
        return $this->determinePlan($tenant) === self::PLAN_ENTERPRISE;
    }

    /**
     * Check if tenant can show product modifiers.
     *
     * BUSINESS RULE: Modifiers are a Pro feature.
     * Semilla tenants NEVER see modifiers (even if data exists in DB).
     */
    public function canShowModifiers(Tenant $tenant): bool
    {
        return ! $this->isSemilla($tenant);
    }

    /**
     * Check if tenant can show customer reviews.
     *
     * BUSINESS RULE: Reviews are a Pro feature.
     */
    public function canShowReviews(Tenant $tenant): bool
    {
        return ! $this->isSemilla($tenant);
    }

    /**
     * Check if tenant can show social media graph (followers, following).
     *
     * BUSINESS RULE: Social features are Pro+ only.
     */
    public function canShowSocialGraph(Tenant $tenant): bool
    {
        return ! $this->isSemilla($tenant);
    }

    /**
     * Check if tenant can use the stateful cart.
     *
     * BUSINESS RULE: Cart is Pro feature. Semilla uses WhatsApp only.
     */
    public function canUseCart(Tenant $tenant): bool
    {
        return ! $this->isSemilla($tenant);
    }

    /**
     * Check if tenant can access analytics dashboard.
     *
     * BUSINESS RULE: Analytics are Pro+ feature.
     */
    public function canAccessAnalytics(Tenant $tenant): bool
    {
        return ! $this->isSemilla($tenant);
    }

    /**
     * Check if tenant can upload custom hero image.
     *
     * BUSINESS RULE: Custom branding is Pro feature.
     */
    public function canCustomizeHero(Tenant $tenant): bool
    {
        return ! $this->isSemilla($tenant);
    }

    /**
     * Check if tenant can show coupon/discount functionality.
     *
     * BUSINESS RULE: Coupons are Pro feature.
     */
    public function canUseCoupons(Tenant $tenant): bool
    {
        return ! $this->isSemilla($tenant);
    }

    /**
     * Get maximum product limit for tenant's plan.
     *
     * @return int Maximum number of visible products
     */
    public function getMaxProductLimit(Tenant $tenant): int
    {
        // If tenant has custom limit set, use that (for upgrades/promotions)
        if ($tenant->product_limit !== null) {
            return $tenant->product_limit;
        }

        $plan = $this->determinePlan($tenant);

        return self::PRODUCT_LIMITS[$plan] ?? self::PRODUCT_LIMITS[self::PLAN_SEMILLA];
    }

    /**
     * Get maximum menu sections for tenant's plan.
     */
    public function getMaxMenuSections(Tenant $tenant): int
    {
        return match ($this->determinePlan($tenant)) {
            self::PLAN_SEMILLA => 3,
            self::PLAN_PRO => 20,
            self::PLAN_ENTERPRISE => PHP_INT_MAX,
        };
    }

    /**
     * Get maximum media uploads (images) for tenant's plan.
     */
    public function getMaxMediaUploads(Tenant $tenant): int
    {
        return match ($this->determinePlan($tenant)) {
            self::PLAN_SEMILLA => 5,
            self::PLAN_PRO => 50,
            self::PLAN_ENTERPRISE => PHP_INT_MAX,
        };
    }

    /**
     * Format phone number with Honduras country code.
     *
     * PURE FUNCTION: No side effects.
     */
    public function formatPhoneWithCountryCode(?string $phone): ?string
    {
        if (empty($phone)) {
            return null;
        }

        // Strip non-numeric characters
        $cleaned = preg_replace('/[^0-9]/', '', $phone);

        // If 8 digits, add Honduras prefix
        if (strlen($cleaned) === 8) {
            return '+504'.$cleaned;
        }

        // If already has country code, ensure + prefix
        if (strlen($cleaned) > 8 && ! str_starts_with($phone, '+')) {
            return '+'.$cleaned;
        }

        return $phone;
    }
}
