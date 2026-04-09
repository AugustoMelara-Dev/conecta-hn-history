<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Settings\GeneralSettings;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class CheckMaintenanceMode
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $settings = app(GeneralSettings::class);
        } catch (Throwable $exception) {
            // Vercel preview can run without DB; fail-open on maintenance check.
            Log::warning('Maintenance settings unavailable, skipping maintenance gate.', [
                'error' => $exception->getMessage(),
                'path' => $request->path(),
            ]);

            return $next($request);
        }

        if (! $settings->maintenance_mode) {
            return $next($request);
        }

        $user = auth()->user();
        if ($user instanceof User && $user->isSuperAdmin()) {
            return $next($request);
        }

        foreach (['admin/login', 'admin', 'livewire/message'] as $path) {
            if ($request->is($path) || $request->is($path.'/*')) {
                return $next($request);
            }
        }

        return response()
            ->view('errors.maintenance', [], 503)
            ->header('Retry-After', '3600');
    }
}
