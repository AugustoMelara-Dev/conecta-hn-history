<?php

declare(strict_types=1);

namespace App\Domain\Billing\Services;

use App\Domain\Billing\DTOs\PlanEntitlementSnapshot;
use App\Domain\TenantGovernance\Enums\Capability;
use App\Domain\TenantGovernance\Enums\TenantPlan;
use App\Models\Tenant;
use Illuminate\Support\Carbon;

final class PlanEntitlementService
{
    /** @var list<string> */
    private const LIMIT_KEYS = [
        'products',
        'services',
        'locations',
        'users',
        'ai_monthly_credits',
        'ai_daily_credits',
        'photos_processed',
        'storage_mb',
        'automations',
        'history_days',
        'channels',
    ];

    public function forTenant(Tenant $tenant): PlanEntitlementSnapshot
    {
        return $this->forPlan($this->effectivePlan($tenant));
    }

    public function forPlan(TenantPlan|string $plan): PlanEntitlementSnapshot
    {
        $plan = $this->normalizePlan($plan);
        $definition = config('subscriptions.plans.'.$plan->value);

        if (! is_array($definition)) {
            throw new \LogicException("Missing subscription definition for [{$plan->value}].");
        }

        $rawLimits = $definition['limits'] ?? null;
        if (! is_array($rawLimits)) {
            throw new \LogicException("Missing subscription limits for [{$plan->value}].");
        }

        $limits = [];
        foreach (self::LIMIT_KEYS as $key) {
            $value = $rawLimits[$key] ?? null;
            if (! is_numeric($value) || (float) $value < 0) {
                throw new \LogicException("Invalid subscription limit [{$plan->value}.{$key}].");
            }

            $limits[$key] = str_starts_with($key, 'ai_') ? (float) $value : (int) $value;
        }

        $capabilities = [];
        foreach ((array) ($definition['entitlements'] ?? []) as $value) {
            if (! is_string($value) || Capability::tryFrom($value) === null) {
                throw new \LogicException("Unknown entitlement in subscription plan [{$plan->value}].");
            }

            $capabilities[] = Capability::from($value);
        }

        $features = array_values(array_filter(
            (array) ($definition['features'] ?? []),
            static fn (mixed $feature): bool => is_string($feature) && trim($feature) !== '',
        ));

        return new PlanEntitlementSnapshot(
            plan: $plan,
            name: (string) ($definition['name'] ?? ucfirst($plan->value)),
            description: (string) ($definition['description'] ?? ''),
            priceMinor: (int) ($definition['price'] ?? 0),
            currency: strtoupper((string) ($definition['currency'] ?? 'HNL')),
            limits: $limits,
            capabilities: array_values(array_unique($capabilities, SORT_REGULAR)),
            features: $features,
        );
    }

    public function effectivePlan(Tenant $tenant): TenantPlan
    {
        $rawPlan = $tenant->getRawOriginal('plan_type') ?? $tenant->getAttribute('plan_type');
        $plan = $this->normalizePlan($rawPlan instanceof TenantPlan ? $rawPlan : (string) $rawPlan);

        if ($plan === TenantPlan::SEMILLA) {
            return $plan;
        }

        $rawStatus = $tenant->getRawOriginal('subscription_status') ?? $tenant->getAttribute('subscription_status');
        $status = (string) ($rawStatus ?: Tenant::DEFAULT_SUBSCRIPTION_STATUS);
        $endsAt = $tenant->getRawOriginal('subscription_ends_at')
            ?? $tenant->getAttribute('subscription_ends_at');

        if (! in_array($status, ['active', 'trial'], true)) {
            return TenantPlan::SEMILLA;
        }

        if ($endsAt !== null && now()->greaterThanOrEqualTo(Carbon::parse($endsAt))) {
            return TenantPlan::SEMILLA;
        }

        return $plan;
    }

    public function normalizePlan(TenantPlan|string $plan): TenantPlan
    {
        if ($plan instanceof TenantPlan) {
            return $plan;
        }

        $normalized = strtolower(trim($plan));
        $aliases = (array) config('subscriptions.legacy_plan_aliases', []);
        $normalized = (string) ($aliases[$normalized] ?? $normalized);

        return TenantPlan::tryFrom($normalized) ?? TenantPlan::SEMILLA;
    }
}
