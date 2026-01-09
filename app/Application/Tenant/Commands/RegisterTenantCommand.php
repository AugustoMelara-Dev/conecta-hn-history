<?php

declare(strict_types=1);

namespace App\Application\Identity\Commands;

/**
 * RegisterTenantCommand - Intent for Tenant + User Registration
 *
 * Encapsulates all data required for the registration use case.
 * Dispatched via CommandBus, handled by RegisterTenantHandler.
 */
final readonly class RegisterTenantCommand
{
    public function __construct(
        // Identity
        public string $businessName,
        public string $slug,

        // Credentials
        public string $ownerName,
        public string $email,
        public string $password,

        // Profile
        public int $cityId,
        public int $categoryId,
        public ?string $whatsapp,
        public string $selectedPlan = 'free',

        // Metadata
        public ?string $logoPath = null
    ) {}
}
