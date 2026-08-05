<?php

declare(strict_types=1);

namespace App\Application\Directory\Contracts;

use App\Application\Directory\Results\PublicTenantEngagementResult;

interface PublicTenantEngagementGatewayInterface
{
    public function toggleFavorite(string $userId, string $tenantId): ?PublicTenantEngagementResult;

    public function toggleFollow(string $userId, string $tenantId): ?PublicTenantEngagementResult;
}
