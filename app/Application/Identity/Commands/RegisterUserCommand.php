<?php

declare(strict_types=1);

namespace App\Application\Identity\Commands;

use App\Application\Shared\Contracts\IdempotentCommandInterface;
use App\Application\Shared\Contracts\SensitiveDataInterface;

/**
 * RegisterUserCommand - Titanium Standard Input
 *
 * Includes Idempotency Key for robust distributed processing.
 * P1-REDACT: SensitiveDataInterface for Outbox/log sanitization.
 */
final readonly class RegisterUserCommand implements \App\Application\Shared\Contracts\VersionedCommandInterface, IdempotentCommandInterface, SensitiveDataInterface
{
    use \App\Application\Shared\Traits\HasSchemaVersion;

    public function __construct(
        public string $name,
        #[\SensitiveParameter] public string $email,
        #[\SensitiveParameter] public string $password,
        public ?string $tenantName = null,
        public ?string $slug = null,
        /** @param string|null $tenantId Null is valid for seller registrations (role = seller). Must not be null for tenant-bound staff registrations. */
        public ?string $tenantId = null,
        public ?string $role = null, // Defaults handled in Handler/Action

        /**
         * Origin of the registration.
         * Required for Data Fidelity.
         */
        public string $source = 'user_registration',

        /**
         * Unique Request ID for Idempotency Control.
         * Prevents duplicate processing in unstable networks.
         */
        public ?string $idempotencyKey = null,

        /**
         * Schema Version for Upcasting.
         */
        public int $schemaVersion = 1,
    ) {}

    public function getIdempotencyKey(): string
    {
        // If explicit key provided (e.g. from Client), use it.
        if ($this->idempotencyKey) {
            return $this->idempotencyKey;
        }

        // Fallback: Deterministic Hash of Payload
        // P3 FIX: Changed md5 → xxh128 for modern, collision-resistant hashing
        // xxh128 is faster and has better distribution than MD5
        return hash('xxh128', 'register_user_'.$this->email);
    }

    public function getSensitiveProperties(): array
    {
        return ['password'];
    }
}
