<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

use App\Domain\TenantGovernance\Enums\TenantPlan;
use App\Models\Tenant;

// use App\Models\Tenant; // REMOVED

/**
 * PlanFeatureFlagService - Domain Service for SaaS Plan Capabilities
 *
 * â•”â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•—
 * â•‘  PURE DOMAIN SERVICE: Zero framework dependencies                        â•‘
 * â• â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•£
 * â•‘  This service encapsulates ALL business rules related to SaaS plans.    â•‘
 * â•‘  It answers questions like:                                              â•‘
 * â•‘  - "Can this tenant show reviews?"                                       â•‘
 * â•‘  - "How many products can they display?"                                 â•‘
 * â•‘  - "Are modifier groups enabled?"                                        â•‘
 * â•‘                                                                           â•‘
 * â•‘  PHILOSOPHY: Single source of truth for plan capabilities.               â•‘
 * â•‘  If a rule changes (e.g., Semilla can now show 15 products), change it  â•‘
 * â•‘  HERE, not in 50 scattered files.                                         â•‘
 * â•šâ•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
 *
 * @see \App\Infrastructure\Tenant\Transformers\TenantMicrositeTransformer - Uses this service
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
     * - plan_type = semilla → Free tier, stateless WhatsApp
     * - plan_type = pro/crecimiento/cosecha → Paid tier
     * - Future: enterprise based on subscription table
     *
     * @return self::PLAN_SEMILLA|self::PLAN_PRO|self::PLAN_ENTERPRISE
     */
    public function determinePlan(object $tenant): string
    {
        // Future: Check subscription relationship for enterprise tier
        // if ($tenant->subscription?->plan === 'enterprise') {
        //     return self::PLAN_ENTERPRISE;
        // }

        $planKey = method_exists($tenant, 'getRawOriginal')
            ? ($tenant->getRawOriginal('plan_type') ?: ($tenant->getAttributes()['plan_type'] ?? null))
            : ($tenant->plan_type ?? null);

        $value = match (true) {
            $planKey instanceof TenantPlan => $planKey->value,
            is_string($planKey) && $planKey !== '' => $planKey,
            default => self::PLAN_SEMILLA,
        };

        return match ($value) {
            Tenant::PLAN_CRECIMIENTO,
            Tenant::PLAN_COSECHA,
            self::PLAN_PRO => self::PLAN_PRO,
            self::PLAN_ENTERPRISE => self::PLAN_ENTERPRISE,
            default => self::PLAN_SEMILLA,
        };
    }

    /**
     * Check if tenant is on the free (Semilla) plan.
     */
    public function isSemilla(object $tenant): bool
    {
        return $this->determinePlan($tenant) === self::PLAN_SEMILLA;
    }

    /**
     * Check if tenant is on the Pro plan.
     */
    public function isPro(object $tenant): bool
    {
        return $this->determinePlan($tenant) === self::PLAN_PRO;
    }

    /**
     * Check if tenant is on the Enterprise plan.
     */
    public function isEnterprise(object $tenant): bool
    {
        return $this->determinePlan($tenant) === self::PLAN_ENTERPRISE;
    }

    /**
     * Check if tenant can show product modifiers.
     *
     * BUSINESS RULE: Modifiers are a Pro feature.
     * Semilla tenants NEVER see modifiers (even if data exists in DB).
     */
    public function canShowModifiers(object $tenant): bool
    {
        return ! $this->isSemilla($tenant);
    }

    /**
     * Check if tenant can show customer reviews.
     *
     * BUSINESS RULE: Reviews are a Pro feature.
     */
    public function canShowReviews(object $tenant): bool
    {
        return ! $this->isSemilla($tenant);
    }

    /**
     * Check if tenant can show social media graph (followers, following).
     *
     * BUSINESS RULE: Social features are Pro+ only.
     */
    public function canShowSocialGraph(object $tenant): bool
    {
        return ! $this->isSemilla($tenant);
    }

    /**
     * Check if tenant can use the stateful cart.
     *
     * BUSINESS RULE: Cart is Pro feature. Semilla uses WhatsApp only.
     */
    public function canUseCart(object $tenant): bool
    {
        return ! $this->isSemilla($tenant);
    }

    /**
     * Check if tenant can access analytics dashboard.
     *
     * BUSINESS RULE: Analytics are Pro+ feature.
     */
    public function canAccessAnalytics(object $tenant): bool
    {
        return ! $this->isSemilla($tenant);
    }

    /**
     * Check if tenant can upload custom hero image.
     *
     * BUSINESS RULE: Custom branding is Pro feature.
     */
    public function canCustomizeHero(object $tenant): bool
    {
        return ! $this->isSemilla($tenant);
    }

    /**
     * Check if tenant can show coupon/discount functionality.
     *
     * BUSINESS RULE: Coupons are Pro feature.
     */
    public function canUseCoupons(object $tenant): bool
    {
        return ! $this->isSemilla($tenant);
    }

    /**
     * Get maximum product limit for tenant's plan.
     *
     * @return int Maximum number of visible products
     */
    public function getMaxProductLimit(object $tenant): int
    {
        // If tenant has custom limit set, use that (for upgrades/promotions)
        if ($tenant->product_limit !== null) {
            return $tenant->product_limit;
        }

        $plan = $this->determinePlan($tenant);

        return self::PRODUCT_LIMITS[$plan];
    }

    /**
     * Get maximum menu sections for tenant's plan.
     */
    public function getMaxMenuSections(object $tenant): int
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
    public function getMaxMediaUploads(object $tenant): int
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
