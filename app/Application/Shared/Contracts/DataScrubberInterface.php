<?php

declare(strict_types=1);

namespace App\Application\Shared\Contracts;

/**
 * DataScrubberInterface - PII/Sensitive Data Redaction Port
 *
 * Defines the contract for sanitizing data structures before logging
 * or serialization. Prevents PII leakage without coupling to a
 * specific infrastructure implementation.
 */
interface DataScrubberInterface
{
    /**
     * Scrub sensitive data from the payload.
     *
     * @param  array<mixed>|object  $payload
     * @return array<mixed>
     */
    public function scrub(array|object $payload): array;
}
