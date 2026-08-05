<?php

declare(strict_types=1);

namespace App\Application\Quotes\Commands;

use Carbon\CarbonImmutable;

final readonly class CreateQuoteCommand
{
    /**
     * @param  list<array{product_id?: int, service_id?: int, quantity: int, discount_minor?: int, tax_minor?: int, metadata?: array<string, mixed>}>  $items
     */
    public function __construct(
        public string $tenantId,
        public string $actorId,
        public string $idempotencyKey,
        public string $locationId,
        public array $items,
        public ?int $customerProfileId = null,
        public ?int $leadId = null,
        public ?int $crmDealId = null,
        public string $source = 'merchant',
        public ?CarbonImmutable $expiresAt = null,
    ) {}
}
