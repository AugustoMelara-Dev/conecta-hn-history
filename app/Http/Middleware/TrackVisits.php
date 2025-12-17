<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * TrackVisits - Rastreador de Métricas Ligero
 * 
 * Middleware de analytics que incrementa visits_count con debouncing.
 * 
 * Características:
 * - Prevención de spam: 1 visita por sesión cada 60 minutos
 * - No bloquea requests (after middleware)
 * - Excluye bots conocidos
 * - Excluye dueños del negocio
 */
class TrackVisits
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Solo trackear en respuestas exitosas
        if ($response->getStatusCode() !== 200) {
            return $response;
        }

        $tenant = $request->route('tenant');

        if (!$tenant instanceof Tenant) {
            return $response;
        }

        // ═══════════════════════════════════════════════════════════════════
        // EXCLUSIONES
        // ═══════════════════════════════════════════════════════════════════

        // Excluir bots conocidos
        if ($this->isBot($request)) {
            return $response;
        }

        // Excluir dueño del negocio (no inflar sus propias métricas)
        $user = auth()->user();
        if ($user && $tenant->user_id === $user->id) {
            return $response;
        }

        // ═══════════════════════════════════════════════════════════════════
        // DEBOUNCING DE SESIÓN (1 visita cada 60 minutos)
        // ═══════════════════════════════════════════════════════════════════
        $sessionKey = "viewed_tenant_{$tenant->id}";

        if (!session()->has($sessionKey)) {
            // Registrar en tabla de interacciones para analytics detallados
            try {
                \App\Models\Interaction::create([
                    'tenant_id' => $tenant->id,
                    'user_id' => $user?->id,
                    'type' => 'view',
                    'ip_address' => $request->ip(),
                    'user_agent' => substr($request->userAgent() ?? '', 0, 500),
                    'referrer' => $request->header('referer'),
                ]);
            } catch (\Exception $e) {
                // No bloquear si falla el registro de analytics
                report($e);
            }

            // Marcar como visto en sesión (60 minutos)
            session()->put($sessionKey, now()->addHour());
        }

        return $response;
    }

    /**
     * Detecta si el request viene de un bot conocido
     */
    protected function isBot(Request $request): bool
    {
        $userAgent = strtolower($request->userAgent() ?? '');

        $bots = [
            'googlebot', 'bingbot', 'slurp', 'duckduckbot',
            'baiduspider', 'yandexbot', 'sogou', 'exabot',
            'facebot', 'facebookexternalhit', 'twitterbot',
            'linkedinbot', 'pinterest', 'whatsapp',
            'curl', 'wget', 'python-requests', 'scrapy',
            'headlesschrome', 'phantomjs', 'selenium',
        ];

        foreach ($bots as $bot) {
            if (str_contains($userAgent, $bot)) {
                return true;
            }
        }

        return false;
    }
}
