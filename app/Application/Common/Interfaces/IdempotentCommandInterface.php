<?php

declare(strict_types=1);

namespace App\Application\Common\Interfaces;

/**
 * IdempotentCommandInterface - The Deduplication Contract
 * 
 * Commands implementing this interface must provide a deterministic
 * unique key to prevent duplicate processing.
 */
interface IdempotentCommandInterface
{
    public function getIdempotencyKey(): string;
}
