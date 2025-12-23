<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CouponIsolationTest - Multi-Tenant Security Penetration Tests
 *
 * SECURITY OBJECTIVE: Zero Trust Verification
 * These tests simulate malicious attempts to exploit cross-tenant vulnerabilities.
 *
 * ARCHITECTURE CONTEXT:
 * Conecta HN uses a Shared Database model where multiple tenants coexist.
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
    use RefreshDatabase;

    protected Tenant $tenantA;

    protected Tenant $tenantB;

    /**
     * Set up test environment with two competing tenants.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Create two isolated tenant environments
        $this->tenantA = Tenant::factory()->create([
            'name' => 'Pizzería Mario',
            'slug' => 'pizzeria-mario',
        ]);

        $this->tenantB = Tenant::factory()->create([
            'name' => 'Café Delicia',
            'slug' => 'cafe-delicia',
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // ATTACK SCENARIO 1: Cross-Tenant Coupon Theft
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * @test
     *
     * @group security
     * @group isolation
     *
     * ATTACK VECTOR: A malicious user from Tenant B attempts to use
     * a secret coupon code that belongs exclusively to Tenant A.
     *
     * EXPECTED RESULT: The system MUST reject this request with a 422 error
     * and NOT reveal any information about the coupon's existence in Tenant A.
     */
    public function it_prevents_cross_tenant_coupon_theft(): void
    {
        // ARRANGE: Create a secret coupon only for Tenant A
        $secretCoupon = Coupon::forceCreate([
            'tenant_id' => $this->tenantA->id,
            'code' => 'SECRET_A',
            'type' => 'fixed',
            'value' => 100.00,
            'is_active' => true,
        ]);

        // ACT: Attacker from Tenant B tries to use Tenant A's coupon
        $response = $this->postJson('/api/coupons/verify', [
            'tenant_id' => $this->tenantB->id,  // Attacker's context
            'code' => 'SECRET_A',                // Victim's coupon
            'cart_subtotal' => 500.00,
        ]);

        // ASSERT: Request MUST fail
        $response->assertStatus(422);
        $response->assertJson([
            'valid' => false,
            'error_code' => 'NOT_FOUND',
        ]);

        // SECURITY: Error message must not reveal coupon exists elsewhere
        $this->assertStringNotContainsString(
            'Tenant A',
            $response->json('error') ?? ''
        );
        $this->assertStringNotContainsString(
            'Pizzería',
            $response->json('error') ?? ''
        );
    }

    // ═══════════════════════════════════════════════════════════════════════
    // ATTACK SCENARIO 2: Namespace Collision Exploitation
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * @test
     *
     * @group security
     * @group isolation
     *
     * ATTACK VECTOR: Both tenants have a coupon with the same code.
     * Verify that each tenant ONLY resolves their own coupon, not the other's.
     *
     * CATASTROPHIC FAILURE: If Tenant A's request applies Tenant B's higher discount,
     * the tenant isolation is fundamentally broken.
     */
    public function it_respects_namespace_isolation_for_same_code_coupons(): void
    {
        // ARRANGE: Same code, different values, different owners
        Coupon::forceCreate([
            'tenant_id' => $this->tenantA->id,
            'code' => 'VERANO',
            'type' => 'fixed',
            'value' => 100.00,  // Tenant A: L.100 discount
            'is_active' => true,
        ]);

        Coupon::forceCreate([
            'tenant_id' => $this->tenantB->id,
            'code' => 'VERANO',
            'type' => 'fixed',
            'value' => 500.00,  // Tenant B: L.500 discount (much higher!)
            'is_active' => true,
        ]);

        // ACT: Request verification in Tenant A's context
        $responseA = $this->postJson('/api/coupons/verify', [
            'tenant_id' => $this->tenantA->id,
            'code' => 'VERANO',
            'cart_subtotal' => 1000.00,
        ]);

        // ASSERT: Tenant A gets THEIR discount (100), not B's (500)
        $responseA->assertStatus(200);
        $responseA->assertJson([
            'valid' => true,
            'discount_amount' => 100.00,
            'new_total' => 900.00,
        ]);

        // ACT: Request verification in Tenant B's context
        $responseB = $this->postJson('/api/coupons/verify', [
            'tenant_id' => $this->tenantB->id,
            'code' => 'VERANO',
            'cart_subtotal' => 1000.00,
        ]);

        // ASSERT: Tenant B gets THEIR discount (500), not A's (100)
        $responseB->assertStatus(200);
        $responseB->assertJson([
            'valid' => true,
            'discount_amount' => 500.00,
            'new_total' => 500.00,
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // ATTACK SCENARIO 3: Index Endpoint Information Leakage
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * @test
     *
     * @group security
     * @group isolation
     *
     * ATTACK VECTOR: Tenant B requests the list of available coupons.
     * The response must contain ONLY Tenant B's coupons, with zero visibility
     * into Tenant A's promotional strategies.
     *
     * INFORMATION DISCLOSURE: If any coupon from Tenant A appears in the response,
     * this represents a serious competitive intelligence leak.
     */
    public function it_prevents_coupon_list_leakage_across_tenants(): void
    {
        // ARRANGE: Create coupons for both tenants
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

        // ACT: Request coupon list for Tenant B
        $response = $this->getJson('/api/coupons?tenant_id='.$this->tenantB->id);

        // ASSERT: Response is successful
        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        // Get the coupons from response
        $coupons = $response->json('data');

        // ASSERT: Only 1 coupon (Tenant B's)
        $this->assertCount(1, $coupons);

        // ASSERT: The coupon is Tenant B's
        $this->assertEquals('PUBLICO_B', $coupons[0]['code']);

        // SECURITY: Verify NO Tenant A coupons leaked
        $codes = array_column($coupons, 'code');
        $this->assertNotContains('SECRETO_A', $codes);
        $this->assertNotContains('PROMO_A', $codes);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // ATTACK SCENARIO 4: Percentage Discount Calculation Integrity
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * @test
     *
     * @group financial
     *
     * VERIFICATION: Percentage discount calculations must be mathematically precise.
     * 10% of 100 = 10, not 9.99 or 10.01
     */
    public function it_calculates_percentage_discount_precisely(): void
    {
        Coupon::forceCreate([
            'tenant_id' => $this->tenantA->id,
            'code' => 'DIEZ',
            'type' => 'percent',
            'value' => 10.00,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/coupons/verify', [
            'tenant_id' => $this->tenantA->id,
            'code' => 'DIEZ',
            'cart_subtotal' => 100.00,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'valid' => true,
            'discount_amount' => 10.00,  // Exactly 10%
            'new_total' => 90.00,
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // ATTACK SCENARIO 5: Minimum Spend Restriction Bypass
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * @test
     *
     * @group business_rules
     *
     * VERIFICATION: Coupon with min_spend MUST reject carts below threshold.
     */
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

        // Attempt with cart below minimum
        $response = $this->postJson('/api/coupons/verify', [
            'tenant_id' => $this->tenantA->id,
            'code' => 'MINIMO500',
            'cart_subtotal' => 200.00,  // Below 500 minimum
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'valid' => false,
            'error_code' => 'MIN_SPEND',
        ]);

        // Verify specific remaining amount in error message
        $this->assertStringContainsString('300', $response->json('error'));
    }

    // ═══════════════════════════════════════════════════════════════════════
    // ATTACK SCENARIO 6: Error Message Information Sanitization
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * @test
     *
     * @group security
     *
     * VERIFICATION: Error messages must not leak sensitive IDs or internal state.
     */
    public function it_does_not_leak_internal_ids_in_error_messages(): void
    {
        // Attempt to verify non-existent coupon
        $response = $this->postJson('/api/coupons/verify', [
            'tenant_id' => $this->tenantA->id,
            'code' => 'NONEXISTENT',
            'cart_subtotal' => 100.00,
        ]);

        $response->assertStatus(422);

        // Get the full response content
        $content = $response->getContent();

        // SECURITY: No tenant_id or internal IDs should appear in response
        $this->assertStringNotContainsString('tenant_id', $content);
        $this->assertStringNotContainsString('"id":', $content);
        $this->assertStringNotContainsString($this->tenantA->id, $content);
    }
}
