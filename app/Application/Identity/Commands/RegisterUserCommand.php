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
        // P3 FIX: Changed md5 → xxh128 for modern, collision-resistant hashing
        // xxh128 is faster and has better distribution than MD5
        return hash('xxh128', 'register_user_'.$this->email);
    }
}
