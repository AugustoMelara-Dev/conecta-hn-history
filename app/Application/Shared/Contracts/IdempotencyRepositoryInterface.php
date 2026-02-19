<?php

declare(strict_types=1);

namespace App\Application\Shared\Contracts;

use App\Application\Identity\Results\RegisterUserResult;

interface IdempotencyRepositoryInterface
{
    /**
     * Retrieve result ensuring checksum matches to prevent reuse attacks.
     */
    public function getResult(string $key, string $checksum): ?RegisterUserResult;

    public function storeResult(string $key, RegisterUserResult $result): void;

    /**
     * Acquire lock with checksum validation.
     */
    public function acquireLock(string $key, string $checksum, int $ttlSeconds): bool;

    public function releaseLock(string $key): void;
}
