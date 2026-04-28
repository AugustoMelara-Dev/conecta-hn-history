<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use App\Domain\Access\Enums\Role;
use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class TenantStatusMiddlewareTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', 'tenant.status'])
            ->get('/__tenant-status-check/{tenant:slug}', static fn (Tenant $tenant): string => 'ok');
    }

    public function test_guest_cannot_access_provisional_tenant_route(): void
    {
        $tenant = Tenant::factory()->create([
            'verification_status' => Tenant::VERIFICATION_STATUS_PROVISIONAL,
            'verified_at' => null,
            'publication_paused_at' => null,
            'is_active' => true,
            'subscription_status' => 'active',
        ]);

        $this->get("/__tenant-status-check/{$tenant->slug}")
            ->assertNotFound();
    }

    public function test_owner_can_preview_provisional_tenant_route(): void
    {
        $owner = User::factory()->create([
            'role' => UserRole::TENANT_OWNER->value,
        ]);

        $tenant = Tenant::factory()->forUser($owner)->create([
            'verification_status' => Tenant::VERIFICATION_STATUS_PROVISIONAL,
            'verified_at' => null,
            'publication_paused_at' => null,
            'is_active' => true,
            'subscription_status' => 'active',
        ]);

        $this->actingAs($owner)
            ->get("/__tenant-status-check/{$tenant->slug}")
            ->assertOk()
            ->assertSee('ok');
    }

    public function test_staff_membership_can_preview_tenant_when_publication_is_paused(): void
    {
        $owner = User::factory()->create([
            'role' => UserRole::TENANT_OWNER->value,
        ]);

        $tenant = Tenant::factory()->forUser($owner)->create([
            'verification_status' => Tenant::VERIFICATION_STATUS_VERIFIED,
            'verified_at' => now(),
            'publication_paused_at' => now(),
            'is_active' => true,
            'subscription_status' => 'active',
        ]);

        $staff = User::factory()->create([
            'role' => UserRole::STAFF->value,
        ]);

        TenantMembership::query()->create([
            'tenant_id' => (string) $tenant->getKey(),
            'user_id' => (string) $staff->getKey(),
            'role' => Role::MANAGER->value,
            'accepted_at' => now(),
        ]);

        $this->actingAs($staff)
            ->get("/__tenant-status-check/{$tenant->slug}")
            ->assertOk()
            ->assertSee('ok');
    }
}
