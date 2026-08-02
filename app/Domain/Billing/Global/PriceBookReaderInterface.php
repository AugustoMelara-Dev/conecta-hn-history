<?php

declare(strict_types=1);

namespace App\Domain\Billing\Global;

use App\Application\Markets\MarketContext;

interface PriceBookReaderInterface
{
    public function forMarket(MarketContext $market): PriceBook;
}
