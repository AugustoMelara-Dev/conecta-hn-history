<?php

declare(strict_types=1);

namespace App\Application\Directory\Contracts;

use App\Application\Directory\Results\PublicTenantEngagementResult;
use Illuminate\Support\Collection;

interface PublicTenantEngagementGatewayInterface
{
    public function toggleFavorite(string $userId, string $tenantId): ?PublicTenantEngagementResult;

    /** @param array<int, string> $tenantIds
     *  @return array<int, string>
     */
    public function favoriteTenantIds(string $userId, array $tenantIds = []): array;

    /** @return Collection<int, \App\Models\Tenant> */
    public function favoriteTenants(string $userId): Collection;

    /**
     * @return array{
     *   items: array<int, array<string, mixed>>,
     *   average: float,
     *   count: int,
     *   reviewed_product_ids: array<int, int>
     * }
     */
    public function publicReviews(string $tenantId, ?string $userId = null): array;

    public function toggleFollow(string $userId, string $tenantId): ?PublicTenantEngagementResult;
}
