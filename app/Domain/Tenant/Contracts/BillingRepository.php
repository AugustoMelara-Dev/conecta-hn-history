<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * BillingRepository - Domain Contract
 */
interface BillingRepository
{
    public function getInvoices(string $tenantId, int $perPage = 10): LengthAwarePaginator;

    public function getSubscriptionStatus(string $tenantId): array;
}
