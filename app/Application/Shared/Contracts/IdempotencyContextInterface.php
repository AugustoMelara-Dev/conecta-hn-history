<?php

declare(strict_types=1);

namespace App\Application\Shared\Contracts;

/**
 * IdempotencyContextInterface - Request-Scoped Idempotency Key Contract
 *
 * Holds the idempotency key for the current operation/request lifecycle.
 * The Application Layer uses this contract; the Infrastructure Layer
 * hydrates it from HTTP headers or other transport mechanisms.
 */
interface IdempotencyContextInterface
{
    public function setKey(string $key): void;

    public function getKey(): string;

    public function hasKey(): bool;
}
