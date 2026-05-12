<?php
declare(strict_types=1);

namespace Tests\Feature\DevOps;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class CanonicalUxFlowContractTest extends TestCase
{
    public function test_health_and_public_bridges_are_canonical(): void
    {
        $routes = collect(Route::getRoutes()->getRoutes())->map(fn ($route) => [
            'uri' => '/'.ltrim($route->uri(), '/'),
            'name' => $route->getName(),
            'action' => (string) ($route->getActionName() ?? ''),
        ]);

        $this->assertTrue($routes->contains(fn (array $route): bool => $route['uri'] === '/up' && $route['name'] === 'health.up'));
        $this->assertTrue($routes->contains(fn (array $route): bool => $route['uri'] === '/explore' && $route['name'] === 'public.explore'));
        $this->assertTrue($routes->contains(fn (array $route): bool => $route['uri'] === '/register' && $route['name'] === 'register'));
        $this->assertSame('/registrar-negocio', route('tenant.register', absolute: false));
    }

    public function test_bridge_routes_use_redirect_controller(): void
    {
        $exploreRoute = Route::getRoutes()->getByName('public.explore');
        $registerRoute = Route::getRoutes()->getByName('register');

        $this->assertNotNull($exploreRoute);
        $this->assertNotNull($registerRoute);
        $this->assertStringContainsString('RedirectController', (string) $exploreRoute?->getActionName());
        $this->assertStringContainsString('RedirectController', (string) $registerRoute?->getActionName());
    }
}
