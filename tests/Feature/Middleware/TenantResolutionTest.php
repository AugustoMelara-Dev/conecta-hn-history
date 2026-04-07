<?php

declare(strict_types=1);

namespace Tests\Feature\Middleware;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use Tests\Traits\InteractsWithInertiaRoutes;

/**
 * TenantResolutionTest - ADR-004 Compliance (Single Source of Truth)
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  DRY: Uses InteractsWithInertiaRoutes trait for route registration      ║
 * ║  TESTS: Request-based resolution (route parameter, subdomain, header)  ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * NOTE: User-tenant validation tests are simplified since User model
 * doesn't have tenant_id column. EnsureUserBelongsToTenant middleware
 * handles this when the relationship exists.
 */
class TenantResolutionTest extends TestCase
{
    use DatabaseTransactions;
    use InteractsWithInertiaRoutes;

    protected function setUp(): void
    {
        parent::setUp();

        // DRY: Use trait-encapsulated route registration
        $this->registerTenantResolutionRoutes();
    }

    protected function tearDown(): void
    {
        // Cleanup: Prevent route pollution between tests
        $this->cleanupInertiaTestRoutes();
        parent::tearDown();
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // CASE 1: Route parameter resolution
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Tenant is resolved from route parameter.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function tenant_is_resolved_from_route_parameter(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'test-shop']);

        $response = $this->get('/__test/tenant/test-shop');

        $response->assertOk();
        $response->assertJson(['tenant_id' => $tenant->id]);
    }

    /**
     * Invalid slug returns null tenant (no error).
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function invalid_slug_returns_null_tenant(): void
    {
        $response = $this->get('/__test/tenant/nonexistent');

        $response->assertOk();
        $response->assertJson(['tenant_id' => null]);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // CASE 2: Header-based resolution (API clients)
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Tenant is resolved from X-Tenant-ID header.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function tenant_is_resolved_from_header(): void
    {
        $tenant = Tenant::factory()->create();

        $response = $this->withHeaders([
            'X-Tenant-ID' => (string) $tenant->id,
        ])->get('/__test/api-tenant');

        $response->assertOk();
        $response->assertJson(['tenant_id' => $tenant->id]);
    }

    /**
     * Invalid header ID returns null tenant.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function invalid_header_id_returns_null(): void
    {
        $response = $this->withHeaders([
            'X-Tenant-ID' => '99999',
        ])->get('/__test/api-tenant');

        $response->assertOk();
        $response->assertJson(['tenant_id' => null]);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // CASE 3: Resolution source priority
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Route parameter takes priority over header.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function route_parameter_takes_priority_over_header(): void
    {
        $routeTenant = Tenant::factory()->create(['slug' => 'route-tenant']);
        $headerTenant = Tenant::factory()->create();

        $response = $this->withHeaders([
            'X-Tenant-ID' => (string) $headerTenant->id,
        ])->get('/__test/tenant/route-tenant');

        $response->assertOk();
        // Route parameter wins
        $response->assertJson(['tenant_id' => $routeTenant->id]);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // CASE 4: Guest access (ADR-004)
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Guest users can access tenant context (guest checkout).
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function guest_users_can_access_tenant(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'public-shop']);

        $response = $this->get('/__test/tenant/public-shop');

        $response->assertOk();
        $response->assertJson(['tenant_id' => $tenant->id]);
    }

    /**
     * Authenticated users can access tenant (normal flow).
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function authenticated_users_can_access_tenant(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'my-shop']);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/__test/tenant/my-shop');

        $response->assertOk();
        $response->assertJson(['tenant_id' => $tenant->id]);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // CASE 5: VC-AUDIT-108 - Header Authorization Security
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * VC-AUDIT-108: Authenticated user cannot access unauthorized tenant via header.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function authenticated_user_cannot_access_unauthorized_tenant_via_header(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(); // User NOT attached to this tenant

        $response = $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => (string) $tenant->id])
            ->get('/__test/api-tenant');

        $response->assertStatus(403);
    }

    /**
     * VC-AUDIT-108: Guest users are not blocked by header authorization (handled by auth middleware).
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function guest_users_can_use_header_resolution(): void
    {
        $tenant = Tenant::factory()->create();

        $response = $this->withHeaders(['X-Tenant-ID' => (string) $tenant->id])
            ->get('/__test/api-tenant');

        $response->assertOk();
        $response->assertJson(['tenant_id' => $tenant->id]);
    }
}
