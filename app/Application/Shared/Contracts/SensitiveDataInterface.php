<?php

declare(strict_types=1);

namespace App\Application\Shared\Contracts;

/**
 * SensitiveDataInterface - PII/Secret Redaction Contract
 *
 * Commands implementing this interface declare which properties
 * contain sensitive data (passwords, tokens, API keys) that MUST
 * be redacted during logging, serialization, or Outbox persistence.
 *
 * SEC-01 COMPLIANCE: Prevents plaintext password leakage in
 * stack traces, command bus logs, and transactional outbox records.
 */
interface SensitiveDataInterface
{
    /**
     * Returns an array of property names that contain sensitive data
     * and must be redacted during logging or serialization.
     *
     * @return array<string>
     */
    public function getSensitiveProperties(): array;
}
