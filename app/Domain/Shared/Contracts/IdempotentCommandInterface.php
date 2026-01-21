<?php

declare(strict_types=1);

namespace App\Domain\Shared\Contracts;

/**
 * IdempotentCommandInterface - Contract for Replay Protection
 *
 * Commands implementing this interface MUST provide a unique key
 * that identifies the request. The Bus will use this to prevent
 * duplicate execution.
 */
interface IdempotentCommandInterface
{
    /**
     * Get the unique idempotency key for this command.
     *
     * @return string UUID or unique hash
     */
    public function getIdempotencyKey(): string;
}
