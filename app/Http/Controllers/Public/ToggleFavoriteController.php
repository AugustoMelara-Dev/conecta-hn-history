<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * ToggleFavoriteController - Inertia-Compatible Favorite Toggle
 *
 * ARCHITECTURE:
 * - Uses polymorphic favorites via User->toggleFavorite()
 * - Returns HTTP 303 (See Other) for Inertia scroll preservation
 * - Validates tenant existence before toggle
 *
 * SECURITY:
 * - Requires authentication (middleware)
 * - Validates tenant_id exists
 */
class ToggleFavoriteController extends Controller
{
    /**
     * Toggle the favorite status for a tenant.
     *
     * CRITICAL: Uses back()->setStatusCode(303) for Inertia.
     * This preserves scroll position and page state.
     * Standard 302 redirects would reset the viewport.
     *
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function __invoke(Request $request)
    {
        // Validate input
        $validated = $request->validate([
            'tenant_id' => ['required', 'integer'],
        ]);

        // Zero Trust: Only allow favoriting visible, active tenants
        $tenant = Tenant::where('id', $validated['tenant_id'])
            ->where('is_active', true)
            ->first();

        if (!$tenant) {
            return back()
                ->setStatusCode(303)
                ->withErrors(['tenant_id' => 'Negocio no encontrado o no disponible.']);
        }

        // Get authenticated user
        $user = $request->user();

        // Toggle favorite using User model method
        $isFavorited = $user->toggleFavorite($tenant);

        // Log for analytics
        Log::info('Favorite toggled', [
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'is_favorited' => $isFavorited,
        ]);

        // Return with HTTP 303 for Inertia scroll preservation
        return back()
            ->setStatusCode(303)
            ->with('success', $isFavorited ? 'Guardado en favoritos' : 'Eliminado de favoritos');
    }
}
