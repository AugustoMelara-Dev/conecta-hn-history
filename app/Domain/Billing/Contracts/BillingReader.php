<?php

declare(strict_types=1);

namespace App\Domain\Billing\Contracts;

use Illuminate\Support\Collection;

interface BillingReader
{
    /**
     * Get payment history for a specific tenant.
     */
    public function getPaymentHistory(string $tenantId, int $limit = 20): Collection;
}
