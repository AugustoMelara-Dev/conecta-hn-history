<?php

declare(strict_types=1);

namespace App\Domain\Shared\Contracts;

use App\ValueObjects\TenantId;

interface TenantAwareEvent
{
    public function tenantId(): TenantId;
}
