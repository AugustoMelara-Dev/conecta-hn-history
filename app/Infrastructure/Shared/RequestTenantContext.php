<?php

declare(strict_types=1);

namespace App\Infrastructure\Shared;

use App\Domain\Shared\Contracts\TenantContext;
use App\Domain\Shared\ValueObjects\TenantId;
use App\Models\Tenant;
use Illuminate\Http\Request;

/**
 * RequestTenantContext - Infrastructure Implementation
 *
 * RESPONSIBILITY:
 * Resolve Tenant ID from the current HTTP Request/User.
 */
final class RequestTenantContext implements TenantContext
{
    public function __construct(
        private readonly Request $request
    ) {}

    /**
     * {@inheritDoc}
     */
    public function getTenantId(): string
    {
        // Try resolving from authenticated user
        // We use dynamic property access to avoid hard import of User model class
        // but strictly check for existence.

        $user = $this->request->user();

        if (! $user) {
            throw new \RuntimeException('Tenant Context Error: User not authenticated.');
        }

        $tenantId = $user->tenant_id ?? null;

        if (! $tenantId) {
            throw new \RuntimeException('Tenant Context Error: User has no associated tenant.');
        }

        if (is_object($tenantId)) {
            if (method_exists($tenantId, 'toString')) {
                return TenantId::fromString(strtolower($tenantId->toString()))->toString();
            }

            if (property_exists($tenantId, 'value') && is_string($tenantId->value)) {
                return TenantId::fromString(strtolower($tenantId->value))->toString();
            }

            if (method_exists($tenantId, '__toString')) {
                return TenantId::fromString(strtolower((string) $tenantId))->toString();
            }

            throw new \RuntimeException('Tenant Context Error: Tenant identifier object cannot be converted.');
        }

        return TenantId::fromString(strtolower((string) $tenantId))->toString();
    }

    /**
     * {@inheritDoc}
     */
    public function getTenantData(?array $only = null): array
    {
        $user = $this->request->user();

        if (! $user || ! $user->tenant) {
            throw new \RuntimeException('Tenant Context Error: Tenant not available.');
        }

        $tenant = $user->tenant;

        // If $only is specified, filter to those keys only
        if ($only !== null) {
            $needsFullTenant = in_array('is_pro', $only, true) || in_array('landing_page_data', $only, true);
            $tenantKey = $needsFullTenant ? $this->getTenantId() : (
                method_exists($tenant, 'getRawOriginal')
                    ? $tenant->getRawOriginal($tenant->getKeyName())
                    : $tenant->getKey()
            );
            $sourceTenant = $needsFullTenant
                // ADMIN_CROSS_TENANT: intentional TenantScope bypass; caller must keep an explicit tenant or local-intent filter.
                ? (Tenant::withoutGlobalScopes()->find((string) $tenantKey) ?? $tenant)
                : $tenant;
            $attributes = method_exists($sourceTenant, 'getAttributes') ? $sourceTenant->getAttributes() : [];
            $plan = $attributes['plan_type'] ?? $attributes['plan'] ?? null;
            $subscriptionStatus = $attributes['subscription_status'] ?? 'active';

            return collect($only)
                ->mapWithKeys(function (string $key) use ($attributes, $plan, $subscriptionStatus): array {
                    if (array_key_exists($key, $attributes)) {
                        return [$key => $attributes[$key]];
                    }

                    return match ($key) {
                        'is_pro' => [
                            $key => in_array($plan, ['pro', 'enterprise'], true)
                                && ! in_array($subscriptionStatus, ['past_due', 'canceled'], true),
                        ],
                        'landing_page_data' => [$key => ['sections' => []]],
                        default => [$key => null],
                    };
                })
                ->all();
        }

        // Default: return essential public fields
        return $tenant->only(['id', 'name', 'slug', 'is_pro']);
    }
}
