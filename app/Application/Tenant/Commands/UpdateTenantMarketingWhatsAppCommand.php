<?php

declare(strict_types=1);

namespace App\Application\Tenant\Commands;

final readonly class UpdateTenantMarketingWhatsAppCommand
{
    public function __construct(
        public string $tenantId,
        public string $whatsappNumber,
        public string $whatsappMessage,
        public int $schemaVersion = 1,
    ) {}
}
