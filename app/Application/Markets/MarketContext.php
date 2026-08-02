<?php

declare(strict_types=1);

namespace App\Application\Markets;

final readonly class MarketContext
{
    public function __construct(
        public string $countryCode,
        public string $localeCode,
        public string $currencyCode,
        public string $timezone,
        public string $source,
    ) {}
}
