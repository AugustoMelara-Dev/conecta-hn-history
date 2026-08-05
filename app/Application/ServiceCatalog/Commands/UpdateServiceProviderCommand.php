<?php

declare(strict_types=1);

namespace App\Application\ServiceCatalog\Commands;

final readonly class UpdateServiceProviderCommand
{
    /**
     * @param  list<string>  $locationIds
     */
    public function __construct(
        public string $tenantId,
        public string $actorId,
        public int $providerId,
        public string $name,
        public array $locationIds,
        public ?string $email = null,
        public ?string $phone = null,
        public bool $active = true,
    ) {}
}
