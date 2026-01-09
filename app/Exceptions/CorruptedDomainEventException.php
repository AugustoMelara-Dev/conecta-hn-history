<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * CorruptedDomainEventException - Data Integrity Breach Detection
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  FAIL-FAST EXCEPTION FOR EVENT SOURCING                                 ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║                                                                          ║
 * ║  THROWN WHEN: A Domain Event payload is missing critical business data. ║
 * ║                                                                          ║
 * ║  PURPOSE:                                                                ║
 * ║    - Force immediate Job failure (moves to failed_jobs table)           ║
 * ║    - Prevent silent data corruption in financial transactions           ║
 * ║    - Enable targeted monitoring via exception class                     ║
 * ║    - Trigger alerting in observability platforms (Sentry, Bugsnag)      ║
 * ║                                                                          ║
 * ║  RECOVERY:                                                              ║
 * ║    1. Investigate queue payload in failed_jobs table                    ║
 * ║    2. Identify source of incomplete data                                ║
 * ║    3. Fix upstream producer                                              ║
 * ║    4. Replay event with correct payload                                 ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * @see App\Events\Order\OrderCancelled::fromPrimitives()
 * @see App\Events\Order\OrderRefunded::fromPrimitives()
 * @see App\Events\Order\OrderPaid::fromPrimitives()
 * @see App\Events\Payment\PaymentApproved::fromPrimitives()
 */
class CorruptedDomainEventException extends \RuntimeException
{
    /**
     * Create exception with standardized message format.
     */
    public static function missingKey(string $key, string $eventClass): self
    {
        return new self(
            "Integrity Breach: Critical key '{$key}' missing in payload for event {$eventClass}"
        );
    }

    /**
     * Create exception for null value on required field.
     */
    public static function nullValue(string $key, string $eventClass): self
    {
        return new self(
            "Integrity Breach: Critical key '{$key}' is null in payload for event {$eventClass}"
        );
    }
}
