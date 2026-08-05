<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Application\Directory\Contracts\PublicTenantEngagementGatewayInterface;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * ToggleFavoriteController - IDOR-Hardened Favorite Toggle
 *
 * REFACTORED:
 * - Uses TenantResolver instead of direct Tenant::where()
 * - Only allows favoriting PUBLIC, ACTIVE tenants
 * - No direct Eloquent queries in controller
 */
final class ToggleFavoriteController extends Controller
{
    public function __construct(
        private readonly PublicTenantEngagementGatewayInterface $engagement
    ) {}

    /**
     * Toggle the favorite status for a tenant.
     *
     * SECURITY: Uses TenantResolver for IDOR protection.
     */
    public function __invoke(Request $request): Response
    {
        $validated = $request->validate([
            'tenant_id' => ['required', 'uuid'],
        ]);

        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        $result = $this->engagement->toggleFavorite(
            (string) $user->getKey(),
            (string) $validated['tenant_id'],
        );

        if ($result === null) {
            return back()
                ->setStatusCode(303)
                ->withErrors(['tenant_id' => 'Negocio no encontrado o no disponible.']);
        }

        Log::info('Favorite toggled', [
            'user_id' => $user->id,
            'tenant_id' => $result->tenantId,
            'is_favorited' => $result->engaged,
        ]);

        return back()
            ->setStatusCode(303)
            ->with('success', $result->engaged ? 'Guardado en favoritos' : 'Eliminado de favoritos');
    }
}
