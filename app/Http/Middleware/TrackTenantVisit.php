<?php

namespace App\Http\Middleware;

use App\Models\Interaction;
use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * ═══════════════════════════════════════════════════════════════════════════
 * TRACK TENANT VISIT MIDDLEWARE - Motor de Analytics
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * Este middleware rastrea visitas únicas a los micrositios de tenants.
 * Es crítico para las métricas del Plan PRO.
 *
 * THROTTLE INTELIGENTE:
 * - Usa caché para evitar registrar múltiples visitas del mismo usuario
 * - Clave única: hash(ip + tenant_id + user_agent)
 * - Expira en 1 hora
 *
 * EXCLUSIONES:
 * - Bots conocidos (Google, Bing, etc.)
 * - Assets estáticos
 * - Usuarios autenticados que son dueños del tenant
 */
class TrackTenantVisit
{
    /**
     * Patrones de User-Agent de bots a excluir
     */
    protected array $botPatterns = [
        'Googlebot',
        'Bingbot',
        'Slurp',
        'DuckDuckBot',
        'Baiduspider',
        'YandexBot',
        'facebookexternalhit',
        'Twitterbot',
        'LinkedInBot',
        'WhatsApp',
        'TelegramBot',
        'curl',
        'wget',
        'python-requests',
        'PostmanRuntime',
    ];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Procesar la respuesta primero
        $response = $next($request);

        // Solo rastrear en respuestas exitosas
        if (! $response->isSuccessful()) {
            return $response;
        }

        // Obtener el tenant de la ruta
        $tenant = $request->route('tenant');

        if (! $tenant instanceof Tenant) {
            return $response;
        }

        // Excluir bots
        if ($this->isBot($request)) {
            return $response;
        }

        // Excluir al dueño del tenant
        if ($this->isOwner($request, $tenant)) {
            return $response;
        }

        // Registrar visita con throttle
        $this->trackVisit($request, $tenant);

        return $response;
    }

    /**
     * Verifica si el request viene de un bot conocido
     */
    protected function isBot(Request $request): bool
    {
        $userAgent = $request->userAgent() ?? '';

        foreach ($this->botPatterns as $pattern) {
            if (stripos($userAgent, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Verifica si el usuario autenticado es dueño del tenant
     */
    protected function isOwner(Request $request, Tenant $tenant): bool
    {
        $user = $request->user();

        if (! $user) {
            return false;
        }

        // Verificar si el usuario es dueño del tenant
        return $tenant->user_id === $user->id;
    }

    /**
     * Registra la visita con throttle de 1 hora
     */
    protected function trackVisit(Request $request, Tenant $tenant): void
    {
        $ip = $request->ip();
        $userAgent = $request->userAgent() ?? 'unknown';

        // Generar clave única para el throttle
        $cacheKey = $this->generateCacheKey($ip, $tenant->id, $userAgent);

        // Verificar si ya se registró esta visita en la última hora
        if (Cache::has($cacheKey)) {
            return;
        }

        // Registrar la interacción
        try {
            Interaction::create([
                'tenant_id' => $tenant->id,
                'type' => 'visit',
                'ip_address' => $ip,
                'user_agent' => mb_substr($userAgent, 0, 500), // Limitar longitud
                'referrer' => mb_substr($request->header('Referer', ''), 0, 500),
                'url' => mb_substr($request->fullUrl(), 0, 500),
                'metadata' => [
                    'device' => $this->detectDevice($userAgent),
                    'country' => null, // TODO: GeoIP
                ],
            ]);

            // Marcar como registrada por 1 hora
            Cache::put($cacheKey, true, now()->addHour());

        } catch (\Exception $e) {
            // Silenciar errores para no afectar la experiencia del usuario
            \Log::warning('Failed to track visit', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Genera una clave de caché única para el throttle
     */
    protected function generateCacheKey(string $ip, int $tenantId, string $userAgent): string
    {
        // Hash para mantener la clave corta pero única
        $hash = md5("{$ip}_{$tenantId}_{$userAgent}");

        return "tenant_visit_{$hash}";
    }

    /**
     * Detecta el tipo de dispositivo basado en User-Agent
     */
    protected function detectDevice(string $userAgent): string
    {
        $userAgent = strtolower($userAgent);

        if (str_contains($userAgent, 'mobile') || str_contains($userAgent, 'android')) {
            return 'mobile';
        }

        if (str_contains($userAgent, 'tablet') || str_contains($userAgent, 'ipad')) {
            return 'tablet';
        }

        return 'desktop';
    }
}
