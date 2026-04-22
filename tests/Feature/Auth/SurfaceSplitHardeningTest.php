<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Domain\Identity\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

final class SurfaceSplitHardeningTest extends TestCase
{
    use DatabaseTransactions;

    public function test_owner_is_redirected_from_root_to_app_surface(): void
    {
        $this->configureSurfaceUrls();

        [$owner, $tenant] = $this->createOwnerWithTenant('owner-root-split');

        $response = $this
            ->actingAs($owner)
            ->get('/');

        $response->assertRedirect(
            'https://app.vitobusinessos.com'.route('filament.app.pages.dashboard', ['tenant' => $tenant->slug], false)
        );
    }

    public function test_owner_is_redirected_from_explore_to_app_surface(): void
    {
        $this->configureSurfaceUrls();

        [$owner, $tenant] = $this->createOwnerWithTenant('owner-explore-split');

        $response = $this
            ->actingAs($owner)
            ->get('/explore');

        $response->assertRedirect(
            'https://app.vitobusinessos.com'.route('filament.app.pages.dashboard', ['tenant' => $tenant->slug], false)
        );
    }

    public function test_super_admin_is_redirected_from_explore_to_admin_surface(): void
    {
        $this->configureSurfaceUrls();

        $superAdmin = User::factory()->superAdmin()->create();

        $response = $this
            ->actingAs($superAdmin)
            ->get('/explore');

        $response->assertRedirect(
            'https://admin.vitobusinessos.com'.route('filament.admin.pages.dashboard', [], false)
        );
    }

    public function test_customer_can_access_explore_surface(): void
    {
        $this->configureSurfaceUrls();

        $customer = User::factory()->create([
            'role' => UserRole::CUSTOMER,
            'tenant_id' => null,
        ]);

        $response = $this
            ->actingAs($customer)
            ->get('/explore');

        $response->assertStatus(200);
        $response->assertInertia(fn (AssertableInertia $page) => $page->component('Public/Explore'));
    }

    public function test_dashboard_legacy_route_redirects_customer_to_www_surface(): void
    {
        $this->configureSurfaceUrls();

        $customer = User::factory()->create([
            'role' => UserRole::CUSTOMER,
            'tenant_id' => null,
        ]);

        $response = $this
            ->actingAs($customer)
            ->get('/dashboard');

        $response->assertRedirect(
            'https://www.vitobusinessos.com'.route('directory.index', [], false)
        );
    }

    public function test_dashboard_legacy_route_redirects_owner_to_app_surface(): void
    {
        $this->configureSurfaceUrls();

        [$owner, $tenant] = $this->createOwnerWithTenant('owner-dashboard-split');

        $response = $this
            ->actingAs($owner)
            ->get('/dashboard');

        $response->assertRedirect(
            'https://app.vitobusinessos.com'.route('filament.app.pages.dashboard', ['tenant' => $tenant->slug], false)
        );
    }

    public function test_workspace_legacy_route_redirects_customer_to_www_surface(): void
    {
        $this->configureSurfaceUrls();

        $customer = User::factory()->create([
            'role' => UserRole::CUSTOMER,
            'tenant_id' => null,
        ]);

        $response = $this
            ->actingAs($customer)
            ->get('/workspace/dashboard');

        $response->assertRedirect(
            'https://www.vitobusinessos.com'.route('directory.index', [], false)
        );
    }

    public function test_authenticated_user_hitting_login_is_redirected_to_assigned_surface(): void
    {
        $this->configureSurfaceUrls();

        [$owner, $tenant] = $this->createOwnerWithTenant('owner-login-split');

        $response = $this
            ->actingAs($owner)
            ->get('/login');

        $response->assertRedirect(
            'https://app.vitobusinessos.com'.route('filament.app.pages.dashboard', ['tenant' => $tenant->slug], false)
        );
    }

    private function configureSurfaceUrls(): void
    {
        config([
            'app.platform_directory_url' => 'https://www.vitobusinessos.com',
            'app.platform_app_url' => 'https://app.vitobusinessos.com',
            'app.platform_admin_url' => 'https://admin.vitobusinessos.com',
        ]);
    }

    /**
     * @return array{0: User, 1: Tenant}
     */
    private function createOwnerWithTenant(string $slug): array
    {
        $owner = User::factory()->create([
            'role' => UserRole::TENANT_OWNER,
        ]);

        $tenant = Tenant::factory()->forUser($owner)->create([
            'slug' => $slug,
        ]);

        $owner->forceFill(['tenant_id' => $tenant->id])->save();

        return [$owner, $tenant];
    }
}
