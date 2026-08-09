<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Application\Directory\Contracts\PublicTenantEngagementGatewayInterface;
use App\Http\Controllers\Controller;
use App\Http\Resources\TenantCardResource;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class FavoritesController extends Controller
{
    public function __construct(
        private readonly PublicTenantEngagementGatewayInterface $engagement,
    ) {}

    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $tenants = $this->engagement->favoriteTenants((string) $user->getKey());

        return Inertia::render('Favorites/Index', [
            'tenants' => TenantCardResource::collection($tenants)->resolve($request),
            'total' => $tenants->count(),
        ]);
    }
}
