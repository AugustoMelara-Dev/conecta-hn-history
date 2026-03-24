<?php

declare(strict_types=1);

namespace App\Domain\Shared\Contracts;

/**
 * TenantReadRepository - Lightweight Read Access
 */
interface TenantReadRepository
{
    public function exists(string|int $id): bool;
}
