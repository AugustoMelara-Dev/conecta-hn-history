<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\HttpFoundation\Response;

class TrackTenantView
{
    /**
     * Handle an incoming request.
     *
     * Pilar 8: Real-time analytics using Redis HyperLogLog
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only track successful responses
        if ($response->getStatusCode() !== 200) {
            return $response;
        }

        // Get tenant from route parameter
        $tenant = $request->route('tenant');

        if (! $tenant) {
            return $response;
        }

        // Don't track if visitor is the tenant owner
        if (auth()->check() && auth()->id() === $tenant->user_id) {
            return $response;
        }

        // Basic bot detection
        $userAgent = $request->userAgent() ?? '';
        $botPatterns = ['bot', 'crawler', 'spider', 'scraper', 'curl', 'wget'];

        foreach ($botPatterns as $pattern) {
            if (stripos($userAgent, $pattern) !== false) {
                return $response;
            }
        }

        try {
            $date = now()->format('Y-m-d');
            $cache = app(\Illuminate\Contracts\Cache\Repository::class);

            // Hash PII to prevent GDPR violations
            $visitorHash = hash('sha256', $request->ip() . '|' . $userAgent);
            
            // Store unique visitors in a set (simulating HyperLogLog logic via Cache store)
            $visitsKey = "analytics:tenants:{$tenant->id}:visits:{$date}";
            $visits = $cache->get($visitsKey, []);
            
            if (!in_array($visitorHash, $visits)) {
                $visits[] = $visitorHash;
                $cache->put($visitsKey, $visits, now()->addHours(48));
            }
            
            // Track total visits (simple counter)
            $totalKey = "analytics:tenants:{$tenant->id}:total:{$date}";
            $cache->increment($totalKey);
            // Re-apply TTL since increment might reset it depending on cache driver
            $cache->put($totalKey, $cache->get($totalKey), now()->addHours(48));
            
        } catch (\Exception $e) {
            // Fallback: Log the error but don't break the user experience
            \Log::warning('Analytics tracking failed: '.$e->getMessage());
        }

        return $response;
    }
}
