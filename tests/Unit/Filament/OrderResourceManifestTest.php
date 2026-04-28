<?php

declare(strict_types=1);

namespace Tests\Unit\Filament;

use App\Filament\App\Resources\OrderResource;
use App\Models\Order;
use App\Models\Tenant;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

final class OrderResourceManifestTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_resource_uses_rubro_manifest_modules_for_access(): void
    {
        Gate::shouldReceive('allows')
            ->with('viewAny', Order::class)
            ->andReturnTrue();

        $retailTenant = Tenant::factory()->create(['business_type' => 'retail']);

        Filament::setTenant($retailTenant, isQuiet: true);

        self::assertTrue(OrderResource::canViewAny());
    }

    public function test_order_resource_is_hidden_when_manifest_does_not_include_orders(): void
    {
        Gate::shouldReceive('allows')
            ->with('viewAny', Order::class)
            ->andReturnTrue();

        $serviceTenant = Tenant::factory()->create(['business_type' => 'service']);

        Filament::setTenant($serviceTenant, isQuiet: true);

        self::assertFalse(OrderResource::canViewAny());
    }
}
