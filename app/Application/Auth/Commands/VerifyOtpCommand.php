<?php

declare(strict_types=1);

namespace App\Application\Auth\Commands;

/**
 * VerifyOtpCommand - Immutable OTP verification credentials.
 *
 * Transport only. The handler validates cryptography, tenant boundaries, and
 * brute-force protection.
 */
final readonly class VerifyOtpCommand implements \App\Application\Shared\Contracts\HashableCommandInterface, \App\Application\Shared\Contracts\VersionedCommandInterface
{
    use \App\Application\Shared\Traits\HasSchemaVersion;

    public function __construct(
        /** Mandatory tenant context. */
        public string $tenantId,

        /** Email or phone number associated with the OTP. */
        #[\SensitiveParameter] public string $identifier,

        /** Six-digit OTP code entered by the user. */
        #[\SensitiveParameter] public string $code,

        /** Client IP used for audit and brute-force detection. */
        #[\SensitiveParameter] public string $ipAddress,

        /** Unique receipt ID for transactional idempotency. */
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
