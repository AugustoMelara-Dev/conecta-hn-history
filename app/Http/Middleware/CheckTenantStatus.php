<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Tenant\Services\TenantVisibilityService;
use App\Domain\TenantGovernance\Exceptions\TenantNotFoundException;
use App\Models\Tenant;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class CheckTenantStatus
{
    public function __construct(
        private readonly TenantVisibilityService $visibilityService
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $request->route('tenant');

        if (! $tenant instanceof Tenant) {
            return $next($request);
        }

        try {
            $this->visibilityService->check($tenant);

            return $next($request);
        } catch (TenantNotFoundException) {
            // Continue to preview authorization branch below.
        }

        $user = auth()->user();
        if ($user instanceof User) {
            $isSuperAdmin = $user->isSuperAdmin();
            $isOwner = (string) $tenant->user_id === (string) $user->id;
            $isAcceptedMember = $user->memberTenants()
                ->where('tenants.id', $tenant->getKey())
                ->wherePivotNotNull('accepted_at')
                ->exists();

            if ($isSuperAdmin || $isOwner || $isAcceptedMember) {
                $request->merge(['_preview_mode' => true]);
                session()->flash('tenant_preview_mode', true);
                session()->flash('tenant_preview_reason', $this->getPreviewReason($tenant));

                return $next($request);
            }
        }

        abort(404);
    }

    protected function getPreviewReason(Tenant $tenant): string
    {
        if (! $tenant->is_active) {
            return 'Este negocio esta desactivado temporalmente';
        }

        if (! in_array((string) $tenant->subscription_status, ['active', 'trial'], true)) {
            return 'La suscripcion actual no permite visibilidad publica';
        }

        if ((string) ($tenant->verification_status ?? 'provisional') === Tenant::VERIFICATION_STATUS_PROVISIONAL) {
            return 'Este negocio aun no completa la verificacion de titularidad';
        }

        if ((string) ($tenant->verification_status ?? '') === Tenant::VERIFICATION_STATUS_REJECTED) {
            return 'La verificacion del negocio fue rechazada';
        }

        if (! empty($tenant->publication_paused_at)) {
            return 'La publicacion fue pausada temporalmente';
        }

        return 'Este negocio no esta publicado';
    }
}
