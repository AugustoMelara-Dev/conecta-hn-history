<?php

declare(strict_types=1);

namespace App\Domain\Public\Contracts;

use App\Models\Tenant;

/**
 * TenantStorefrontRepository - Public Read Contract
 */
interface TenantStorefrontRepository
{
    /**
     * Find tenant by slug with all optimized relations for display.
     *
     * @param string $slug
     * @return Tenant|null
     */
    public function findBySlug(string $slug): ?Tenant;
}
