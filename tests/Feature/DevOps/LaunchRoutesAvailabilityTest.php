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
        ]);

        $this->assertTrue($routes->contains(fn (array $route): bool => $route['uri'] === '/up'));
        $this->assertTrue($routes->contains(fn (array $route): bool => $route['uri'] === '/login' && $route['name'] === 'login'));
        $this->assertTrue($routes->contains(fn (array $route): bool => $route['uri'] === '/verify-email' && $route['name'] === 'verification.notice'));
    }
}

