<?php

declare(strict_types=1);

namespace App\Application\Auth\Commands;

use App\Application\Shared\Contracts\IdempotentCommandInterface;
use App\Application\Shared\Contracts\SensitiveDataInterface;

/**
 * ResetUserPasswordCommand - Immutable Password Reset DTO
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  TRANSPORT ONLY: No business logic, no Eloquent models.                ║
 * ║  TENANT AWARE: Carries tenantId for audit and isolation.               ║
 * ║  QUEUE SAFE: All properties are primitives — serializable by design.   ║
 * ║  SEC-01 FIX: Password scrubbed from stack traces via SensitiveParameter║
 * ║  P1-REDACT: SensitiveDataInterface for Outbox/log sanitization.        ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class ResetUserPasswordCommand implements IdempotentCommandInterface, SensitiveDataInterface, \App\Application\Shared\Contracts\VersionedCommandInterface
{
    use \App\Application\Shared\Traits\HasSchemaVersion;

    public function __construct(
        /** The user's UUID (resolved by Handler, not carried as Entity) */
        public string $userId,

        /** The new password (scrubbed from stack traces) */
        #[\SensitiveParameter] public string $password,

        /** Tenant context for audit trail and ownership validation */
        public string $tenantId,

        /** Unique Receipt ID for transactional idempotency */
        public ?string $idempotencyKey = null,

        /** Schema Version for Upcasting */
        public int $schemaVersion = 1,
    ) {}

    public function getIdempotencyKey(): string
    {
        return $this->idempotencyKey ?: hash('xxh128', 'reset_pwd_' . $this->userId);
    }

    public function getSensitiveProperties(): array
    {
        return ['password'];
    }
}


