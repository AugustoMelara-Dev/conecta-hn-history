<?php

declare(strict_types=1);

namespace App\Application\Ai;

interface PrivateAiTenantAccessReaderInterface
{
    public function findAllowlistedTenant(int|string $tenantId): ?object;
}
