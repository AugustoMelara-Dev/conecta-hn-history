<?php

declare(strict_types=1);

namespace App\Application\Growth;

use App\Domain\Growth\Contracts\GrowthFactSource;

final readonly class RepositoryMarketingTruthCatalog
{
    public function __construct(
        private GrowthFactSource $factSource,
    ) {}

    public function marketingClaims(): array
    {
        return $this->factSource->marketingClaims();
    }
}
