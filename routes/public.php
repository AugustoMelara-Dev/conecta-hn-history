<?php

use App\Models\City;
use App\Models\Tenant;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Public Utility Routes
|--------------------------------------------------------------------------
|
| This file only hosts supplemental public routes that are loaded after the
| main web tree. The canonical definitions for `/`, `/explore` and
| `/registrar-negocio` live in `routes/web.php`.
|
| Keeping a single source of truth prevents duplicated public entry points
| and inconsistent page/component ownership.
|
*/

if (! function_exists('publicStaticAssetResponse')) {
    function publicStaticAssetResponse(string $relativePath, string $contentType)
    {
        $path = public_path($relativePath);

        abort_unless(is_file($path), 404);

        return response()->file($path, [
            'Content-Type' => $contentType,
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}

Route::get('/manifest.webmanifest', fn () => publicStaticAssetResponse('manifest.webmanifest', 'application/manifest+json; charset=utf-8'))
    ->name('public.manifest');

Route::get('/favicon.svg', fn () => publicStaticAssetResponse('favicon.svg', 'image/svg+xml'))
    ->name('public.favicon.svg');

Route::get('/favicon.ico', fn () => publicStaticAssetResponse('icon-192x192.png', 'image/png'))
    ->name('public.favicon.ico');

Route::get('/icon-192x192.png', fn () => publicStaticAssetResponse('icon-192x192.png', 'image/png'))
    ->name('public.icon.192');

Route::get('/icon-512x512.png', fn () => publicStaticAssetResponse('icon-512x512.png', 'image/png'))
    ->name('public.icon.512');

// ═══════════════════════════════════════════════════════════════════════════
// TENANT SUBDOMAIN ROUTES - DISABLED (Path-based fallback active in web.php)
// ═══════════════════════════════════════════════════════════════════════════
// Subdomain routing disabled for local dev + Ziggy compatibility.
// Path-based fallback: /t/{tenant:slug} defined in routes/web.php.
// To restore subdomain routing for production, uncomment the block below
// and remove the path-based group from web.php.
// ═══════════════════════════════════════════════════════════════════════════

// Route::domain('{tenant}.' . config('app.domain'))->group(function () {
//     // TENANT MICROSITE - Public Profile (CQRS: READ-ONLY Controller)
//     Route::get('/', [\App\Http\Controllers\Public\TenantMicrositeController::class, 'show'])
//         ->name('tenant.microsite.show');
//
//     // BOOKING WIZARD - Public Appointment Booking (SERVICE-type tenants)
//     Route::prefix('book')->group(function () {
//         Route::get('/', [\App\Http\Controllers\Public\BookingController::class, 'index'])
//             ->name('public.booking.index');
//         Route::post('/', [\App\Http\Controllers\Public\BookingController::class, 'store'])
//             ->name('public.booking.store');
//         Route::get('/providers/{service}', [\App\Http\Controllers\Public\BookingController::class, 'getProviders'])
//             ->name('public.booking.providers');
//         Route::get('/slots', [\App\Http\Controllers\Public\BookingController::class, 'getSlots'])
//             ->name('public.booking.slots');
//         Route::get('/success', [\App\Http\Controllers\Public\BookingController::class, 'success'])
//             ->name('public.booking.success');
//     });
// });

// ═══════════════════════════════════════════════════════════════════════════
// GUEST APPOINTMENT PORTAL - Signed URL Access (No Auth Required)
// These are domain-agnostic since they use signed URLs
// ═══════════════════════════════════════════════════════════════════════════
Route::get('/appointments/{appointment}/manage', [\App\Http\Controllers\Public\AppointmentManagementController::class, 'show'])
    ->name('public.appointment.manage')
    ->middleware('signed');

Route::post('/appointments/{appointment}/cancel', [\App\Http\Controllers\Public\AppointmentManagementController::class, 'cancel'])
    ->name('public.appointment.cancel')
    ->middleware('signed');

if (! function_exists('publicRouteTablesAvailable')) {
    /**
     * @param  array<int, string>  $tables
     */
    function publicRouteTablesAvailable(array $tables): bool
    {
        foreach ($tables as $table) {
            if (! Schema::hasTable($table)) {
                return false;
            }
        }

        return true;
    }
}

if (! function_exists('publicRouteStats')) {
    /**
     * @return array{total_tenants: int, total_cities: int}
     */
    function publicRouteStats(): array
    {
        if (! publicRouteTablesAvailable(['tenants', 'cities'])) {
            return [
                'total_tenants' => 0,
                'total_cities' => 0,
            ];
        }

        try {
            return [
                'total_tenants' => Tenant::where('is_active', true)->count(),
                'total_cities' => City::has('tenants')->count(),
            ];
        } catch (QueryException) {
            return [
                'total_tenants' => 0,
                'total_cities' => 0,
            ];
        }
    }
}
