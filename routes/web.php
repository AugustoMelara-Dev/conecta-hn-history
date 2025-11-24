<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Public Routes (Landing & Directory)
|--------------------------------------------------------------------------
*/

Route::get('/', [\App\Http\Controllers\WelcomeController::class, 'index'])->name('welcome');

// Auth Demo Route
Route::get('/auth-demo', function () {
    return Inertia::render('AuthDemo');
})->name('auth.demo');

Route::get('/directorio', [\App\Http\Controllers\DirectoryController::class, 'index'])->name('directory.index');

/*
|--------------------------------------------------------------------------
| Location Routes
|--------------------------------------------------------------------------
*/

Route::post('/location/set', [\App\Http\Controllers\LocationController::class, 'setCity'])->name('location.set');
Route::post('/location/clear', [\App\Http\Controllers\LocationController::class, 'clearCity'])->name('location.clear');
Route::get('/api/cities', [\App\Http\Controllers\LocationController::class, 'getCities'])->name('cities.all');
Route::get('/api/search', [\App\Http\Controllers\Api\SearchController::class, 'index'])->name('api.search');

/*
|--------------------------------------------------------------------------
| Auth Routes
|--------------------------------------------------------------------------
*/

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    // Unified Profile/Account Route
    Route::get('/mi-cuenta', [ProfileController::class, 'edit'])->name('mi-cuenta.edit');
    Route::patch('/mi-cuenta', [ProfileController::class, 'update'])->name('mi-cuenta.update');
    Route::delete('/mi-cuenta', [ProfileController::class, 'destroy'])->name('mi-cuenta.destroy');

    // Analytics API
    Route::get('/analytics/data', [\App\Http\Controllers\AnalyticsController::class, 'index'])->name('analytics.data');

    Route::post('/reviews', [\App\Http\Controllers\ReviewController::class, 'store'])->name('reviews.store');
});

require __DIR__.'/auth.php';

/*
|--------------------------------------------------------------------------
| Tenant Microsite Routes (Wildcard - MUST BE LAST)
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {
    Route::post('/tenants/{tenant}/toggle-follow', [\App\Http\Controllers\FollowController::class, 'toggle'])->name('tenant.toggle-follow');
});

// Pilar 8: Track tenant visits with Redis HyperLogLog
Route::get('/{tenant:slug}', [App\Http\Controllers\TenantController::class, 'show'])
    ->middleware('track.tenant')
    ->name('tenant.show');
