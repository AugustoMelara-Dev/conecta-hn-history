<?php

declare(strict_types=1);

namespace App\Application\Products\Commands;

/**
 * The only creation DTO for operational products. Money is expressed in the
 * tenant market currency's integer minor units.
 *
 * @param  array<string, mixed>  $attributes  Deliberately allow-listed by CanonicalProductWriter.
 */
final readonly class CreateCanonicalProductCommand
{
    public function __construct(
        public string $tenantId,
        public string $idempotencyKey,
        public string $name,
        public int $priceMinor,
        public int $initialStockQuantity = 0,
        public array $attributes = [],
        public bool $published = false,
    ) {}
}
