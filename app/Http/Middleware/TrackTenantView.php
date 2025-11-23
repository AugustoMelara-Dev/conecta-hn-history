<?php

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
        
        if (!$tenant) {
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
            $key = "analytics:tenants:{$tenant->id}:visits:{$date}";
            
            // Unique visitor identifier (IP + User Agent)
            $visitorId = $request->ip() . '|' . $userAgent;
            
            // Add to HyperLogLog for unique count
            Redis::pfadd($key, [$visitorId]);
            
            // Set TTL to 48 hours (gives sync job time to process)
            Redis::expire($key, 60 * 60 * 48);
            
            // Optional: Track total visits (simple counter)
            $totalKey = "analytics:tenants:{$tenant->id}:total:{$date}";
            Redis::incr($totalKey);
            Redis::expire($totalKey, 60 * 60 * 48);
            
        } catch (\Exception $e) {
            // Fail silently - don't break the user experience
            \Log::error('Analytics tracking failed', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage()
            ]);
        }

        return $response;
    }
}
