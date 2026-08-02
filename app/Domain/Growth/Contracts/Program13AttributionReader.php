<?php

declare(strict_types=1);

namespace App\Domain\Growth\Contracts;

use App\Domain\Growth\Data\AttributionSnapshot;

interface Program13AttributionReader
{
    public function snapshot(string $tenantId): AttributionSnapshot;
}
