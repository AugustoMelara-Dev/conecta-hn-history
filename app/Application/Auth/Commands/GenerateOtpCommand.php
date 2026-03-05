<?php

declare(strict_types=1);

namespace App\Application\Auth\Commands;

use App\Application\Shared\Contracts\HashableCommandInterface;

/**
 * GenerateOtpCommand - Immutable OTP Request Contract
 *
 * â•”â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•—
 * â•‘  TENANT AWARE: Mandatory tenantId to prevent cross-tenant OTP abuse.    â•‘
 * â•‘  HASHABLE: Implements HashableCommand for integrity verification.       â•‘
 * â•‘  AUDIT METADATA: Carries IP/UserAgent for security logging.             â•‘
 * â•šâ•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
 */
final readonly class GenerateOtpCommand implements HashableCommandInterface, \App\Application\Shared\Contracts\VersionedCommandInterface
{
    use \App\Application\Shared\Traits\HasSchemaVersion;

    public function __construct(
        /** Mandatory Tenant Context (Axiom A) */
        public string $tenantId,

        /** Email or phone number to send OTP */
        #[\SensitiveParameter] public string $identifier,

        /** OTP type (email, sms) */
        public string $type,

        /** Client IP for security audit */
        #[\SensitiveParameter] public string $ipAddress,

        /** Client User-Agent for fingerprinting */
        #[\SensitiveParameter] public string $userAgent,

        /** Unique Receipt ID (UUID v7) for transactional idempotency */
        public string $idempotencyKey = '',
        public int $schemaVersion = 1,
    ) {}

    public function getIdempotencyKey(): string
    {
        // If not provided, generate deterministic key from payload
        if ($this->idempotencyKey === '') {
            return $this->getCommandHash();
        }

        return $this->idempotencyKey;
    }

    public function getTenantId(): string
    {
        return $this->tenantId;
    }

    public function getCommandHash(): string
    {
        return hash('sha256', json_encode([
            'tenantId' => $this->tenantId,
            'identifier' => $this->identifier,
            'type' => $this->type,
            'ipAddress' => $this->ipAddress,
        ]));
    }
}
