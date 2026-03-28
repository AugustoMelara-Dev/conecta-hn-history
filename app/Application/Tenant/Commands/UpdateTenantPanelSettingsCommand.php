<?php

declare(strict_types=1);

namespace App\Application\Tenant\Commands;

final readonly class UpdateTenantPanelSettingsCommand
{
    public function __construct(
        public string $tenantId,
        public string $name,
        public string $whatsappNumber,
        public ?string $description,
        public ?string $address,
        public string $whatsappMessage,
        public int $schemaVersion = 1,
    ) {}
}
