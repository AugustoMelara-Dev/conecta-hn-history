<?php

namespace App\Http\Middleware;

use App\Settings\GeneralSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * CheckMaintenanceMode - Kill Switch Inteligente
 *
 * Protocolo de bloqueo discriminatorio que permite acceso
 * administrativo mientras bloquea el acceso público.
 *
 * Lógica:
 * - Si maintenance_mode = false → Permitir todo el tráfico
 * - Si maintenance_mode = true:
 *   - super_admin → Permitir acceso
 *   - Otros usuarios → Mostrar página de mantenimiento (503)
 */
class CheckMaintenanceMode
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Obtener configuración desde la Torre de Control
        $settings = app(GeneralSettings::class);

        // Si el modo mantenimiento está DESACTIVADO, permitir todo
        if (! $settings->maintenance_mode) {
            return $next($request);
        }

        // ═══════════════════════════════════════════════════════════════════
        // MODO MANTENIMIENTO ACTIVO - Evaluar excepciones
        // ═══════════════════════════════════════════════════════════════════

        $user = auth()->user();

        // Excepción 1: Usuario autenticado con rol super_admin
        if ($user && $user->hasRole('super_admin')) {
            return $next($request);
        }

        // Excepción 2: Permitir acceso a rutas de login administrativo
        // para que los admins puedan autenticarse y desactivar el modo
        $allowedPaths = [
            'admin/login',
            'admin',
            'livewire/message',
        ];

        foreach ($allowedPaths as $path) {
            if ($request->is($path) || $request->is($path.'/*')) {
                return $next($request);
            }
        }

        // ═══════════════════════════════════════════════════════════════════
        // BLOQUEO: Usuario común - Mostrar vista de mantenimiento
        // ═══════════════════════════════════════════════════════════════════

        // Opción 1: Renderizar vista personalizada
        if (view()->exists('errors.maintenance')) {
            return response()
                ->view('errors.maintenance', [], 503)
                ->header('Retry-After', 3600);
        }

        // Opción 2: Fallback simple si la vista no existe
        abort(503, 'Estamos realizando mejoras en la plataforma. Vuelve pronto.');
    }
}

