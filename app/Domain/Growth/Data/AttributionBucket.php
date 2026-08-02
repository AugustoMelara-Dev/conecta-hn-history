<?php

declare(strict_types=1);

namespace App\Domain\Growth\Data;

use App\Domain\Growth\Enums\AttributionStage;

final readonly class AttributionBucket
{
    /**
     * @param  array<string, int>  $revenueByCurrencyMinor
     */
    public function __construct(
        public AttributionStage $stage,
        public int $count,
        public array $revenueByCurrencyMinor,
        public string $definition,
    ) {}
}
