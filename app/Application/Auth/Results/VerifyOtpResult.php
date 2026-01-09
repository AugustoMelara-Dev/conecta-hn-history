<?php

declare(strict_types=1);

namespace App\Application\DTOs\Auth;

use App\Domain\Identity\Entities\UserEntity;
use Carbon\Carbon;

/**
 * VerifyOtpResult - Structured Verification Outcome
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  RICH CONTRACT: Replaces ambiguous arrays with typed properties.        ║
 * ║  NULLABLE USER: Explicit null instead of empty object anti-pattern.     ║
 * ║  CONTROLLER DECISION: Enables informed session/routing decisions.       ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class VerifyOtpResult
{
    public function __construct(
        /** User entity (null if registration required) */
        public ?UserEntity $user,
        
        /** Timestamp of successful verification */
        public Carbon $verifiedAt,
        
        /** Flag for future 2FA step requirement */
        public bool $requiresTwoFactor = false,
        
        /** Flag if user was not found (new registration needed) */
        public bool $requiresRegistration = false,
        
        /** Original identifier for registration flow */
        public ?string $identifier = null,
    ) {}

    /**
     * Create result for existing user verification.
     */
    public static function forUser(UserEntity $user): self
    {
        return new self(
            user: $user,
            verifiedAt: Carbon::now(),
        );
    }

    /**
     * Create result for new user (requires registration).
     */
    public static function forNewUser(string $identifier): self
    {
        return new self(
            user: null, // Explicit null instead of empty object
            verifiedAt: Carbon::now(),
            requiresRegistration: true,
            identifier: $identifier,
        );
    }

    /**
     * Check if user exists and verification was successful.
     */
    public function hasUser(): bool
    {
        return $this->user !== null;
    }
}
