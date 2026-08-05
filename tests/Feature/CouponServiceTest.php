<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Shared\Services\TenantContext;
use App\Exceptions\InvalidCouponException;
use App\Models\City;
use App\Models\Coupon;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CouponValidatorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * CouponServiceTest - Integration Tests for CouponValidatorService
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  SERVICE LAYER TESTING: Verifies business rules and guest logic        ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * These tests use the database to verify service-level business rules,
 * specifically the Guest vs Authenticated user handling.
 *
 * @covers \App\Services\CouponValidatorService
 */
class CouponServiceTest extends TestCase
{
    use RefreshDatabase;

    private CouponValidatorService $service;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(CouponValidatorService::class);

        // Create a tenant for all tests
        $this->tenant = Tenant::factory()->create([
            'name' => 'Test Business',
            'slug' => 'test-business',
            'city_id' => City::factory()->create()->id,
        ]);

        // Bind tenant to container (simulates TenantMiddleware)
        app()->instance('currentTenant', $this->tenant);
        app(TenantContext::class)->setTenant((string) $this->tenant->id);
    }

    protected function tearDown(): void
    {
        // Reset schema cache after tests
        CouponValidatorService::resetSchemaCache();
        app(TenantContext::class)->reset();
        parent::tearDown();
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // GUEST ACCESS VALIDATION
    // ═══════════════════════════════════════════════════════════════════════════════

    /**
     * @test
     * Verifies that guests can use coupons without per-user restrictions.
     *
     * SCENARIO: Anonymous user validates an unrestricted coupon
     * EXPECTED: Validation succeeds, returns Coupon model
     */
    #[Test]
    public function it_allows_guests_to_use_unrestricted_coupons(): void
    {
        // Arrange: Create a coupon with no per-user limits
        $coupon = Coupon::factory()->create([
            'tenant_id' => $this->tenant->id,
            'code' => 'GUESTFRIENDLY',
            'type' => Coupon::TYPE_PERCENT,
            'value' => 10.00,
            'is_active' => true,
            'starts_at' => null,
            'expires_at' => null,
            'usage_limit' => null, // No global limit
            'usage_count' => 0,
            'min_spend' => null,
        ]);

        // Act: Validate as guest (user = null)
        $result = $this->service->validate(
            code: 'GUESTFRIENDLY',
            user: null, // GUEST
            cartTotal: 100.00,
            tenant: $this->tenant
        );

        // Assert: Validation succeeded
        $this->assertInstanceOf(Coupon::class, $result);
        $this->assertSame($coupon->id, $result->id);
        $this->assertSame('GUESTFRIENDLY', $result->code);
    }

    /**
     * @test
     * Verifies guests can validate coupons with global limits (but not per-user).
     */
    #[Test]
    public function it_allows_guests_when_global_limit_not_reached(): void
    {
        $coupon = Coupon::factory()->create([
            'tenant_id' => $this->tenant->id,
            'code' => 'GLOBALLIMITED',
            'type' => Coupon::TYPE_FIXED,
            'value' => 50.00,
            'is_active' => true,
            'usage_limit' => 100,
            'usage_count' => 50, // 50 of 100 used
        ]);

        $result = $this->service->validate(
            code: 'GLOBALLIMITED',
            user: null,
            cartTotal: 200.00,
            tenant: $this->tenant
        );

        $this->assertInstanceOf(Coupon::class, $result);
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // THE "LOGIN WALL" SECURITY CHECK
    // ═══════════════════════════════════════════════════════════════════════════════

    /**
     * @test
     * CRITICAL: Verifies that guests cannot use per-user-limited coupons.
     *
     * SCENARIO: Anonymous user tries to validate a coupon with max_uses_per_user
     * EXPECTED: InvalidCouponException with code REQUIRE_LOGIN
     *
     * This test ensures guests cannot bypass per-user tracking by staying anonymous.
     */
    #[Test]
    public function it_throws_exception_for_guests_using_restricted_coupons(): void
    {
        // Skip if coupon_user table doesn't exist
        if (! Schema::hasTable('coupon_user')) {
            $this->markTestSkipped('coupon_user table does not exist');
        }

        // Arrange: Create a coupon WITH per-user limit
        Coupon::factory()->perUserLimited(1)->create([
            'tenant_id' => $this->tenant->id,
            'code' => 'PERUSERLIMITED',
            'type' => Coupon::TYPE_PERCENT,
            'value' => 20.00,
            'is_active' => true,
        ]);

        // Act & Assert: Guest validation must throw REQUIRE_LOGIN
        $this->expectException(InvalidCouponException::class);

        try {
            $this->service->validate(
                code: 'PERUSERLIMITED',
                user: null, // GUEST
                cartTotal: 100.00,
                tenant: $this->tenant
            );
        } catch (InvalidCouponException $e) {
            // Verify the exact error code
            $this->assertSame('REQUIRE_LOGIN', $e->getErrorCode());
            $this->assertStringContainsString('iniciar sesión', $e->getMessage());
            throw $e;
        }
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // CONCURRENCY SIMULATION - GLOBAL LIMIT DEPLETION
    // ═══════════════════════════════════════════════════════════════════════════════

    /**
     * @test
     * Verifies that validation fails when global usage limit is reached.
     *
     * SCENARIO: Coupon with usage_limit = 5, usage_count = 5
     * EXPECTED: InvalidCouponException with code DEPLETED
     */
    #[Test]
    public function it_prevents_usage_when_global_limit_reached(): void
    {
        // Arrange: Create a fully depleted coupon
        Coupon::factory()->create([
            'tenant_id' => $this->tenant->id,
            'code' => 'DEPLETED100',
            'type' => Coupon::TYPE_PERCENT,
            'value' => 10.00,
            'is_active' => true,
            'usage_limit' => 5,
            'usage_count' => 5, // LIMIT REACHED
        ]);

        // Act & Assert
        $this->expectException(InvalidCouponException::class);

        try {
            $this->service->validate(
                code: 'DEPLETED100',
                user: null,
                cartTotal: 100.00,
                tenant: $this->tenant
            );
        } catch (InvalidCouponException $e) {
            $this->assertSame('DEPLETED', $e->getErrorCode());
            throw $e;
        }
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // AUTHENTICATED USER TESTS
    // ═══════════════════════════════════════════════════════════════════════════════

    /**
     * @test
     * Verifies authenticated users can validate unrestricted coupons.
     */
    #[Test]
    public function it_allows_authenticated_users_to_use_coupons(): void
    {
        $user = User::factory()->create();

        $coupon = Coupon::factory()->create([
            'tenant_id' => $this->tenant->id,
            'code' => 'AUTHONLY',
            'type' => Coupon::TYPE_PERCENT,
            'value' => 15.00,
            'is_active' => true,
        ]);

        $result = $this->service->validate(
            code: 'AUTHONLY',
            user: $user,
            cartTotal: 200.00,
            tenant: $this->tenant
        );

        $this->assertInstanceOf(Coupon::class, $result);
        $this->assertSame($coupon->id, $result->id);
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // MINIMUM SPEND VALIDATION
    // ═══════════════════════════════════════════════════════════════════════════════

    /**
     * @test
     * Verifies that coupons with minimum spend requirement are enforced.
     */
    #[Test]
    public function it_rejects_coupon_when_minimum_spend_not_met(): void
    {
        Coupon::factory()->create([
            'tenant_id' => $this->tenant->id,
            'code' => 'MINSPEND100',
            'type' => Coupon::TYPE_FIXED,
            'value' => 25.00,
            'is_active' => true,
            'min_spend' => 100.00, // Minimum L.100 required
        ]);

        $this->expectException(InvalidCouponException::class);

        try {
            $this->service->validate(
                code: 'MINSPEND100',
                user: null,
                cartTotal: 50.00, // Below minimum
                tenant: $this->tenant
            );
        } catch (InvalidCouponException $e) {
            $this->assertSame('MIN_SPEND_NOT_MET', $e->getErrorCode());
            $this->assertArrayHasKey('remaining', $e->context());
            throw $e;
        }
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // DATE RANGE VALIDATION
    // ═══════════════════════════════════════════════════════════════════════════════

    /**
     * @test
     * Verifies expired coupons are rejected.
     */
    #[Test]
    public function it_rejects_expired_coupons(): void
    {
        Coupon::factory()->create([
            'tenant_id' => $this->tenant->id,
            'code' => 'EXPIRED2024',
            'type' => Coupon::TYPE_PERCENT,
            'value' => 10.00,
            'is_active' => true,
            'expires_at' => now()->subDay(),
        ]);

        $this->expectException(InvalidCouponException::class);

        try {
            $this->service->validate(
                code: 'EXPIRED2024',
                user: null,
                cartTotal: 100.00,
                tenant: $this->tenant
            );
        } catch (InvalidCouponException $e) {
            $this->assertSame('EXPIRED', $e->getErrorCode());
            throw $e;
        }
    }

    /**
     * @test
     * Verifies future coupons (not yet started) are rejected.
     */
    #[Test]
    public function it_rejects_future_coupons(): void
    {
        Coupon::factory()->create([
            'tenant_id' => $this->tenant->id,
            'code' => 'FUTURE2025',
            'type' => Coupon::TYPE_PERCENT,
            'value' => 20.00,
            'is_active' => true,
            'starts_at' => now()->addWeek(),
        ]);

        $this->expectException(InvalidCouponException::class);

        try {
            $this->service->validate(
                code: 'FUTURE2025',
                user: null,
                cartTotal: 100.00,
                tenant: $this->tenant
            );
        } catch (InvalidCouponException $e) {
            $this->assertSame('NOT_STARTED', $e->getErrorCode());
            throw $e;
        }
    }

    /**
     * @test
     * Verifies inactive coupons are rejected.
     */
    #[Test]
    public function it_rejects_inactive_coupons(): void
    {
        Coupon::factory()->create([
            'tenant_id' => $this->tenant->id,
            'code' => 'INACTIVE123',
            'type' => Coupon::TYPE_FIXED,
            'value' => 50.00,
            'is_active' => false, // INACTIVE
        ]);

        $this->expectException(InvalidCouponException::class);

        try {
            $this->service->validate(
                code: 'INACTIVE123',
                user: null,
                cartTotal: 100.00,
                tenant: $this->tenant
            );
        } catch (InvalidCouponException $e) {
            $this->assertSame('INACTIVE', $e->getErrorCode());
            throw $e;
        }
    }

    /**
     * @test
     * Verifies non-existent coupons are rejected.
     */
    #[Test]
    public function it_rejects_nonexistent_coupons(): void
    {
        $this->expectException(InvalidCouponException::class);

        try {
            $this->service->validate(
                code: 'DOESNOTEXIST',
                user: null,
                cartTotal: 100.00,
                tenant: $this->tenant
            );
        } catch (InvalidCouponException $e) {
            $this->assertSame('NOT_FOUND', $e->getErrorCode());
            throw $e;
        }
    }
}
