<?php

declare(strict_types=1);

namespace App\Domain\Discovery;

final readonly class DiscoveryQuery
{
    public function __construct(
        public string $searchText,
        public string $marketCountryCode,
        public ?int $budgetMinor = null,
        public ?string $currencyCode = null,
        public ?string $desiredLocalDate = null,
        public ?string $offerType = null,
        public int $limit = 6,
    ) {}
}
