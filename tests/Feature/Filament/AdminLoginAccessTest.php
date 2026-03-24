<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Domain\Identity\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

final class AdminLoginAccessTest extends TestCase
{
    use DatabaseTransactions;

    public function test_business_user_can_open_admin_login_without_hitting_a_403_loop(): void
    {
        $seller = User::factory()->create([
            'role' => UserRole::TENANT_OWNER,
        ]);

        $response = $this
            ->actingAs($seller)
            ->get('/admin/login');

        $response
            ->assertOk()
            ->assertSee('Panel de superadministración')
            ->assertSee('/login')
            ->assertSee('Correo')
            ->assertSee('data-panel-theme-link="admin-panel"', false)
            ->assertSee('data-filament-critical-theme="true"', false)
            ->assertDontSee('__filament_runtime', false);

        $this->assertGuest();
    }

    public function test_superadmin_is_redirected_from_admin_login_to_admin_dashboard(): void
    {
        $superAdmin = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN,
            'email' => 'panel-superadmin@example.com',
        ]);

        config(['auth.super_admins' => [$superAdmin->email]]);

        $this
            ->actingAs($superAdmin)
            ->get('/admin/login')
            ->assertRedirect('/admin');
    }
}
