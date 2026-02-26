<?php

declare(strict_types=1);

namespace App\Application\Shared\DTOs\Mail;

/**
 * UserMailData - Immutable DTO for User Email Context
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  ARCHITECTURAL PURPOSE: Snapshot Serialization                           ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║  This DTO captures user data at the moment of email dispatch. It avoids  ║
 * ║  the SerializesModels anti-pattern by carrying only primitives.          ║
 * ║                                                                          ║
 * ║  BENEFITS:                                                               ║
 * ║  1. No DB query on queue deserialization                                 ║
 * ║  2. Immune to Model state changes between dispatch and send              ║
 * ║  3. Safe for multi-tenant queue workers                                  ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class UserMailData
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public ?int $tenantId = null,
    ) {}

    /**
     * Create from User model (snapshot at dispatch time).
     */
    public static function fromUser(object $user): self
    {
        return new self(
            id: $user->id,
            name: $user->name,
            email: $user->email,
            tenantId: $user->tenant_id,
        );
    }
}
