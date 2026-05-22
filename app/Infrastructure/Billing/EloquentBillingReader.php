<?php

declare(strict_types=1);

namespace App\Infrastructure\Billing;

use App\Domain\Billing\Contracts\BillingReader;
use App\Models\Payment;
use Illuminate\Support\Collection;

final class EloquentBillingReader implements BillingReader
{
    /**
     * Get payment history for a specific tenant using Eloquent.
     */
    public function getPaymentHistory(int $tenantId, int $limit = 20): Collection
    {
        // Fail-safe if model doesn't exist (e.g. during early dev or package extraction)
        if (! class_exists(Payment::class)) {
            return collect([]);
        }

        // ADMIN_CROSS_TENANT: intentional TenantScope bypass; caller must keep an explicit tenant or local-intent filter.
        return Payment::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }
}
