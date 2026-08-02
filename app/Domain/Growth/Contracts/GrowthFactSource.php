<?php

declare(strict_types=1);

namespace App\Domain\Growth\Contracts;

use App\Domain\Growth\Data\MarketingClaim;

interface GrowthFactSource
{
    /**
     * @return list<MarketingClaim>
     */
    public function marketingClaims(): array;
}
