<?php

declare(strict_types=1);

namespace App\Application\Ai\Presentation;

use App\Application\Markets\MarketContext;
use Carbon\CarbonImmutable;

final class AiMarketPresenter
{
    public function money(int $amountMinor, MarketContext $market): string
    {
        return $market->currencyCode.' '.number_format($amountMinor / 100, 2, '.', ',');
    }

    public function localDate(MarketContext $market): CarbonImmutable
    {
        return CarbonImmutable::now($market->timezone);
    }
}
