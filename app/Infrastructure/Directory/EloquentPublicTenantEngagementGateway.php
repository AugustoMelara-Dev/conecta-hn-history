<?php

declare(strict_types=1);

namespace App\Infrastructure\Directory;

use App\Application\Directory\Contracts\PublicTenantEngagementGatewayInterface;
use App\Application\Directory\Results\PublicTenantEngagementResult;
use App\Infrastructure\Persistence\Eloquent\Scopes\TenantScope;
use App\Models\Follow;
use App\Models\Tenant;
use App\Models\TenantFavorite;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class EloquentPublicTenantEngagementGateway implements PublicTenantEngagementGatewayInterface
{
    public function toggleFavorite(string $userId, string $tenantId): ?PublicTenantEngagementResult
    {
        return DB::transaction(function () use ($userId, $tenantId): ?PublicTenantEngagementResult {
            if (! $this->publicTenantExists($tenantId) || ! User::query()->whereKey($userId)->exists()) {
                return null;
            }

            $favoriteQuery = TenantFavorite::query()
                // PUBLIC_READ_WITH_TENANT_FILTER: explicit tenant and user filters guard this public engagement write.
                ->withoutGlobalScope(TenantScope::class)
                ->where('user_id', $userId)
                ->where('tenant_id', $tenantId);

            $favorite = (clone $favoriteQuery)->lockForUpdate()->first();

            if ($favorite instanceof TenantFavorite) {
                $favorite->delete();
                $engaged = false;
            } else {
                $favorite = new TenantFavorite;
                $favorite->forceFill([
                    'user_id' => $userId,
                    'tenant_id' => $tenantId,
                ]);
                $favorite->save();
                $engaged = true;
            }

            return new PublicTenantEngagementResult(
                tenantId: $tenantId,
                engaged: $engaged,
                count: (clone $favoriteQuery)->count(),
            );
        });
    }

    public function toggleFollow(string $userId, string $tenantId): ?PublicTenantEngagementResult
    {
        return DB::transaction(function () use ($userId, $tenantId): ?PublicTenantEngagementResult {
            if (! $this->publicTenantExists($tenantId) || ! User::query()->whereKey($userId)->exists()) {
                return null;
            }

            $followQuery = Follow::query()
                // PUBLIC_READ_WITH_TENANT_FILTER: explicit tenant and user filters guard this public engagement write.
                ->withoutGlobalScope(TenantScope::class)
                ->where('user_id', $userId)
                ->where('tenant_id', $tenantId);

            $follow = (clone $followQuery)->lockForUpdate()->first();

            if ($follow instanceof Follow) {
                $follow->delete();
                $engaged = false;
            } else {
                $follow = new Follow;
                $follow->forceFill([
                    'user_id' => $userId,
                    'tenant_id' => $tenantId,
                ]);
                $follow->save();
                $engaged = true;
            }

            $count = Follow::query()
                // PUBLIC_READ_WITH_TENANT_FILTER: explicit tenant filter guards this public aggregate.
                ->withoutGlobalScope(TenantScope::class)
                ->where('tenant_id', $tenantId)
                ->count();

            return new PublicTenantEngagementResult($tenantId, $engaged, $count);
        });
    }

    private function publicTenantExists(string $tenantId): bool
    {
        return Tenant::query()
            ->whereKey($tenantId)
            ->where('is_active', true)
            ->whereIn('subscription_status', ['active', 'trial'])
            ->exists();
    }
}
