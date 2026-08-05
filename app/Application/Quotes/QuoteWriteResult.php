<?php

declare(strict_types=1);

namespace App\Application\Quotes;

use App\Models\Quote;

final readonly class QuoteWriteResult
{
    public function __construct(
        public Quote $quote,
        public bool $replayed = false,
        public string $outcome = 'ok',
        public ?Quote $revisedQuote = null,
    ) {}
}
