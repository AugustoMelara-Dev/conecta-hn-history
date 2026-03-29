<?php

declare(strict_types=1);

namespace App\Infrastructure\Tenant;

use App\Domain\Shared\Contracts\PaginatedResult;
use App\Domain\Tenant\Contracts\BillingRepository;
use App\Domain\TenantGovernance\Enums\TenantPlan;
use App\Infrastructure\Shared\EloquentPaginatedResult;
use App\Models\Payment;
use App\Models\Tenant;

/**
 * EloquentBillingRepository - Infra Implementation
 */
final class EloquentBillingRepository implements BillingRepository
{
    public function getInvoices(int $tenantId, int $perPage = 10): PaginatedResult
    {
        return new EloquentPaginatedResult(
            Payment::query()
                ->where('tenant_id', $tenantId)
                ->orderByDesc('created_at')
                ->paginate($perPage)
        );
    }

    public function getSubscriptionStatus(int $tenantId): array
    {
        $tenant = Tenant::find($tenantId);

        if (! $tenant) {
            return [];
        }

        $planKey = $tenant->getRawOriginal('plan_type') ?: ($tenant->getAttributes()['plan_type'] ?? null);
        $resolvedPlan = match (true) {
            $planKey instanceof TenantPlan => $planKey->value,
            is_string($planKey) && $planKey !== '' => $planKey,
            default => Tenant::PLAN_SEMILLA,
        };
        $isPaidPlan = $resolvedPlan !== Tenant::PLAN_SEMILLA;

        return [
            'status' => $tenant->subscription_status ?? ($isPaidPlan ? 'active' : 'inactive'),
            'renewal_date' => $tenant->subscription_ends_at?->toIso8601String(),
            'plan' => match ($resolvedPlan) {
                Tenant::PLAN_CRECIMIENTO => 'CRECIMIENTO',
                Tenant::PLAN_COSECHA => 'COSECHA',
                TenantPlan::PRO->value => 'PRO',
                default => 'FREE',
            },
        ];
    }
}
