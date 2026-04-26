<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\TenantGovernance\Contracts\CapabilityManagerInterface;
use App\Application\TenantGovernance\Contracts\RubroManifestReaderInterface;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Auth\AuthRedirector;
use App\Support\Surface\SurfaceSplitBootstrapAdapter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

final class MeContextController extends Controller
{
    public function __construct(
        private readonly CapabilityManagerInterface $capabilityManager,
        private readonly RubroManifestReaderInterface $rubroManifest,
        private readonly SurfaceSplitBootstrapAdapter $bootstrapAdapter
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return response()->json([
                'error_code' => 'AUTH_REQUIRED',
                'message' => 'Debes iniciar sesion para consultar tu contexto.',
            ], 401);
        }

        $activeTenant = $this->resolveActiveTenant($request, $user);
        $context = AuthRedirector::contextFor($user);

        return response()->json([
            'data' => [
                'user' => [
                    'id' => (string) $user->getKey(),
                    'name' => (string) $user->name,
                    'email' => (string) $user->email,
                    'role' => $user->role->value,
                ],
                'audience' => $context['audience'],
                'home_surface' => $context['home_surface'],
                'redirect_to' => $context['redirect_to'],
                'allowed_surfaces' => [$context['home_surface']],
                'active_tenant' => $this->serializeTenant($activeTenant),
                'memberships' => $this->serializeMemberships($user),
                'feature_capability_map' => $this->rubroManifest->featureCapabilityMap(),
                'surface_bootstrap' => $this->bootstrapAdapter->forUser($user, $request),
            ],
            'meta' => [
                'generated_at' => now()->toIso8601String(),
            ],
        ]);
    }

    private function resolveActiveTenant(Request $request, User $user): ?Tenant
    {
        $containerTenant = app()->bound('currentTenant') ? app('currentTenant') : null;

        if ($containerTenant instanceof Tenant && $user->canAccessTenant($containerTenant)) {
            return $containerTenant;
        }

        if ($user->tenant instanceof Tenant) {
            return $user->tenant;
        }

        $membershipTenantId = $user->memberTenants()
            ->wherePivotNotNull('accepted_at')
            ->orderBy('tenants.name')
            ->value('tenants.id');

        if (! is_string($membershipTenantId) && ! is_int($membershipTenantId)) {
            return null;
        }

        return Tenant::query()->find($membershipTenantId);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function serializeTenant(?Tenant $tenant): ?array
    {
        if (! $tenant instanceof Tenant) {
            return null;
        }

        return [
            'id' => (string) $tenant->getKey(),
            'slug' => (string) ($tenant->slug ?? ''),
            'name' => (string) ($tenant->name ?? ''),
            'business_type' => $tenant->business_type->value,
            'business_segment_key' => (string) ($tenant->business_segment_key ?? ''),
            'capabilities' => array_values(array_map(
                static fn ($capability): string => $capability->value,
                $this->capabilityManager->allForTenant($tenant)
            )),
        ];
    }

    /**
     * @return list<array<string, string|null>>
     */
    private function serializeMemberships(User $user): array
    {
        $memberships = collect();

        if ($user->tenant instanceof Tenant) {
            $memberships->push([
                'tenant_id' => (string) $user->tenant->getKey(),
                'tenant_slug' => (string) ($user->tenant->slug ?? ''),
                'tenant_name' => (string) ($user->tenant->name ?? ''),
                'membership_role' => 'owner',
                'accepted_at' => now()->toIso8601String(),
            ]);
        }

        /** @var Collection<int, Tenant> $memberTenants */
        $memberTenants = $user->memberTenants()
            ->wherePivotNotNull('accepted_at')
            ->get();

        foreach ($memberTenants as $tenant) {
            $pivotRole = data_get($tenant, 'pivot.role');
            $pivotAcceptedAt = data_get($tenant, 'pivot.accepted_at');

            $memberships->push([
                'tenant_id' => (string) $tenant->getKey(),
                'tenant_slug' => (string) ($tenant->slug ?? ''),
                'tenant_name' => (string) ($tenant->name ?? ''),
                'membership_role' => is_string($pivotRole) ? $pivotRole : '',
                'accepted_at' => $pivotAcceptedAt instanceof \DateTimeInterface
                    ? $pivotAcceptedAt->format(\DateTimeInterface::ATOM)
                    : (is_string($pivotAcceptedAt) ? $pivotAcceptedAt : null),
            ]);
        }

        return $memberships
            ->unique('tenant_id')
            ->values()
            ->all();
    }

}
