<?php

declare(strict_types=1);

namespace App\Infrastructure\Tenant;

use App\Domain\Tenant\Contracts\BillingRepository;
use App\Models\Payment;
use App\Models\Tenant;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * EloquentBillingRepository - Infra Implementation
 */
final class EloquentBillingRepository implements BillingRepository
{
    public function getInvoices(int $tenantId, int $perPage = 10): LengthAwarePaginator
    {
        return Payment::query()
            ->where('tenant_id', $tenantId)
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function getSubscriptionStatus(int $tenantId): array
    {
        $tenant = Tenant::find($tenantId);

        if (!$tenant) {
            return [];
        }

        if (method_exists($tenant, 'subscription') && $tenant->subscription) {
            return [
                'status' => $tenant->subscription->stripe_status ?? 'active',
                'renewal_date' => $tenant->subscription->ends_at?->toIso8601String(),
                'plan' => $tenant->is_pro ? 'PRO' : 'FREE',
            ];
        }

        return $tenant->is_pro ? [
            'status' => 'active',
            'renewal_date' => now()->addMonth()->toIso8601String(),
            'plan' => 'PRO',
        ] : [
            'status' => 'inactive',
            'plan' => 'FREE',
        ];
    }
}
