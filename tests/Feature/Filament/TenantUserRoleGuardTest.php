<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Domain\Identity\Enums\UserRole;
use App\Filament\App\Resources\TenantUserResource;
use App\Models\Category;
use App\Models\City;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class TenantUserRoleGuardTest extends TestCase
{
    use DatabaseTransactions;

    public function test_admin_role_options_exclude_owner_and_admin(): void
    {
        [$tenant] = $this->makeTenantWithOwner();

        $admin = User::factory()->create([
            'role' => UserRole::STAFF,
        ]);

        TenantMembership::query()->create([
            'tenant_id' => (string) $tenant->getKey(),
            'user_id' => (string) $admin->getKey(),
            'role' => 'admin',
            'accepted_at' => now(),
        ]);

        $this->actingAs($admin);
        $this->setAppPanelTenantContext($tenant);

        $options = TenantUserResource::getRoleOptions();

        $this->assertArrayNotHasKey('owner', $options);
        $this->assertArrayNotHasKey('admin', $options);
        $this->assertArrayHasKey('manager', $options);
        $this->assertArrayHasKey('operator', $options);
        $this->assertArrayHasKey('viewer', $options);
    }

    public function test_admin_cannot_assign_admin_role_via_payload_tampering(): void
    {
        [$tenant] = $this->makeTenantWithOwner();

        $admin = User::factory()->create([
            'role' => UserRole::STAFF,
        ]);

        TenantMembership::query()->create([
            'tenant_id' => (string) $tenant->getKey(),
            'user_id' => (string) $admin->getKey(),
            'role' => 'admin',
            'accepted_at' => now(),
        ]);

        $this->actingAs($admin);
        $this->setAppPanelTenantContext($tenant);

        $this->expectException(ValidationException::class);

        TenantUserResource::normalizeMembershipData($tenant, [
            'role' => 'admin',
            'preset_key' => null,
        ]);
    }

    public function test_owner_can_assign_admin_role(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner();

        $this->actingAs($owner);
        $this->setAppPanelTenantContext($tenant);

        $normalized = TenantUserResource::normalizeMembershipData($tenant, [
            'role' => 'admin',
            'preset_key' => null,
        ]);

        $this->assertSame('admin', $normalized['role']);
    }

    protected function tearDown(): void
    {
        Filament::setTenant(null, isQuiet: true);
        Filament::setCurrentPanel(null);

        parent::tearDown();
    }

    /**
     * @return array{0: Tenant, 1: User}
     */
    private function makeTenantWithOwner(): array
    {
        $owner = User::factory()->create([
            'role' => UserRole::TENANT_OWNER,
        ]);

        $city = City::factory()->create();
        $category = Category::factory()->create([
            'name' => 'Servicios',
            'slug' => 'servicios',
        ]);

        $tenant = Tenant::factory()->state([
            'user_id' => $owner->id,
            'slug' => 'tenant-role-guard-test',
            'business_type' => 'service',
            'city_id' => $city->getKey(),
            'category_id' => $category->getKey(),
        ])->pro()->create();

        $owner->forceFill(['tenant_id' => $tenant->id])->save();

        return [$tenant, $owner];
    }

    private function setAppPanelTenantContext(Tenant $tenant): void
    {
        Filament::setCurrentPanel('app');
        Filament::setTenant($tenant, isQuiet: true);
        $this->actingAsTenant((string) $tenant->getKey());
    }
}

