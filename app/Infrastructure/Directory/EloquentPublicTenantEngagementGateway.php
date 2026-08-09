<?php

declare(strict_types=1);

namespace App\Infrastructure\Directory;

use App\Application\Directory\Contracts\PublicTenantEngagementGatewayInterface;
use App\Application\Directory\Results\PublicTenantEngagementResult;
use App\Infrastructure\Persistence\Eloquent\Scopes\TenantScope;
use App\Models\Follow;
use App\Models\Review;
use App\Models\Tenant;
use App\Models\TenantFavorite;
use App\Models\User;
use Illuminate\Support\Collection;
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
                count: TenantFavorite::query()
                    // PUBLIC_READ_WITH_TENANT_FILTER: aggregate is constrained to the engaged tenant UUID.
                    ->withoutGlobalScope(TenantScope::class)
                    ->where('tenant_id', $tenantId)
                    ->count(),
            );
        });
    }

    public function favoriteTenantIds(string $userId, array $tenantIds = []): array
    {
        return TenantFavorite::query()
            // PUBLIC_READ_WITH_TENANT_FILTER: this buyer-owned lookup is constrained by user and optional tenant ids.
            ->withoutGlobalScope(TenantScope::class)
            ->where('user_id', $userId)
            ->when($tenantIds !== [], fn ($query) => $query->whereIn('tenant_id', $tenantIds))
            ->pluck('tenant_id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->values()
            ->all();
    }

    public function favoriteTenants(string $userId): Collection
    {
        $favoriteTenantIds = TenantFavorite::query()
            // PUBLIC_READ_WITH_TENANT_FILTER: the subquery is constrained to the authenticated buyer.
            ->withoutGlobalScope(TenantScope::class)
            ->where('user_id', $userId)
            ->select('tenant_id');

        return $this->publicTenantQuery()
            ->whereIn('id', $favoriteTenantIds)
            ->with(['city', 'category'])
            ->withCount('tenantFavorites')
            ->orderByDesc(
                TenantFavorite::query()
                    // PUBLIC_READ_WITH_TENANT_FILTER: ordering subquery is constrained to the authenticated buyer and related tenant.
                    ->withoutGlobalScope(TenantScope::class)
                    ->select('created_at')
                    ->whereColumn('tenant_id', 'tenants.id')
                    ->where('user_id', $userId)
                    ->limit(1)
            )
            ->get();
    }

    public function publicReviews(string $tenantId, ?string $userId = null): array
    {
        if (! $this->publicTenantExists($tenantId)) {
            return ['items' => [], 'average' => 0.0, 'count' => 0, 'reviewed_product_ids' => []];
        }

        $approved = Review::query()
            // PUBLIC_READ_WITH_TENANT_FILTER: approved review projection is constrained to one public tenant.
            ->withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenantId)
            ->where('is_approved', true);

        $aggregate = (clone $approved)
            ->selectRaw('COALESCE(AVG(rating), 0) as average, COUNT(*) as total')
            ->first();

        $items = (clone $approved)
            ->with(['user:id,name', 'product:id,tenant_id,name'])
            ->latest('created_at')
            ->limit(20)
            ->get()
            ->map(static fn (Review $review): array => [
                'id' => (int) $review->getKey(),
                'rating' => (int) $review->rating,
                'comment' => $review->comment,
                'created_at' => $review->created_at?->translatedFormat('d M Y'),
                'author' => $review->user?->name ?? 'Cliente verificado',
                'product_id' => $review->product_id ? (int) $review->product_id : null,
                'product_name' => $review->product?->name,
            ])
            ->all();

        $reviewedProductIds = $userId === null
            ? []
            : Review::query()
                // PUBLIC_READ_WITH_TENANT_FILTER: buyer state is constrained by tenant and authenticated user.
                ->withoutGlobalScope(TenantScope::class)
                ->where('tenant_id', $tenantId)
                ->where('user_id', $userId)
                ->whereNotNull('product_id')
                ->pluck('product_id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->values()
                ->all();

        return [
            'items' => $items,
            'average' => round((float) ($aggregate?->average ?? 0), 1),
            'count' => (int) ($aggregate?->total ?? 0),
            'reviewed_product_ids' => $reviewedProductIds,
        ];
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
        return $this->publicTenantQuery()
            ->whereKey($tenantId)
            ->exists();
    }

    private function publicTenantQuery()
    {
        return Tenant::query()
            ->where('is_active', true)
            ->whereIn('subscription_status', ['active', 'trial']);
    }
}
