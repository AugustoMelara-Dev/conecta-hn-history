<?php

declare(strict_types=1);

namespace App\Application\Auth\Commands;

/**
 * VerifyOtpCommand - Immutable OTP Verification Credentials
 *
 * â•”â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•—
 * â•‘  TRANSPORT ONLY: No validation logic - Handler validates cryptography.  â•‘
 * â•‘  SECURITY METADATA: Carries IP for audit and brute-force detection.     â•‘
 * â•‘  TENANT AWARE: Mandatory tenantId to prevent cross-tenant verification. â•‘
 * â•šâ•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
 */
final readonly class VerifyOtpCommand implements \App\Application\Shared\Contracts\HashableCommandInterface, \App\Application\Shared\Contracts\VersionedCommandInterface
{
    use \App\Application\Shared\Traits\HasSchemaVersion;

    public function __construct(
        /** Mandatory Tenant Context (Axiom A) */
        public string $tenantId,

        /** Email or phone number associated with OTP */
        public string $identifier,

        /** 6-digit OTP code entered by user (plain text) */
        public string $code,

        /** Client IP for security audit and brute-force detection */
        public string $ipAddress,

        /** Unique Receipt ID (UUID v7) for transactional idempotency */
        public string $idempotencyKey,
        public int $schemaVersion = 1,
    ) {}

    public function getIdempotencyKey(): string
    {
        return $this->idempotencyKey;
    }

    public function getTenantId(): string
    {
        return $this->tenantId;
    }

    public function getCommandHash(): string
    {
        return hash('sha256', json_encode([
            'identifier' => $this->identifier,
            'code' => $this->code,
            'tenantId' => $this->tenantId,
        ]));
    }
}
