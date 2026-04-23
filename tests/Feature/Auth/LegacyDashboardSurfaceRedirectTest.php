<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Domain\Identity\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

final class LegacyDashboardSurfaceRedirectTest extends TestCase
{
    use DatabaseTransactions;

    public function test_dashboard_redirects_owner_to_business_surface(): void
    {
        $owner = User::factory()->create([
            'role' => UserRole::TENANT_OWNER,
        ]);
        $tenant = Tenant::factory()->forUser($owner)->create([
            'slug' => 'panel-owner',
        ]);
        $owner->forceFill(['tenant_id' => $tenant->getKey()])->save();

        $this->actingAs($owner)
            ->get('/dashboard')
            ->assertRedirect(route('filament.app.pages.dashboard', ['tenant' => 'panel-owner'], false));
    }

    public function test_dashboard_redirects_customer_to_directory_surface(): void
    {
        $customer = User::factory()->create([
            'role' => UserRole::CUSTOMER,
            'tenant_id' => null,
        ]);

        $this->actingAs($customer)
            ->get('/dashboard')
            ->assertRedirect(route('directory.index', [], false));
    }

    public function test_dashboard_redirects_super_admin_to_admin_surface(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN,
        ]);

        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertRedirect(route('filament.admin.pages.dashboard', [], false));
    }
}
