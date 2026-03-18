<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Settings\GeneralSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class CheckMaintenanceMode
{
    public function handle(Request $request, Closure $next): Response
    {
        $settings = app(GeneralSettings::class);

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
