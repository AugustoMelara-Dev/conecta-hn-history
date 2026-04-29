<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * CouponIsolationTest - Multi-Tenant Security Penetration Tests
 *
 * SECURITY OBJECTIVE: Zero Trust Verification
 * These tests simulate malicious attempts to exploit cross-tenant vulnerabilities.
 *
 * ARCHITECTURE CONTEXT:
 * Vito Business OS uses a Shared Database model where multiple tenants coexist.
 * A failure in tenant isolation could lead to:
 * - Financial fraud (using another tenant's discounts)
 * - Data leakage (seeing competitor's coupon strategies)
 * - Reputation damage (loss of platform trust)
 *
 * TEST PHILOSOPHY:
 * These tests verify NEGATIVE outcomes - attacks that MUST fail.
 */
class CouponIsolationTest extends TestCase
{
    use DatabaseTransactions;

    protected Tenant $tenantA;

    protected Tenant $tenantB;

    /**
     * Set up test environment with two competing tenants.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Keep the legacy scenarios on numeric-string IDs while the API also accepts UUID tenants.
        $this->tenantA = Tenant::factory()->create([
            'id' => '1001',
            'name' => 'Pizzeria Mario',
            'slug' => 'pizzeria-mario',
        ]);

        $this->tenantB = Tenant::factory()->create([
            'id' => '1002',
            'name' => 'Cafe Delicia',
            'slug' => 'cafe-delicia',
        ]);
    }

    public function it_prevents_cross_tenant_coupon_theft(): void
    {
        Coupon::forceCreate([
            'tenant_id' => $this->tenantA->id,
            'code' => 'SECRET_A',
            'type' => 'fixed',
            'value' => 100.00,
            'is_active' => true,
        ]);

        $this->actingAsTenant((string) $this->tenantB->id);

        $response = $this->postJson('/api/v1/coupons/verify', [
            'tenant_id' => $this->tenantB->id,
            'code' => 'SECRET_A',
            'cart_subtotal' => 500.00,
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'valid' => false,
            'error_code' => 'NOT_FOUND',
        ]);

        $this->assertStringNotContainsString('Tenant A', $response->json('error') ?? '');
        $this->assertStringNotContainsString('Pizzeria', $response->json('error') ?? '');
    }

    public function it_respects_namespace_isolation_for_same_code_coupons(): void
    {
        Coupon::forceCreate([
            'tenant_id' => $this->tenantA->id,
            'code' => 'VERANO',
            'type' => 'fixed',
            'value' => 100.00,
            'is_active' => true,
        ]);

        Coupon::forceCreate([
            'tenant_id' => $this->tenantB->id,
            'code' => 'VERANO',
            'type' => 'fixed',
            'value' => 500.00,
            'is_active' => true,
        ]);

        $this->actingAsTenant((string) $this->tenantA->id);

        $responseA = $this->postJson('/api/v1/coupons/verify', [
            'tenant_id' => $this->tenantA->id,
            'code' => 'VERANO',
            'cart_subtotal' => 1000.00,
        ]);

        $responseA->assertStatus(200);
        $responseA->assertJson([
            'valid' => true,
            'discount_amount' => 100,
            'new_total' => 900,
        ]);

        $this->actingAsTenant((string) $this->tenantB->id);

        $responseB = $this->postJson('/api/v1/coupons/verify', [
            'tenant_id' => $this->tenantB->id,
            'code' => 'VERANO',
            'cart_subtotal' => 1000.00,
        ]);

        $responseB->assertStatus(200);
        $responseB->assertJson([
            'valid' => true,
            'discount_amount' => 500.00,
            'new_total' => 500.00,
        ]);
    }

    public function it_prevents_coupon_list_leakage_across_tenants(): void
    {
        Coupon::forceCreate([
            'tenant_id' => $this->tenantA->id,
            'code' => 'SECRETO_A',
            'type' => 'percent',
            'value' => 50.00,
            'is_active' => true,
        ]);

        Coupon::forceCreate([
            'tenant_id' => $this->tenantA->id,
            'code' => 'PROMO_A',
            'type' => 'fixed',
            'value' => 25.00,
            'is_active' => true,
        ]);

        Coupon::forceCreate([
            'tenant_id' => $this->tenantB->id,
            'code' => 'PUBLICO_B',
            'type' => 'fixed',
            'value' => 10.00,
            'is_active' => true,
        ]);

        $this->actingAsTenant((string) $this->tenantB->id);

        $response = $this->getJson('/api/v1/coupons?tenant_id='.$this->tenantB->id);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $coupons = $response->json('data');

        $this->assertCount(1, $coupons);
        $this->assertEquals('PUBLICO_B', $coupons[0]['code']);

        $codes = array_column($coupons, 'code');
        $this->assertNotContains('SECRETO_A', $codes);
        $this->assertNotContains('PROMO_A', $codes);
    }

    public function test_coupon_api_accepts_uuid_tenant_ids(): void
    {
        $tenant = Tenant::factory()->create([
            'name' => 'UUID Tenant',
            'slug' => 'uuid-tenant',
        ]);

        Coupon::forceCreate([
            'tenant_id' => $tenant->id,
            'code' => 'UUID_OK',
            'type' => 'fixed',
            'value' => 25.00,
            'is_active' => true,
        ]);

        $this->actingAsTenant((string) $tenant->id);

        $response = $this->postJson('/api/v1/coupons/verify', [
            'tenant_id' => $tenant->id,
            'code' => 'UUID_OK',
            'cart_subtotal' => 100.00,
        ]);

        $response->assertOk();
        $response->assertJson([
            'valid' => true,
            'discount_amount' => 25.00,
            'new_total' => 75.00,
        ]);
    }

    public function it_calculates_percentage_discount_precisely(): void
    {
        Coupon::forceCreate([
            'tenant_id' => $this->tenantA->id,
            'code' => 'DIEZ',
            'type' => 'percent',
            'value' => 10.00,
            'is_active' => true,
        ]);

        $this->actingAsTenant((string) $this->tenantA->id);

        $response = $this->postJson('/api/v1/coupons/verify', [
            'tenant_id' => $this->tenantA->id,
            'code' => 'DIEZ',
            'cart_subtotal' => 100.00,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'valid' => true,
            'discount_amount' => 10.00,
            'new_total' => 90.00,
        ]);
    }

    public function it_enforces_minimum_spend_restriction(): void
    {
        Coupon::forceCreate([
            'tenant_id' => $this->tenantA->id,
            'code' => 'MINIMO500',
            'type' => 'fixed',
            'value' => 50.00,
            'min_spend' => 500.00,
            'is_active' => true,
        ]);

        $this->actingAsTenant((string) $this->tenantA->id);

        $response = $this->postJson('/api/v1/coupons/verify', [
            'tenant_id' => $this->tenantA->id,
            'code' => 'MINIMO500',
            'cart_subtotal' => 200.00,
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'valid' => false,
            'error_code' => 'MIN_SPEND',
        ]);

        $this->assertStringContainsString('300', $response->json('error'));
    }

    public function it_does_not_leak_internal_ids_in_error_messages(): void
    {
        $this->actingAsTenant((string) $this->tenantA->id);

        $response = $this->postJson('/api/v1/coupons/verify', [
            'tenant_id' => $this->tenantA->id,
            'code' => 'NONEXISTENT',
            'cart_subtotal' => 100.00,
        ]);

        $response->assertStatus(422);

        $content = $response->getContent();

        $this->assertStringNotContainsString('tenant_id', $content);
        $this->assertStringNotContainsString('"id":', $content);
        $this->assertStringNotContainsString($this->tenantA->id, $content);
    }
}
