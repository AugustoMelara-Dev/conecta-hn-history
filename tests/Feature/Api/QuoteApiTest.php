<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Domain\Access\Enums\Role;
use App\Models\Location;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\GlobalMarketFixture;
use Tests\TestCase;

final class QuoteApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_tenant_creates_and_transitions_a_server_priced_quote(): void
    {
        [$owner, $tenant, $location, $product] = $this->catalog();
        [, $other] = $this->catalog('quote-api-other@example.test');
        Sanctum::actingAs($owner);

        $created = $this->withHeader('X-Tenant-ID', (string) $tenant->id)
            ->postJson('/api/v1/quotes', [
                'tenant_id' => $other->id,
                'idempotency_key' => 'quote-api:create:1',
                'location_id' => $location->id,
                'items' => [[
                    'product_id' => $product->id,
                    'quantity' => 2,
                ]],
            ])
            ->assertCreated()
            ->assertJsonPath('data.tenant_id', (string) $tenant->id)
            ->assertJsonPath('data.currency', 'CAD')
            ->assertJsonPath('data.total_minor', 5_000)
            ->assertJsonPath('data.status', 'draft');
        $quoteId = (string) $created->json('data.id');

        $this->withHeader('X-Tenant-ID', (string) $tenant->id)
            ->postJson("/api/v1/quotes/{$quoteId}/transition", [
                'idempotency_key' => 'quote-api:issue:1',
                'target_status' => 'issued',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'issued')
            ->assertJsonPath('outcome', 'ok');

        $this->withHeader('X-Tenant-ID', (string) $tenant->id)
            ->postJson("/api/v1/quotes/{$quoteId}/transition", [
                'idempotency_key' => 'quote-api:accept:1',
                'target_status' => 'accepted',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'accepted');
    }

    public function test_quote_api_requires_entitlement_and_hides_cross_tenant_quotes(): void
    {
        [$owner, $tenant, $location, $product] = $this->catalog();
        [$otherOwner, $other, $otherLocation, $otherProduct] = $this->catalog(
            'quote-api-isolation@example.test',
        );
        Sanctum::actingAs($owner);
        $quoteId = (string) $this->withHeader('X-Tenant-ID', (string) $tenant->id)
            ->postJson('/api/v1/quotes', [
                'idempotency_key' => 'quote-api:isolation:create',
                'location_id' => $location->id,
                'items' => [['product_id' => $product->id, 'quantity' => 1]],
            ])
            ->assertCreated()
            ->json('data.id');

        Sanctum::actingAs($otherOwner);
        $this->withHeader('X-Tenant-ID', (string) $other->id)
            ->postJson("/api/v1/quotes/{$quoteId}/transition", [
                'idempotency_key' => 'quote-api:isolation:issue',
                'target_status' => 'issued',
            ])
            ->assertNotFound();

        $freeOwner = User::factory()->create(['email' => 'quote-free@example.test']);
        $free = Tenant::factory()->forUser($freeOwner)->create([
            'business_type' => 'retail',
            'commercial_mode' => 'products',
        ]);
        $freeLocation = GlobalMarketFixture::assignCanada($free);
        $free->memberships()->create([
            'user_id' => $freeOwner->getKey(),
            'role' => Role::OWNER,
            'accepted_at' => now(),
        ]);
        $freeProduct = Product::factory()->forTenant($free)->create([
            'price' => 10,
            'price_minor' => 1_000,
            'currency' => 'CAD',
        ]);
        Sanctum::actingAs($freeOwner);
        $this->withHeader('X-Tenant-ID', (string) $free->id)
            ->postJson('/api/v1/quotes', [
                'idempotency_key' => 'quote-api:free:create',
                'location_id' => $freeLocation->id,
                'items' => [['product_id' => $freeProduct->id, 'quantity' => 1]],
            ])
            ->assertForbidden();
        self::assertNotSame($location->id, $otherLocation->id);
        self::assertNotSame($product->id, $otherProduct->id);
    }

    /** @return array{User, Tenant, Location, Product} */
    private function catalog(string $email = 'quote-api-owner@example.test'): array
    {
        $owner = User::factory()->create(['email' => $email]);
        $tenant = Tenant::factory()->forUser($owner)->pro()->create([
            'business_type' => 'service',
            'commercial_mode' => 'hybrid',
        ]);
        $location = GlobalMarketFixture::assignCanada($tenant);
        $tenant->memberships()->create([
            'user_id' => $owner->getKey(),
            'role' => Role::OWNER,
            'accepted_at' => now(),
        ]);
        $product = Product::factory()->forTenant($tenant)->create([
            'name' => 'API product',
            'price' => 25,
            'price_minor' => 2_500,
            'currency' => 'CAD',
        ]);

        return [$owner, $tenant, $location, $product];
    }
}
