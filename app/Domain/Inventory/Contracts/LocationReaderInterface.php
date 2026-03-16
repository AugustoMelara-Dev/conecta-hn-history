<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Contracts;

/**
 * LocationReaderInterface - Read-side port for tenant location validation.
 */
interface LocationReaderInterface
{
    public function existsActiveForTenant(string $locationId, string $tenantId): bool;
}
