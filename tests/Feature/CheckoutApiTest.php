<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CheckoutApiTest - API Gateway Security Tests
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  API SECURITY TESTING: Verifies data sanitization and response format  ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * These tests verify that the CheckoutController:
 * 1. Returns correct JSON structure
 * 2. Does NOT leak sensitive database columns
 * 3. Handles validation errors properly
 *
 * @covers \App\Http\Controllers\CheckoutController
 */
class CheckoutApiTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private string $apiEndpoint;

    protected function setUp(): void
    {
        parent::setUp();

        // Create tenant for tests
        $this->tenant = Tenant::factory()->create([
            'name' => 'API Test Business',
            'slug' => 'api-test',
        ]);

        // Bind tenant to container
        app()->instance('currentTenant', $this->tenant);

        $this->apiEndpoint = '/api/v1/checkout/validate-coupon';
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // API CONTRACT VERIFICATION (HAPPY PATH)
    // ═══════════════════════════════════════════════════════════════════════════════

    /**
     * @test
     * Verifies the API returns correct JSON structure for valid coupons.
     *
     * SCENARIO: Valid coupon code submitted via POST
     * EXPECTED: HTTP 200 with structured JSON containing required keys
     */
    public function it_validates_coupon_via_api_and_returns_correct_json(): void
    {
        // Arrange: Create a valid coupon
        Coupon::factory()->create([
            'tenant_id' => $this->tenant->id,
            'code' => 'APITEST20',
            'type' => Coupon::TYPE_PERCENT,
            'value' => 20.00,
            'is_active' => true,
            'starts_at' => null,
            'expires_at' => null,
            'usage_limit' => null,
            'min_spend' => null,
        ]);

        // Act: POST to validate-coupon endpoint
        $response = $this->postJson($this->apiEndpoint, [
            'code' => 'APITEST20',
            'cart_total' => 100.00,
        ]);

        // Assert: Status 200
        $response->assertStatus(200);

        // Assert: JSON structure contains ONLY expected keys
        $response->assertJsonStructure([
            'valid',
            'code',
            'discount_type',
            'discount_amount',
            'new_total',
            'message',
        ]);

        // Assert: Correct values
        $json = $response->json();
        $this->assertTrue($json['valid']);
        $this->assertSame('APITEST20', $json['code']);
        $this->assertSame('percent', $json['discount_type']);
        $this->assertEquals(20.00, $json['discount_amount']);
        $this->assertEquals(80.00, $json['new_total']);
    }

    /**
     * @test
     * Verifies fixed amount coupon returns correct calculation.
     */
    public function it_calculates_fixed_discount_correctly(): void
    {
        Coupon::factory()->create([
            'tenant_id' => $this->tenant->id,
            'code' => 'FIXED50',
            'type' => Coupon::TYPE_FIXED,
            'value' => 50.00,
            'is_active' => true,
        ]);

        $response = $this->postJson($this->apiEndpoint, [
            'code' => 'FIXED50',
            'cart_total' => 200.00,
        ]);

        $response->assertStatus(200);
        $json = $response->json();

        $this->assertEquals(50.00, $json['discount_amount']);
        $this->assertEquals(150.00, $json['new_total']);
    }

    /**
     * @test
     * Verifies free shipping coupon returns shipping cost as discount.
     */
    public function it_returns_shipping_cost_for_free_shipping_coupon(): void
    {
        Coupon::factory()->create([
            'tenant_id' => $this->tenant->id,
            'code' => 'FREESHIP',
            'type' => Coupon::TYPE_FREE_SHIPPING,
            'value' => 0,
            'is_active' => true,
        ]);

        $response = $this->postJson($this->apiEndpoint, [
            'code' => 'FREESHIP',
            'cart_total' => 100.00,
            'shipping_cost' => 45.00,
        ]);

        $response->assertStatus(200);
        $json = $response->json();

        $this->assertEquals(45.00, $json['discount_amount']);
        $this->assertStringContainsString('Envío gratis', $json['message']);
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // DATA LEAKAGE PREVENTION (CRITICAL SECURITY)
    // ═══════════════════════════════════════════════════════════════════════════════

    /**
     * @test
     * CRITICAL SECURITY: Verifies sensitive database columns are NOT exposed.
     *
     * SCENARIO: Successful coupon validation
     * EXPECTED: Response must NOT contain internal database fields
     *
     * This test prevents accidental exposure of:
     * - Database IDs (enables enumeration attacks)
     * - Internal notes (confidential business data)
     * - Usage limits (competitive intelligence)
     * - Timestamps (system metadata)
     */
    public function it_does_not_leak_sensitive_model_data(): void
    {
        Coupon::factory()->create([
            'tenant_id' => $this->tenant->id,
            'code' => 'SECURITYTEST',
            'type' => Coupon::TYPE_PERCENT,
            'value' => 15.00,
            'is_active' => true,
            'description' => 'Internal notes - SHOULD NOT BE EXPOSED',
            'usage_limit' => 1000,
            'usage_count' => 500,
            'min_spend' => 50.00,
        ]);

        $response = $this->postJson($this->apiEndpoint, [
            'code' => 'SECURITYTEST',
            'cart_total' => 100.00,
        ]);

        $response->assertStatus(200);

        // CRITICAL ASSERTIONS: These keys must NOT exist in response
        $response->assertJsonMissing(['id']);
        $response->assertJsonMissing(['tenant_id']);
        $response->assertJsonMissing(['description']);
        $response->assertJsonMissing(['internal_notes']);
        $response->assertJsonMissing(['usage_limit']);
        $response->assertJsonMissing(['usage_count']);
        $response->assertJsonMissing(['max_discount']);
        $response->assertJsonMissing(['min_spend']);
        $response->assertJsonMissing(['created_at']);
        $response->assertJsonMissing(['updated_at']);
        $response->assertJsonMissing(['starts_at']);
        $response->assertJsonMissing(['expires_at']);

        // Additional verification: only allowed keys present
        $allowedKeys = ['valid', 'code', 'discount_type', 'discount_amount', 'new_total', 'message'];
        $actualKeys = array_keys($response->json());

        foreach ($actualKeys as $key) {
            $this->assertContains(
                $key,
                $allowedKeys,
                "Unexpected key '$key' found in API response. Potential data leakage."
            );
        }
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // VALIDATION FAILURE HANDLING
    // ═══════════════════════════════════════════════════════════════════════════════

    /**
     * @test
     * Verifies that missing 'code' field returns 422 validation error.
     */
    public function it_returns_422_for_missing_code(): void
    {
        $response = $this->postJson($this->apiEndpoint, [
            // 'code' => MISSING
            'cart_total' => 100.00,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['code']);
    }

    /**
     * @test
     * Verifies that missing 'cart_total' returns 422 validation error.
     */
    public function it_returns_422_for_missing_cart_total(): void
    {
        $response = $this->postJson($this->apiEndpoint, [
            'code' => 'ANYCODE',
            // 'cart_total' => MISSING
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['cart_total']);
    }

    /**
     * @test
     * Verifies that empty body returns 422 with both field errors.
     */
    public function it_returns_422_for_empty_body(): void
    {
        $response = $this->postJson($this->apiEndpoint, []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['code', 'cart_total']);
    }

    /**
     * @test
     * Verifies that invalid cart_total (negative) returns 422.
     */
    public function it_returns_422_for_negative_cart_total(): void
    {
        $response = $this->postJson($this->apiEndpoint, [
            'code' => 'ANYCODE',
            'cart_total' => -50.00,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['cart_total']);
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // BUSINESS RULE ERRORS (422 with message)
    // ═══════════════════════════════════════════════════════════════════════════════

    /**
     * @test
     * Verifies that invalid coupon code returns 422 with error message.
     */
    public function it_returns_422_for_invalid_coupon_code(): void
    {
        $response = $this->postJson($this->apiEndpoint, [
            'code' => 'NONEXISTENT',
            'cart_total' => 100.00,
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'valid' => false,
            'code' => 'NOT_FOUND',
        ]);
        $response->assertJsonStructure(['message']);
    }

    /**
     * @test
     * Verifies that expired coupon returns 422 with EXPIRED code.
     */
    public function it_returns_422_for_expired_coupon(): void
    {
        Coupon::factory()->create([
            'tenant_id' => $this->tenant->id,
            'code' => 'EXPIREDAPI',
            'type' => Coupon::TYPE_PERCENT,
            'value' => 10.00,
            'is_active' => true,
            'expires_at' => now()->subDay(),
        ]);

        $response = $this->postJson($this->apiEndpoint, [
            'code' => 'EXPIREDAPI',
            'cart_total' => 100.00,
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'valid' => false,
            'code' => 'EXPIRED',
        ]);
    }

    /**
     * @test
     * Verifies that depleted coupon returns 422 with DEPLETED code.
     */
    public function it_returns_422_for_depleted_coupon(): void
    {
        Coupon::factory()->create([
            'tenant_id' => $this->tenant->id,
            'code' => 'DEPLETEDAPI',
            'type' => Coupon::TYPE_FIXED,
            'value' => 25.00,
            'is_active' => true,
            'usage_limit' => 10,
            'usage_count' => 10, // DEPLETED
        ]);

        $response = $this->postJson($this->apiEndpoint, [
            'code' => 'DEPLETEDAPI',
            'cart_total' => 100.00,
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'valid' => false,
            'code' => 'DEPLETED',
        ]);
    }

    /**
     * @test
     * Verifies that inactive coupon returns 422 with INACTIVE code.
     */
    public function it_returns_422_for_inactive_coupon(): void
    {
        Coupon::factory()->create([
            'tenant_id' => $this->tenant->id,
            'code' => 'INACTIVEAPI',
            'type' => Coupon::TYPE_PERCENT,
            'value' => 5.00,
            'is_active' => false, // INACTIVE
        ]);

        $response = $this->postJson($this->apiEndpoint, [
            'code' => 'INACTIVEAPI',
            'cart_total' => 100.00,
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'valid' => false,
            'code' => 'INACTIVE',
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // AUTHENTICATED USER TESTS
    // ═══════════════════════════════════════════════════════════════════════════════

    /**
     * @test
     * Verifies authenticated users can validate coupons.
     */
    public function it_allows_authenticated_users_to_validate_coupons(): void
    {
        $user = User::factory()->create();

        Coupon::factory()->create([
            'tenant_id' => $this->tenant->id,
            'code' => 'AUTHUSER',
            'type' => Coupon::TYPE_PERCENT,
            'value' => 25.00,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson($this->apiEndpoint, [
                'code' => 'AUTHUSER',
                'cart_total' => 200.00,
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'valid' => true,
            'discount_amount' => 50,
            'new_total' => 150,
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // CART SUMMARY ENDPOINT
    // ═══════════════════════════════════════════════════════════════════════════════

    /**
     * @test
     * Verifies cart summary endpoint returns correct structure.
     */
    public function it_returns_cart_summary_with_applied_coupon(): void
    {
        Coupon::factory()->create([
            'tenant_id' => $this->tenant->id,
            'code' => 'SUMMARY10',
            'type' => Coupon::TYPE_PERCENT,
            'value' => 10.00,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/checkout/cart-summary', [
            'subtotal' => 100.00,
            'shipping_cost' => 20.00,
            'coupon_code' => 'SUMMARY10',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'subtotal',
            'shipping_cost',
            'discount_amount',
            'discount_type',
            'applied_coupon',
            'total',
        ]);

        $json = $response->json();
        $this->assertEquals(10.00, $json['discount_amount']);
        $this->assertEquals(110.00, $json['total']); // 100 + 20 - 10
        $this->assertSame('SUMMARY10', $json['applied_coupon']);
    }

    /**
     * @test
     * Verifies remove coupon endpoint works.
     */
    public function it_removes_coupon_and_returns_original_total(): void
    {
        $response = $this->postJson('/api/v1/checkout/remove-coupon', [
            'subtotal' => 100.00,
            'shipping_cost' => 25.00,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'discount_amount' => 0.0,
            'applied_coupon' => null,
            'total' => 125.00,
        ]);
    }
}
