<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure;

use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PortableOrderingTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_priority_order_is_database_portable(): void
    {
        Tenant::factory()->create(['plan_type' => 'semilla']);
        Tenant::factory()->create(['plan_type' => 'enterprise']);
        Tenant::factory()->create(['plan_type' => 'pro']);

        self::assertSame(
            ['enterprise', 'pro', 'semilla'],
            Tenant::query()->inPriorityOrder()->getQuery()->pluck('plan_type')->all(),
        );
    }

    public function test_impulse_candidates_accept_uuid_tenant_and_preserve_the_selected_set(): void
    {
        $tenant = Tenant::factory()->create();
        $otherTenant = Tenant::factory()->create();

        $expected = Product::factory()->count(4)->create([
            'tenant_id' => $tenant->id,
            'is_impulse_buy' => true,
            'is_visible' => true,
        ])->modelKeys();
        Product::factory()->create([
            'tenant_id' => $otherTenant->id,
            'is_impulse_buy' => true,
            'is_visible' => true,
        ]);

        $actual = Product::getRandomImpulseCandidates((string) $tenant->id, count: 4);

        self::assertCount(4, $actual);
        self::assertEqualsCanonicalizing($expected, $actual->modelKeys());
        foreach ($actual as $product) {
            self::assertInstanceOf(Product::class, $product);
            self::assertSame((string) $tenant->id, (string) $product->tenant_id);
        }
    }
}
