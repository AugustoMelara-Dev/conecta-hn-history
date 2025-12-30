<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * PayloadTooLargeException - Thrown when input exceeds safe limits.
 *
 * Protects against memory exhaustion DoS attacks via massive JSON payloads.
 */
class PayloadTooLargeException extends DataIntegrityException
{
    public static function itemsExceeded(int $count, int $limit): self
    {
        return new self(
            "Payload too large: {$count} items exceeds limit of {$limit}.",
            ['count' => $count, 'limit' => $limit]
        );
    }
}
