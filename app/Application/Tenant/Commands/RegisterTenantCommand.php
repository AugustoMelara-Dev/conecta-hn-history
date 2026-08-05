<?php

declare(strict_types=1);

namespace App\Application\Tenant\Commands;

use App\Application\Common\Interfaces\IdempotentCommandInterface;

/**
 * RegisterTenantCommand - Intent for Tenant + User Registration
 *
 * Encapsulates all data required for the registration use case.
 * Dispatched via CommandBus, handled by RegisterTenantHandler.
 */
final readonly class RegisterTenantCommand implements IdempotentCommandInterface
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
        public ?int $cityId,
        public ?int $categoryId,
        public ?string $whatsapp,
        public string $selectedPlan = 'free',
        public string $businessType = 'retail',
        public string $commercialMode = 'products',

        // Metadata
        public ?string $logoPath = null,
        public ?string $rubroKey = null,

        // Explicit Idempotency
        public ?string $idempotencyKey = null,

        // Explicit global market context. Legacy Honduras callers may use cityId
        // during the expand-and-shadow-read compatibility window.
        public ?string $countryCode = null,
        public ?int $localityId = null,
        public ?string $localeCode = null,
        public ?string $currencyCode = null,
        public ?string $timezone = null,
        public ?string $address = null,
    ) {}

    public function getIdempotencyKey(): string
    {
        if ($this->idempotencyKey) {
            return $this->idempotencyKey;
        }

        // Dedicated Hash: Tenant Name + Owner Email
        return md5("register_tenant_{$this->businessName}_{$this->email}");
    }
}
