<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * CheckTenantStatus - Guardián de Estado del Tenant
 *
 * Middleware de seguridad que valida el acceso a micrositios.
 *
 * Reglas de Acceso:
 * 1. Bloquea si status !== 'approved' OR is_active === false
 * 2. Excepción: Dueño del negocio o Super Admin pueden ver en modo preview
 * 3. Inyecta indicador de "modo preview" para mostrar alerta visual
 */
class CheckTenantStatus
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $request->route('tenant');

        // Si no hay tenant o no es instancia válida, continuar (404 nativo)
        if (! $tenant instanceof Tenant) {
            return $next($request);
        }

        // Verificar si el negocio está publicado y activo
        $isPublished = $tenant->status === 'approved' && $tenant->is_active === true;

        if ($isPublished) {
            // Acceso público normal
            return $next($request);
        }

        // ═══════════════════════════════════════════════════════════════════
        // MODO PREVIEW: Solo para dueño o Super Admin
        // ═══════════════════════════════════════════════════════════════════
        $user = auth()->user();

        if ($user) {
            // Verificar si es Super Admin
            $isSuperAdmin = $user->hasRole('super_admin') || $user->email === 'admin@conectahn.com';

            // Verificar si es dueño del negocio
            $isOwner = $tenant->user_id === $user->id;

            if ($isSuperAdmin || $isOwner) {
                // Inyectar indicador de modo preview en la request
                $request->merge(['_preview_mode' => true]);

                // También guardar en session para acceder desde Blade
                session()->flash('tenant_preview_mode', true);
                session()->flash('tenant_preview_reason', $this->getPreviewReason($tenant));

                return $next($request);
            }
        }

        // ═══════════════════════════════════════════════════════════════════
        // BLOQUEO: No tiene permisos, mostrar 404
        // ═══════════════════════════════════════════════════════════════════
        abort(404);
    }

    /**
     * Obtiene la razón por la cual está en modo preview
     */
    protected function getPreviewReason(Tenant $tenant): string
    {
        if ($tenant->status === 'pending') {
            return 'Este negocio está pendiente de aprobación';
        }

        if ($tenant->status === 'rejected') {
            return 'Este negocio fue rechazado. Revisa los comentarios.';
        }

        if (! $tenant->is_active) {
            return 'Este negocio está desactivado temporalmente';
        }

        return 'Este negocio no está publicado';
    }
}
