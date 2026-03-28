<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Domain\Identity\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

final class TenantPanelAccessTest extends TestCase
{
    use DatabaseTransactions;

    public function test_verified_tenant_owner_can_open_the_filament_app_dashboard(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::TENANT_OWNER,
        ]);
        $tenant = Tenant::factory()->create([
            'user_id' => $user->id,
            'slug' => 'cafe-norte-oficial',
        ]);
        $user->forceFill(['tenant_id' => $tenant->id])->save();

        $response = $this
            ->actingAs($user)
            ->get(route('filament.app.pages.dashboard', ['tenant' => $tenant->slug], false));

        $response->assertOk();
    }

    public function test_legacy_profile_page_redirects_tenant_owners_to_the_filament_profile(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::TENANT_OWNER,
        ]);
        $tenant = Tenant::factory()->create([
            'user_id' => $user->id,
            'slug' => 'cafe-norte-oficial',
        ]);
        $user->forceFill(['tenant_id' => $tenant->id])->save();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertRedirect(route('filament.app.auth.profile', [], false));
    }

    public function test_verified_tenant_owner_can_open_the_settings_page(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::TENANT_OWNER,
        ]);
        $tenant = Tenant::factory()->create([
            'user_id' => $user->id,
            'slug' => 'cafe-norte-oficial',
        ]);
        $user->forceFill(['tenant_id' => $tenant->id])->save();

        $response = $this
            ->actingAs($user)
            ->get(route('filament.app.pages.settings', ['tenant' => $tenant->slug], false));

        $response->assertOk();
    }

    public function test_verified_tenant_owner_can_open_the_official_business_profile_editor(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::TENANT_OWNER,
        ]);
        $tenant = Tenant::factory()->create([
            'user_id' => $user->id,
            'slug' => 'cafe-norte-oficial',
        ]);
        $user->forceFill(['tenant_id' => $tenant->id])->save();

        $response = $this
            ->actingAs($user)
            ->get(route('filament.app.tenant.profile', ['tenant' => $tenant->slug], false));

        $response->assertOk();
    }

    public function test_legacy_workspace_entries_redirect_tenant_owners_to_the_app_panel(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::TENANT_OWNER,
        ]);
        $tenant = Tenant::factory()->create([
            'user_id' => $user->id,
            'slug' => 'cafe-norte-oficial',
        ]);
        $user->forceFill(['tenant_id' => $tenant->id])->save();

        $this->actingAs($user)
            ->get(route('tenant.dashboard', [], false))
            ->assertRedirect(route('filament.app.pages.dashboard', ['tenant' => $tenant->slug], false));

        $this->actingAs($user)
            ->get(route('tenant.products.index', [], false))
            ->assertRedirect(route('filament.app.resources.products.index', ['tenant' => $tenant->slug], false));

        $this->actingAs($user)
            ->get(route('tenant.orders.index', [], false))
            ->assertRedirect(route('filament.app.resources.orders.index', ['tenant' => $tenant->slug], false));

        $this->actingAs($user)
            ->get(route('tenant.marketing.index', [], false))
            ->assertRedirect(route('filament.app.pages.manage-marketing', ['tenant' => $tenant->slug], false));

        $this->actingAs($user)
            ->get(route('tenant.reports.index', [], false))
            ->assertRedirect(route('filament.app.pages.growth-center', ['tenant' => $tenant->slug], false));

        $this->actingAs($user)
            ->get(route('tenant.billing.index', [], false))
            ->assertRedirect(route('filament.app.pages.manage-billing', ['tenant' => $tenant->slug], false));

        $this->actingAs($user)
            ->get(route('tenant.settings.index', [], false))
            ->assertRedirect(route('filament.app.pages.settings', ['tenant' => $tenant->slug], false));

        $this->actingAs($user)
            ->get(route('tenant.upgrade', [], false))
            ->assertRedirect(route('filament.app.pages.upgrade-to-pro', ['tenant' => $tenant->slug], false));
    }

    public function test_legacy_landing_page_builder_redirects_to_the_official_profile_editor(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::TENANT_OWNER,
        ]);
        $tenant = Tenant::factory()->create([
            'user_id' => $user->id,
            'slug' => 'cafe-norte-oficial',
        ]);
        $user->forceFill(['tenant_id' => $tenant->id])->save();

        $this->actingAs($user)
            ->get(route('tenant.landing-page.index', [], false))
            ->assertRedirect(route('filament.app.tenant.profile', ['tenant' => $tenant->slug], false));

        $this->actingAs($user)
            ->put(route('tenant.landing-page.update', [], false), ['sections' => []])
            ->assertRedirect(route('filament.app.tenant.profile', ['tenant' => $tenant->slug], false))
            ->assertSessionHas('error', 'La presencia publica ya se administra desde el editor oficial del micrositio en /app.');
    }
}
