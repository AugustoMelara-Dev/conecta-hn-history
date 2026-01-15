<?php

declare(strict_types=1);

namespace App\Application\Identity\Commands;

use App\Application\Common\Interfaces\IdempotentCommandInterface;

/**
 * RegisterUserCommand - Titanium Standard Input
 *
 * Includes Idempotency Key for robust distributed processing.
 */
final readonly class RegisterUserCommand implements IdempotentCommandInterface
{
    public function __construct(
        public string $name,
        public string $email,
        public string $password,
        public ?string $tenantName = null,
        public ?string $slug = null,
        
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
    ) {}

    public function getIdempotencyKey(): string
    {
        // If explicit key provided (e.g. from Client), use it.
        if ($this->idempotencyKey) {
            return $this->idempotencyKey;
        }

        // Fallback: Deterministic Hash of Payload
        // Ensures that identical payloads equate to the same key
        return md5('register_user_' . $this->email);
    }
}
