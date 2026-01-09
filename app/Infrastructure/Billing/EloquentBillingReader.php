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
     *
     * @param int $tenantId
     * @param int $limit
     * @return Collection
     */
    public function getPaymentHistory(int $tenantId, int $limit = 20): Collection
    {
        // Fail-safe if model doesn't exist (e.g. during early dev or package extraction)
        if (! class_exists(Payment::class)) {
            return collect([]);
        }

        return Payment::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }
}
