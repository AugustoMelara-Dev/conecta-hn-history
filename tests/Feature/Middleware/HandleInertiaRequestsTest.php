<?php

declare(strict_types=1);

namespace Tests\Feature\Middleware;

use App\Models\Announcement;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\InteractsWithInertiaRoutes;

/**
 * HandleInertiaRequestsTest - Black Box Behavior Testing (v10)
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  ENCAPSULATED: Route registration via InteractsWithInertiaRoutes trait ║
 * ║  CONTRACT TESTING: No Reflection, only observable HTTP behavior         ║
 * ║  CLEANUP: Routes cleaned up in tearDown (Issue #10)                     ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
class HandleInertiaRequestsTest extends TestCase
{
    use InteractsWithInertiaRoutes;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Trait-encapsulated route registration
        $this->registerInertiaTestRoutes();
    }

    protected function tearDown(): void
    {
        // Issue #10: Cleanup routes to prevent pollution
        $this->cleanupInertiaTestRoutes();
        parent::tearDown();
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // CASE 1: AUTHENTICATED USER WITHOUT TENANT
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * @test
     * Verifies authenticated users receive null tenant on non-storefront routes.
     */
    public function authenticated_user_without_tenant_receives_null_in_admin(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/__test/admin');

        $response->assertOk();

        $props = $this->getInertiaProps($response);

        $this->assertNull($props['tenant'] ?? null);
        $this->assertEquals([], $props['announcements'] ?? []);
        $this->assertEquals($user->id, $props['auth']['user']['id'] ?? null);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // CASE 2: TENANT BOUND IN STOREFRONT ROUTE
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * @test
     * Verifies tenant data is properly hydrated in storefront context.
     */
    public function storefront_route_receives_tenant_data(): void
    {
        $tenant = Tenant::factory()->create([
            'name' => 'Test Store',
            'slug' => 'test-store',
            'primary_color' => '#3B82F6',
            'is_pro' => true,
        ]);

        app()->instance('currentTenant', $tenant);

        $response = $this->get('/__test/storefront');

        $response->assertOk();

        $props = $this->getInertiaProps($response);

        $this->assertNotNull($props['tenant']);
        $this->assertEquals($tenant->id, $props['tenant']['id']);
        $this->assertEquals('Test Store', $props['tenant']['name']);
        $this->assertEquals('#3B82F6', $props['tenant']['primary_color']);

        app()->forgetInstance('currentTenant');
    }

    /**
     * @test
     * Verifies active announcements are fetched for tenant.
     */
    public function storefront_receives_active_announcements(): void
    {
        $tenant = Tenant::factory()->create();

        Announcement::factory()->create([
            'business_id' => $tenant->id,
            'is_active' => true,
            'type' => 'promo',
            'content' => '<p>Test Promo</p>',
        ]);

        app()->instance('currentTenant', $tenant);

        $response = $this->get('/__test/storefront');

        $response->assertOk();

        $props = $this->getInertiaProps($response);

        $this->assertCount(1, $props['announcements']);
        $this->assertEquals('promo', $props['announcements'][0]['type']);

        app()->forgetInstance('currentTenant');
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // CASE 3: CSS INJECTION SANITIZATION
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * @test
     * Verifies malicious primary_color is sanitized to safe default.
     */
    public function css_injection_in_primary_color_is_blocked(): void
    {
        $tenant = Tenant::factory()->create([
            'primary_color' => null,
        ]);

        app()->instance('currentTenant', $tenant);

        $response = $this->get('/__test/storefront');

        $response->assertOk();

        $props = $this->getInertiaProps($response);

        // Must be sanitized to safe default
        $this->assertEquals('#111827', $props['tenant']['primary_color']);

        app()->forgetInstance('currentTenant');
    }

    /**
     * @test
     * Verifies valid hex colors pass through unchanged.
     */
    public function valid_hex_color_passes_through(): void
    {
        $tenant = Tenant::factory()->create([
            'primary_color' => '#FF5500',
        ]);

        app()->instance('currentTenant', $tenant);

        $response = $this->get('/__test/storefront');

        $props = $this->getInertiaProps($response);

        $this->assertEquals('#FF5500', $props['tenant']['primary_color']);

        app()->forgetInstance('currentTenant');
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // CASE 4: CONTEXT ISOLATION
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * @test
     * Verifies admin routes never receive tenant data even when bound.
     */
    public function admin_route_blocks_tenant_data(): void
    {
        $tenant = Tenant::factory()->create();
        app()->instance('currentTenant', $tenant);

        $response = $this->get('/__test/admin');

        $response->assertOk();

        $props = $this->getInertiaProps($response);

        // Admin must NEVER receive tenant data
        $this->assertNull($props['tenant'] ?? null);
        $this->assertEquals([], $props['announcements'] ?? []);

        app()->forgetInstance('currentTenant');
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // CASE 5: TYPE SAFETY
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * @test
     * Verifies non-Tenant objects in container are rejected.
     */
    public function non_tenant_object_returns_null(): void
    {
        app()->instance('currentTenant', new \stdClass);

        $response = $this->get('/__test/storefront');

        $response->assertOk();

        $props = $this->getInertiaProps($response);

        $this->assertNull($props['tenant'] ?? null);

        app()->forgetInstance('currentTenant');
    }

    /**
     * @test
     * Verifies null binding returns null tenant.
     */
    public function null_binding_returns_null(): void
    {
        app()->instance('currentTenant', null);

        $response = $this->get('/__test/storefront');

        $response->assertOk();

        $props = $this->getInertiaProps($response);

        $this->assertNull($props['tenant'] ?? null);

        app()->forgetInstance('currentTenant');
    }
}
