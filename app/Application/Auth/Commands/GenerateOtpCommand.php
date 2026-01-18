<?php

declare(strict_types=1);

namespace App\Application\Auth\Commands;

/**
 * GenerateOtpCommand
 *
 * Immutable DTO strictly enforcing Tenant Context and Audit Metadata.
 * Used as the Entry Contract for the OTP Generation Use Case.
 */
final readonly class GenerateOtpCommand
{
    public function __construct(
        public string $tenantId, // Mandatory Tenant Context (Axiom A)
        public string $identifier,
        public string $type,
        public string $ipAddress,
        public string $userAgent,
        /** Unique Receipt ID (UUID v7) for transactional idempotency */
        public string $idempotencyKey,
    ) {}
}
