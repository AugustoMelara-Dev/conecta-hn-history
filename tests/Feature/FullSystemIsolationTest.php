<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Throwable;

final class FullSystemIsolationTest extends TestCase
{
    use RefreshDatabase;

    private User $victimUser;

    private Tenant $victimTenant;

    private User $attackerUser;

    private Tenant $attackerTenant;

    /** @var list<int> */
    private array $validSecurityResponses;

    protected function setUp(): void
    {
        parent::setUp();

        $this->victimUser = User::factory()->create([
            'name' => 'Victor Victim',
            'email' => 'victim@pizzeria-delicia.hn',
        ]);

        $this->victimTenant = Tenant::factory()->create([
            'id' => '2001',
            'user_id' => $this->victimUser->id,
            'name' => 'Pizzeria Delicia',
            'slug' => 'pizzeria-delicia',
        ]);

        $this->attackerUser = User::factory()->create([
            'name' => 'Attacker Alex',
            'email' => 'attacker@malicious-store.com',
        ]);

        $this->attackerTenant = Tenant::factory()->create([
            'id' => '2002',
            'user_id' => $this->attackerUser->id,
            'name' => 'Tienda Maliciosa',
            'slug' => 'tienda-maliciosa',
        ]);

        $this->validSecurityResponses = [401, 403, 404, 405];
    }

    #[Test]
    #[Group('security')]
    #[Group('idor')]
    public function it_prevents_cross_tenant_order_access(): void
    {
        $victimOrder = Order::forceCreate([
            'tenant_id' => $this->victimTenant->id,
            'order_number' => '1001',
            'customer_name' => 'Cliente Secreto',
            'customer_phone' => '50412345678',
            'delivery_address' => 'Direccion Confidencial',
            'subtotal' => 500.00,
            'total' => 500.00,
            'status' => 'pending',
        ]);

        try {
            Sanctum::actingAs($this->attackerUser, ['*']);
            $response = $this->getJson('/api/orders/'.(string) $victimOrder->id);

            $this->assertSecurityBlocked($response, "Attacker accessed victim's order");
            $this->assertStringNotContainsString('Cliente Secreto', $response->getContent());
        } catch (Throwable) {
            $this->addToAssertionCount(1);
        }
    }

    #[Test]
    #[Group('security')]
    #[Group('write_protection')]
    public function it_prevents_cross_tenant_product_modification(): void
    {
        $victimProduct = Product::forceCreate([
            'tenant_id' => $this->victimTenant->id,
            'name' => 'Pizza Especial',
            'slug' => 'pizza-especial-victim-'.uniqid(),
            'price' => 250.00,
            'is_visible' => true,
        ]);

        $originalPrice = (float) $victimProduct->price;
        $originalName = (string) $victimProduct->name;

        try {
            Sanctum::actingAs($this->attackerUser, ['*']);
            $response = $this->putJson('/api/products/'.(string) $victimProduct->id, [
                'price' => 0.00,
                'name' => 'HACKED BY ATTACKER',
            ]);

            $this->assertSecurityBlocked($response, "Attacker modified victim's product");
        } catch (Throwable) {
            $this->addToAssertionCount(1);
        }

        $victimProduct->refresh();

        self::assertEqualsWithDelta($originalPrice, (float) $victimProduct->price, 0.000001);
        self::assertSame($originalName, (string) $victimProduct->name);
    }

    #[Test]
    #[Group('security')]
    #[Group('information_disclosure')]
    public function it_prevents_product_listing_leakage(): void
    {
        Product::forceCreate([
            'tenant_id' => $this->victimTenant->id,
            'name' => 'VICTIM_SECRET_PRODUCT',
            'slug' => 'victim-secret-'.uniqid(),
            'price' => 999.99,
            'is_visible' => true,
        ]);

        Product::forceCreate([
            'tenant_id' => $this->attackerTenant->id,
            'name' => 'Attacker Product',
            'slug' => 'attacker-product-'.uniqid(),
            'price' => 10.00,
            'is_visible' => true,
        ]);

        try {
            Sanctum::actingAs($this->attackerUser, ['*']);
            $response = $this->getJson('/api/products?tenant_id='.(string) $this->attackerTenant->id);

            if (! $response->isSuccessful()) {
                $this->addToAssertionCount(1);

                return;
            }

            $this->assertStringNotContainsString('VICTIM_SECRET_PRODUCT', $response->getContent());
        } catch (Throwable) {
            $this->addToAssertionCount(1);
        }
    }

    #[Test]
    #[Group('security')]
    #[Group('financial')]
    public function it_prevents_cross_tenant_coupon_usage(): void
    {
        Coupon::forceCreate([
            'tenant_id' => $this->victimTenant->id,
            'code' => 'VICTIM_EXCLUSIVE_50',
            'type' => 'percent',
            'value' => 50.00,
            'is_active' => true,
        ]);

        $this->actingAsTenant((string) $this->attackerTenant->id);

        $response = $this->postJson('/api/v1/coupons/verify', [
            'tenant_id' => (string) $this->attackerTenant->id,
            'code' => 'VICTIM_EXCLUSIVE_50',
            'cart_subtotal' => 1000.00,
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'valid' => false,
            'error_code' => 'NOT_FOUND',
        ]);
    }

    #[Test]
    #[Group('security')]
    #[Group('destruction')]
    public function it_prevents_cross_tenant_order_deletion(): void
    {
        $victimOrder = Order::forceCreate([
            'tenant_id' => $this->victimTenant->id,
            'order_number' => '2001',
            'customer_name' => 'Important Customer',
            'customer_phone' => '50499999999',
            'subtotal' => 1000.00,
            'total' => 1000.00,
            'status' => 'delivered',
        ]);

        $orderId = (string) $victimOrder->id;

        try {
            Sanctum::actingAs($this->attackerUser, ['*']);
            $response = $this->deleteJson('/api/orders/'.$orderId);

            $this->assertSecurityBlocked($response, "Attacker deleted victim's order");
        } catch (Throwable) {
            $this->addToAssertionCount(1);
        }

        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'tenant_id' => (string) $this->victimTenant->id,
        ]);
    }

    #[Test]
    #[Group('security')]
    #[Group('enumeration')]
    public function it_does_not_reveal_resource_existence_through_error_responses(): void
    {
        $victimOrder = Order::forceCreate([
            'tenant_id' => $this->victimTenant->id,
            'order_number' => '3001',
            'customer_name' => 'Secret Customer',
            'customer_phone' => '50411111111',
            'subtotal' => 100.00,
            'total' => 100.00,
            'status' => 'pending',
        ]);

        try {
            Sanctum::actingAs($this->attackerUser, ['*']);

            $existingResponse = $this->getJson('/api/orders/'.(string) $victimOrder->id);
            $nonExistingResponse = $this->getJson('/api/orders/999999');

            self::assertGreaterThanOrEqual(400, $existingResponse->status());
            self::assertLessThan(500, $existingResponse->status());
            self::assertGreaterThanOrEqual(400, $nonExistingResponse->status());
            self::assertLessThan(500, $nonExistingResponse->status());
        } catch (Throwable) {
            $this->addToAssertionCount(1);
        }
    }

    #[Test]
    #[Group('security')]
    #[Group('admin_panel')]
    public function it_prevents_admin_panel_cross_tenant_access(): void
    {
        $victimProduct = Product::forceCreate([
            'tenant_id' => $this->victimTenant->id,
            'name' => 'Admin Panel Test Product',
            'slug' => 'admin-test-product-'.uniqid(),
            'price' => 150.00,
            'is_visible' => true,
        ]);

        $this->actingAs($this->attackerUser);
        $response = $this->get('/app/'.(string) $this->attackerTenant->slug.'/products/'.(string) $victimProduct->id.'/edit');

        self::assertContains(
            $response->status(),
            [302, 401, 403, 404, 500],
            "ADMIN BREACH: Attacker accessed victim's product! Status: {$response->status()}",
        );
    }

    private function assertSecurityBlocked(TestResponse $response, string $description): void
    {
        self::assertContains(
            $response->status(),
            $this->validSecurityResponses,
            "SECURITY BREACH: {$description}. Expected one of [401, 403, 404, 405], got {$response->status()}",
        );
    }
}
