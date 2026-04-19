<?php

declare(strict_types=1);

namespace App\Application\Tenant\Commands;

use App\Application\Shared\Contracts\IdempotentCommandInterface;
use App\Application\Shared\Contracts\SensitiveDataInterface;
use SensitiveParameter;

/**
 * RegisterTenantCommand - Intent for Tenant + User Registration
 *
 * Encapsulates all data required for the registration use case.
 * Dispatched via CommandBus, handled by RegisterTenantHandler.
 */
final readonly class RegisterTenantCommand implements IdempotentCommandInterface, SensitiveDataInterface
{
    public function __construct(
        // Identity
        public string $businessName,
        public string $slug,

        // Credentials
        #[\SensitiveParameter]
        public string $ownerName,
        #[SensitiveParameter]
        public string $email,
        #[SensitiveParameter]
        public string $password,

        // Profile
        public int $cityId,
        public int $categoryId,
        public string $businessType,
        public ?string $whatsapp,
        public string $selectedPlan = 'free',

        // Metadata
        public ?string $logoPath = null,
        public ?string $claimEvidenceType = null,
        public ?string $claimEvidenceUrl = null,
        public ?string $claimEvidenceNotes = null,
        public bool $termsAccepted = true,
        public bool $privacyAccepted = true,
        public ?string $termsVersion = null,
        public ?string $privacyVersion = null,
        public ?string $acceptedIpAddress = null,
        public ?string $acceptedUserAgent = null,

        // Explicit Idempotency
        public ?string $idempotencyKey = null,

        // Schema Version for Upcasting
        public int $schemaVersion = 1,
    ) {}

    public function getIdempotencyKey(): string
    {
        if ($this->idempotencyKey) {
            return $this->idempotencyKey;
        }

        // Dedicated Hash: Tenant Name + Owner Email
        return hash('xxh128', "register_tenant_{$this->businessName}_{$this->email}");
    }

    public function getSensitiveProperties(): array
    {
        return ['password', 'ownerName'];
    }
}
