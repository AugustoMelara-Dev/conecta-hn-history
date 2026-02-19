<?php

declare(strict_types=1);

namespace App\Infrastructure\Shared\Context;

use App\Application\Shared\Contracts\IdempotencyContextInterface;
use RuntimeException;

/**
 * IdempotencyContext
 *
 * Holds the Idempotency Key for the current operation/request.
 * This decouples the Domain/Application layer from HTTP Headers.
 */
class IdempotencyContext implements IdempotencyContextInterface
{
    private ?string $key = null;

    public function setKey(string $key): void
    {
        $this->key = $key;
    }

    public function getKey(): string
    {
        if ($this->key === null) {
            throw new RuntimeException('Idempotency Context not hydrated. Ensure EnsureIdempotencyKey middleware is executing.');
        }

        return $this->key;
    }

    public function hasKey(): bool
    {
        return $this->key !== null;
    }
}
