<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Domain\TenantGovernance\Services\TenantResolver;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * ToggleFollowController - Inertia-Compatible Follow Toggle
 */
final class ToggleFollowController extends Controller
{
    public function __construct(
        private readonly TenantResolver $tenantResolver,
        private readonly LoggerInterface $log
    ) {}

    public function __invoke(Request $request): Response
    {
        $validated = $request->validate([
            'tenant_id' => ['required', 'string'],
        ]);

        $tenant = $this->tenantResolver->findPublicById($validated['tenant_id']);

        if (! $tenant) {
            return back()
                ->setStatusCode(303)
                ->withErrors(['tenant_id' => 'Negocio no encontrado o no disponible.']);
        }

        $user = $request->user();
        $changes = $user->following()->toggle($tenant->id);
        $isFollowing = ! empty($changes['attached']);

        $this->log->info('Follow toggled', [
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'is_following' => $isFollowing,
        ]);

        return back()
            ->setStatusCode(303)
            ->with('success', $isFollowing ? 'Ahora sigues a este negocio' : 'Has dejado de seguir a este negocio');
    }
}
