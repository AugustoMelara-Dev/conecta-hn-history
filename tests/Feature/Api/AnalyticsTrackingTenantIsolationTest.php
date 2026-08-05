<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Domain\Analytics\Enums\InteractionType;
use App\Models\Interaction;
use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AnalyticsTrackingTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_tracking_accepts_a_product_owned_by_the_public_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $product = Product::factory()->forTenant($tenant)->create();

        $this->postJson('/api/v1/analytics/track', [
            'tenant_id' => $tenant->getKey(),
            'type' => InteractionType::CART_ADD->value,
            'product_id' => $product->getKey(),
        ])->assertAccepted();

        $this->assertDatabaseHas('interactions', [
            'tenant_id' => $tenant->getKey(),
            'product_id' => $product->getKey(),
            'type' => InteractionType::CART_ADD->value,
        ]);
    }

    public function test_public_tracking_rejects_a_product_owned_by_another_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $otherTenant = Tenant::factory()->create();
        $foreignProduct = Product::factory()->forTenant($otherTenant)->create();

        $this->postJson('/api/v1/analytics/track', [
            'tenant_id' => $tenant->getKey(),
            'type' => InteractionType::CART_ADD->value,
            'product_id' => $foreignProduct->getKey(),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('product_id');

        self::assertSame(
            0,
            Interaction::query()->withoutGlobalScopes()->count(),
        );
    }

    public function test_whatsapp_tracking_creates_a_lead_with_the_tenant_uuid(): void
    {
        $tenant = Tenant::factory()->create();
        $product = Product::factory()->forTenant($tenant)->create();

        $this->postJson('/api/v1/analytics/track', [
            'tenant_id' => $tenant->getKey(),
            'type' => InteractionType::WHATSAPP_CLICK->value,
            'product_id' => $product->getKey(),
            'metadata' => ['message' => 'Quiero información'],
        ])->assertAccepted();

        $this->assertDatabaseHas('leads', [
            'tenant_id' => $tenant->getKey(),
            'product_id' => $product->getKey(),
            'source' => InteractionType::WHATSAPP_CLICK->value,
            'message' => 'Quiero información',
        ]);
    }

    public function test_public_tracking_rejects_a_non_uuid_tenant_identifier(): void
    {
        $this->postJson('/api/v1/analytics/track', [
            'tenant_id' => '1',
            'type' => InteractionType::CART_ADD->value,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('tenant_id');
    }
}
