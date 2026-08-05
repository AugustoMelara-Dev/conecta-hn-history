<?php

declare(strict_types=1);

namespace App\Application\ServiceCatalog\Commands;

final readonly class UpdateServiceCommand
{
    /**
     * @param  list<string>|null  $locationIds
     */
    public function __construct(
        public string $tenantId,
        public string $actorId,
        public int $serviceId,
        public ?string $name = null,
        public ?int $priceMinor = null,
        public ?string $currency = null,
        public ?int $durationMinutes = null,
        public ?array $locationIds = null,
        public ?string $description = null,
        public bool $descriptionSpecified = false,
        public ?bool $published = null,
    ) {}
}
