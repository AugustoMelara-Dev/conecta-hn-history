<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Domain\TenantGovernance\Services\TenantResolver;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Psr\Log\LoggerInterface;
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
        private TenantResolver $tenantResolver,
        private LoggerInterface $log
    ) {}

    /**
     * Toggle the favorite status for a tenant.
     *
     * SECURITY: Uses TenantResolver for IDOR protection.
     */
    public function __invoke(Request $request): Response
    {
        $validated = $request->validate([
            'tenant_id' => ['required', 'integer'],
        ]);

        // SECURE: Use TenantResolver - only returns public, active tenants
        $tenant = $this->tenantResolver->findPublicById($validated['tenant_id']);

        if (! $tenant) {
            return back()
                ->setStatusCode(303)
                ->withErrors(['tenant_id' => 'Negocio no encontrado o no disponible.']);
        }

        $user = $request->user();
        $isFavorited = $user->toggleFavorite($tenant);

        $this->log->info('Favorite toggled', [
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'is_favorited' => $isFavorited,
        ]);

        return back()
            ->setStatusCode(303)
            ->with('success', $isFavorited ? 'Guardado en favoritos' : 'Eliminado de favoritos');
    }
}
