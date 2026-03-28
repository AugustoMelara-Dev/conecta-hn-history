<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class LegacyWorkspaceRoutePolicyTest extends TestCase
{
    public function test_workspace_write_routes_are_limited_to_the_explicit_legacy_compatibility_allow_list(): void
    {
        $workspaceWrites = collect(Route::getRoutes()->getRoutes())
            ->filter(function ($route): bool {
                $uri = ltrim($route->uri(), '/');

                if (! str_starts_with($uri, 'workspace/')) {
                    return false;
                }

                return collect($route->methods())
                    ->contains(fn (string $method): bool => in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true));
            })
            ->mapWithKeys(fn ($route): array => [$route->getName() => [
                'uri' => $route->uri(),
                'methods' => collect($route->methods())
                    ->reject(fn (string $method): bool => in_array($method, ['GET', 'HEAD'], true))
                    ->values()
                    ->all(),
            ]])
            ->sortKeys();

        $expected = collect([
            'tenant.billing.report-payment' => [
                'uri' => 'workspace/billing/report-payment',
                'methods' => ['POST'],
            ],
            'tenant.billing.upgrade' => [
                'uri' => 'workspace/billing/upgrade',
                'methods' => ['POST'],
            ],
            'tenant.landing-page.update' => [
                'uri' => 'workspace/landing-page',
                'methods' => ['PUT'],
            ],
            'tenant.orders.update-status' => [
                'uri' => 'workspace/orders/{order}/status',
                'methods' => ['PUT'],
            ],
            'tenant.products.destroy' => [
                'uri' => 'workspace/products/{product}',
                'methods' => ['DELETE'],
            ],
            'tenant.products.store' => [
                'uri' => 'workspace/products',
                'methods' => ['POST'],
            ],
            'tenant.products.toggle-visibility' => [
                'uri' => 'workspace/products/{product}/toggle-visibility',
                'methods' => ['PUT'],
            ],
            'tenant.products.update' => [
                'uri' => 'workspace/products/{product}',
                'methods' => ['PUT'],
            ],
            'tenant.settings.update-profile' => [
                'uri' => 'workspace/settings/profile',
                'methods' => ['POST'],
            ],
            'tenant.settings.update-schedule' => [
                'uri' => 'workspace/settings/schedule',
                'methods' => ['PUT'],
            ],
        ])->sortKeys();

        expect($workspaceWrites->all())->toBe($expected->all());
    }
}
