<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\TenantGovernance\Services\TenantResolver;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * FollowController - Public Interaction API
 *
 * REFACTORED:
 * - Final class, strict types
 * - Uses TenantResolver instead of direct Route Model Binding (which uses implicit Eloquent query)
 */
final class FollowController extends Controller
{
    public function __construct(
        private readonly TenantResolver $tenantResolver
    ) {}

    /**
     * @param  string  $tenantId  Route parameter as string (UUID)
     */
    public function toggle(Request $request, string $tenantId): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // Validate Tenant Existence via Resolver (Safe, Public Check)
        $tenant = $this->tenantResolver->findPublicById($tenantId);

        if (! $tenant) {
            return response()->json(['message' => 'Negocio no encontrado o no disponible.'], 404);
        }

        $resolvedTenantId = $tenant->id();
        $isFollowing = $user->toggleFollowingTenant($resolvedTenantId);
        $followersCount = Tenant::query()
            ->find($resolvedTenantId)
            ?->followers()
            ->count() ?? 0;

        return response()->json([
            'following' => $isFollowing,
            'followers_count' => $followersCount,
            'message' => $isFollowing ? 'Ahora sigues a este negocio' : 'Has dejado de seguir a este negocio',
        ]);
    }
}
