<?php

declare(strict_types=1);

namespace App\Application\ServiceCatalog\Commands;

final readonly class CreateServiceCommand
{
    /**
     * @param  list<string>  $locationIds
     */
    public function __construct(
        public string $tenantId,
        public string $actorId,
        public string $idempotencyKey,
        public string $name,
        public int $priceMinor,
        public string $currency,
        public int $durationMinutes,
        public array $locationIds,
        public ?string $description = null,
        public bool $published = false,
    ) {}
}
