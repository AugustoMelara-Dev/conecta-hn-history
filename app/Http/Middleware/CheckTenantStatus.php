<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class CheckTenantStatus
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $request->route('tenant');

        if (! $tenant instanceof Tenant) {
            return $next($request);
        }

        $isPublished = $tenant->status === 'approved' && $tenant->is_active === true;
        if ($isPublished) {
            return $next($request);
        }

        $user = auth()->user();
        if ($user instanceof User) {
            $isSuperAdmin = $user->isSuperAdmin();
            $isOwner = (string) $tenant->user_id === (string) $user->id;

            if ($isSuperAdmin || $isOwner) {
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
        if ($tenant->status === 'pending') {
            return 'Este negocio esta pendiente de aprobacion';
        }

        if ($tenant->status === 'rejected') {
            return 'Este negocio fue rechazado. Revisa los comentarios.';
        }

        if (! $tenant->is_active) {
            return 'Este negocio esta desactivado temporalmente';
        }

        return 'Este negocio no esta publicado';
    }
}
