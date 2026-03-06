<?php

declare(strict_types=1);

namespace App\Domain\Shared\ValueObjects;

use InvalidArgumentException;

final readonly class EncryptedEmail
{
    public function __construct(public string $encryptedValue)
    {
        if (trim($encryptedValue) === '') {
            throw new InvalidArgumentException('Encrypted email cannot be empty.');
        }

        // Enforce PII protection: Reject any string that resembles a plain text email.
        if (filter_var($encryptedValue, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Security Violation: Email must be encrypted before being stored in the Domain Event.');
        }
    }

    public function value(): string
    {
        return $this->encryptedValue;
    }
}
