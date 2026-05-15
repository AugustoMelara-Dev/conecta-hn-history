<?php

declare(strict_types=1);

namespace Tests\Feature\DevOps;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class LaunchRoutesAvailabilityTest extends TestCase
{
    public function test_launch_critical_routes_are_registered(): void
    {
        $routes = collect(Route::getRoutes()->getRoutes())->map(fn ($route) => [
            'uri' => '/'.ltrim($route->uri(), '/'),
            'name' => $route->getName(),
            'domain' => $route->getDomain(),
        ]);

        $this->assertTrue($routes->contains(fn (array $route): bool => $route['uri'] === '/up'));
        $this->assertTrue($routes->contains(fn (array $route): bool => $route['uri'] === '/login' && $route['name'] === 'login'));
        $this->assertTrue($routes->contains(fn (array $route): bool => $route['uri'] === '/verify-email' && $route['name'] === 'verification.notice'));
        $this->assertTrue($routes->contains(fn (array $route): bool => $route['uri'] === '/' && $route['name'] === 'home'));
        $this->assertTrue($routes->contains(fn (array $route): bool => $route['uri'] === '/' && $route['name'] === 'app.directory' && $route['domain'] === config('conecta.directory_app_host')));
        $this->assertTrue($routes->contains(fn (array $route): bool => $route['uri'] === '/directorio' && $route['name'] === 'directory.index'));
        $this->assertTrue($routes->contains(fn (array $route): bool => $route['uri'] === '/directory' && $route['name'] === 'directory.legacy'));
        $this->assertTrue($routes->contains(fn (array $route): bool => $route['uri'] === '/registrar-negocio' && $route['name'] === 'tenant.register'));
        $this->assertTrue($routes->contains(fn (array $route): bool => $route['uri'] === '/dashboard' && $route['name'] === 'dashboard'));
        $this->assertTrue($routes->contains(fn (array $route): bool => $route['uri'] === '/app/{tenant}' && $route['name'] === 'filament.app.pages.dashboard'));
        $this->assertTrue($routes->contains(fn (array $route): bool => $route['uri'] === '/admin' && $route['name'] === 'filament.admin.pages.dashboard'));
        $this->assertFalse($routes->contains(fn (array $route): bool => str_starts_with($route['uri'], '/admin/{tenant}')));
    }
}
