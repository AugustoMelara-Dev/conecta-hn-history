<?php

declare(strict_types=1);

namespace App\Application\Quotes\Commands;

use Carbon\CarbonImmutable;

final readonly class ReviseQuoteCommand
{
    /**
     * @param  list<array{product_id?: int, service_id?: int, quantity: int, discount_minor?: int, tax_minor?: int, metadata?: array<string, mixed>}>  $items
     */
    public function __construct(
        public string $tenantId,
        public string $actorId,
        public string $quoteId,
        public string $idempotencyKey,
        public array $items,
        public ?CarbonImmutable $expiresAt = null,
    ) {}
}
