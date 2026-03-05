<?php

declare(strict_types=1);

namespace App\Infrastructure\Billing;

use App\Domain\Billing\Contracts\BillingReader;
use App\Models\Payment;

final class EloquentBillingReader implements BillingReader
{
    /**
     * Get payment history for a specific tenant using Eloquent.
     */
    public function getPaymentHistory(int $tenantId, int $limit = 20): array
    {
        if (! class_exists(Payment::class)) {
            return [];
        }

        return Payment::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->toArray();
    }
}
