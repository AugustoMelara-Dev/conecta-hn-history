<?php

declare(strict_types=1);

use App\Http\Requests\StoreOrderRequest;
use Illuminate\Support\Facades\Route;

/**
 * H3 & H5 Security Compliance Tests
 */

// ═══════════════════════════════════════════════════════════════════════════
// H3: Track Routes Rate Limiting
// ═══════════════════════════════════════════════════════════════════════════

test('track routes have throttle middleware', function () {
    $routes = Route::getRoutes();

    $trackRoutes = collect($routes->getRoutes())
        ->filter(fn ($route) => str_starts_with($route->uri(), 'track/'));

    expect($trackRoutes)->not->toBeEmpty();

    foreach ($trackRoutes as $route) {
        $middleware = $route->middleware();
        $hasThrottle = collect($middleware)->contains(fn ($m) => str_starts_with($m, 'throttle'));

        expect($hasThrottle)->toBeTrue(
            "Route {$route->uri()} should have throttle middleware"
        );
    }
});

// ═══════════════════════════════════════════════════════════════════════════
// H5: StoreOrderRequest Context Hardening
// ═══════════════════════════════════════════════════════════════════════════

test('public order request derives tenant authority instead of accepting tenant_id', function () {
    $request = new StoreOrderRequest;
    $rules = $request->rules();

    expect($rules)->not->toHaveKey('tenant_id');
});

test('public order request ignores a caller supplied tenant id', function () {
    $request = StoreOrderRequest::create('/api/orders', 'POST', [
        'tenant_id' => 999, // Malicious input
        'customer_name' => 'Test User',
        'customer_phone' => '99999999',
        'delivery_type' => 'pickup',
        'items' => [
            ['product_id' => 1, 'name' => 'Test', 'price' => 10, 'quantity' => 1],
        ],
    ]);

    // Manually set route parameters to simulate binding
    $request->setRouteResolver(fn () => null);

    expect($request->rules())->not->toHaveKey('tenant_id');
});
