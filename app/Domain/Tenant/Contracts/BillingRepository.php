<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * BillingRepository - Domain Contract
 */
interface BillingRepository
{
    public function getInvoices(int $tenantId, int $perPage = 10): LengthAwarePaginator;

    public function getSubscriptionStatus(int $tenantId): array;
}
