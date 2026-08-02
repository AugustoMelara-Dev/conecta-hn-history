<?php

declare(strict_types=1);

namespace App\Infrastructure\Tenant;

use App\Infrastructure\AI\EloquentTenantAgentReader;
use App\Models\Tenant;

final class TenantPrivateAgentService
{
    public function __construct(private readonly EloquentTenantAgentReader $reader) {}

    /** @return array<string, mixed> */
    public function answer(Tenant $tenant, string $message): array
    {
        return $this->reader->answer((string) $tenant->getKey(), $message);
    }
}
