<?php

declare(strict_types=1);

namespace App\Domain\Billing\Contracts;

use Illuminate\Support\Collection;

interface BillingReader
{
    /**
     * Get payment history for a specific tenant.
     *
     * @param int $tenantId
     * @param int $limit
     * @return Collection
     */
    public function getPaymentHistory(int $tenantId, int $limit = 20): Collection;
}
