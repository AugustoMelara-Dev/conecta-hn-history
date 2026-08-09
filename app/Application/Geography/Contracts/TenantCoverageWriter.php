<?php

declare(strict_types=1);

namespace App\Application\Geography\Contracts;

use App\Domain\Geography\Enums\FulfillmentMode;

interface TenantCoverageWriter
{
    /** @param list<FulfillmentMode> $modes */
    public function replace(string $tenantId, string $locationId, array $modes, string $areaType, ?float $radiusKm = null): void;
}
