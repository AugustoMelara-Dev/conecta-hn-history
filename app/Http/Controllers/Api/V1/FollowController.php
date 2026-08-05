<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Directory\Contracts\PublicTenantEngagementGatewayInterface;
use App\Http\Controllers\Controller;
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
        private readonly PublicTenantEngagementGatewayInterface $engagement
    ) {}

    /**
     * @param  string  $tenantId  Tenant UUID from the route
     */
    public function toggle(Request $request, string $tenantId): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $result = $this->engagement->toggleFollow((string) $user->getKey(), $tenantId);

        if ($result === null) {
            return response()->json(['message' => 'Negocio no encontrado o no disponible.'], 404);
        }

        return response()->json([
            'following' => $result->engaged,
            'followers_count' => $result->count,
            'message' => $result->engaged ? 'Ahora sigues a este negocio' : 'Has dejado de seguir a este negocio',
        ]);
    }
}
