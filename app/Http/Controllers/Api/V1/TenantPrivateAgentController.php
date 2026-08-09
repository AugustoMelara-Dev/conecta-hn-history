<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Access\Contracts\TenantPermissionGuardInterface;
use App\Domain\Access\Enums\Permission;
use App\Domain\Access\Enums\Role;
use App\Domain\TenantGovernance\Enums\Capability;
use App\Http\Controllers\Controller;
use App\Infrastructure\Tenant\TenantPrivateAgentService;
use App\Support\Tenancy\RequestTenantResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class TenantPrivateAgentController extends Controller
{
    public function chat(
        Request $request,
        RequestTenantResolver $tenantResolver,
        TenantPermissionGuardInterface $permissionGuard,
        TenantPrivateAgentService $agent,
    ): JsonResponse {
        $data = $request->validate([
            'message' => ['required', 'string', 'min:3', 'max:240'],
        ]);

        $user = $request->user();
        abort_unless(is_object($user), 401);

        $tenant = $tenantResolver->resolveExplicit($request);
        abort_unless(is_object($tenant), 403);

        abort_unless($this->tenantIsAllowlisted($tenant), 403, 'El agente privado todavia no esta habilitado para este negocio.');

        $isOwner = (string) $tenant->user_id === (string) $user->id
            || $tenant->memberships()
                ->where('user_id', $user->id)
                ->where('role', Role::OWNER)
                ->whereNotNull('accepted_at')
                ->exists();

        $allowed = $user->isSuperAdmin()
            || $isOwner
            || $permissionGuard->allows($user, $tenant, Capability::AI_CONTENT, Permission::AI_USE);

        abort_unless($allowed, 403, 'No tienes permiso para usar el agente privado.');

        return response()->json($agent->answer($tenant, (string) $data['message']));
    }

    private function tenantIsAllowlisted(object $tenant): bool
    {
        $allowed = config('conecta.features.tenant_private_agent_tenants', []);

        if (! is_array($allowed) || $allowed === []) {
            return false;
        }

        return in_array((string) $tenant->getKey(), $allowed, true)
            || in_array((string) ($tenant->slug ?? ''), $allowed, true);
    }
}
