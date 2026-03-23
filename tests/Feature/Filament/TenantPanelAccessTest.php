<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Domain\Identity\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class TenantPanelAccessTest extends TestCase
{
    use RefreshDatabase;

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
}
