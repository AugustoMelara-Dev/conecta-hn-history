<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Contracts;

use App\Domain\Shared\Contracts\PaginatedResult;

/**
 * BillingRepository - Domain Contract
 */
interface BillingRepository
{
    public function getInvoices(int $tenantId, int $perPage = 10): PaginatedResult;

    public function getSubscriptionStatus(int $tenantId): array;
}
