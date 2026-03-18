<?php

declare(strict_types=1);

namespace App\Infrastructure\Tenant;

use App\Domain\Shared\Contracts\PaginatedResult;
use App\Domain\Tenant\Contracts\BillingRepository;
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

        return [
            'status' => $tenant->subscription_status ?? ($tenant->is_pro ? 'active' : 'inactive'),
            'renewal_date' => $tenant->subscription_ends_at?->toIso8601String(),
            'plan' => $tenant->is_pro ? 'PRO' : 'FREE',
        ];
    }
}
