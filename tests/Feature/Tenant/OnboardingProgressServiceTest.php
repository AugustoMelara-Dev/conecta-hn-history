<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant;

use App\Domain\TenantGovernance\Services\TenantOnboardingProgressService;
use App\Enums\UserRole;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class OnboardingProgressServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_persists_onboarding_progress_snapshot_for_tenant_owner(): void
    {
        $owner = User::factory()->create([
            'role' => UserRole::TENANT_OWNER->value,
        ]);

        $tenant = Tenant::factory()->forUser($owner)->create([
            'description' => 'Clinica con servicios premium.',
            'address' => 'Colonia Palmira, Tegucigalpa',
            'whatsapp_number' => '+50499998888',
            'is_active' => true,
        ]);

        Product::factory()->forTenant($tenant)->create();

        $this->actingAsTenant((string) $tenant->id);
        $snapshot = app(TenantOnboardingProgressService::class)->persist($tenant, $owner);

        self::assertGreaterThanOrEqual(60, (int) $snapshot['progress']);
        self::assertArrayHasKey('key', $snapshot['nextAction']);
        self::assertArrayHasKey('title', $snapshot['nextAction']);
        self::assertArrayHasKey('copy', $snapshot['nextAction']);
        self::assertArrayHasKey('href', $snapshot['nextAction']);
        self::assertCount(5, $snapshot['milestones']);

        $this->assertDatabaseHas('tenant_onboarding_states', [
            'tenant_id' => (string) $tenant->id,
            'user_id' => (string) $owner->id,
            'role' => UserRole::TENANT_OWNER->value,
        ]);
    }

    public function test_persists_onboarding_progress_snapshot_for_staff_role(): void
    {
        $staff = User::factory()->create([
            'role' => UserRole::STAFF->value,
        ]);

        $tenant = Tenant::factory()->create([
            'description' => 'Centro de servicios',
            'address' => 'Tegucigalpa',
            'phone' => '+50422112211',
            'business_type' => 'service',
        ]);

        $this->actingAsTenant((string) $tenant->id);
        $snapshot = app(TenantOnboardingProgressService::class)->persist($tenant, $staff);

        self::assertSame(UserRole::STAFF->value, $snapshot['role']);
        self::assertCount(5, $snapshot['milestones']);
        self::assertArrayHasKey('key', $snapshot['nextAction']);
        self::assertArrayHasKey('title', $snapshot['nextAction']);
        self::assertArrayHasKey('copy', $snapshot['nextAction']);
        self::assertArrayHasKey('href', $snapshot['nextAction']);

        $this->assertDatabaseHas('tenant_onboarding_states', [
            'tenant_id' => (string) $tenant->id,
            'user_id' => (string) $staff->id,
            'role' => UserRole::STAFF->value,
        ]);
    }

    public function test_persists_onboarding_progress_snapshot_for_customer_role(): void
    {
        $customer = User::factory()->create([
            'role' => UserRole::CUSTOMER->value,
        ]);

        $tenant = Tenant::factory()->create([
            'description' => 'Spa integral',
            'address' => 'San Pedro Sula',
            'is_active' => true,
            'business_type' => 'service',
        ]);

        $this->actingAsTenant((string) $tenant->id);
        $snapshot = app(TenantOnboardingProgressService::class)->persist($tenant, $customer);

        self::assertSame(UserRole::CUSTOMER->value, $snapshot['role']);
        self::assertCount(5, $snapshot['milestones']);
        self::assertArrayHasKey('key', $snapshot['nextAction']);
        self::assertArrayHasKey('title', $snapshot['nextAction']);
        self::assertArrayHasKey('copy', $snapshot['nextAction']);
        self::assertArrayHasKey('href', $snapshot['nextAction']);

        $this->assertDatabaseHas('tenant_onboarding_states', [
            'tenant_id' => (string) $tenant->id,
            'user_id' => (string) $customer->id,
            'role' => UserRole::CUSTOMER->value,
        ]);
    }

    public function test_owner_context_prioritizes_catalog_next_action_when_incomplete(): void
    {
        $owner = User::factory()->create([
            'role' => UserRole::TENANT_OWNER->value,
        ]);

        $tenant = Tenant::factory()->forUser($owner)->create([
            'description' => 'Negocio activo',
            'address' => 'Tegucigalpa',
            'whatsapp_number' => '+50499887766',
            'is_active' => true,
            'business_type' => 'service',
        ]);

        $this->actingAsTenant((string) $tenant->id);
        $snapshot = app(TenantOnboardingProgressService::class)->buildForTenant($tenant, $owner, 'catalog');

        self::assertSame('owner_catalog', (string) $snapshot['nextAction']['key']);
    }

    public function test_staff_context_prioritizes_agenda_next_action_when_incomplete(): void
    {
        $staff = User::factory()->create([
            'role' => UserRole::STAFF->value,
        ]);

        $tenant = Tenant::factory()->create([
            'description' => 'Clinica',
            'address' => 'San Pedro Sula',
            'business_type' => 'service',
        ]);

        $this->actingAsTenant((string) $tenant->id);
        $snapshot = app(TenantOnboardingProgressService::class)->buildForTenant($tenant, $staff, 'agenda');

        self::assertSame('staff_schedule', (string) $snapshot['nextAction']['key']);
    }

    public function test_customer_context_prioritizes_team_next_action_when_incomplete(): void
    {
        $customer = User::factory()->create([
            'role' => UserRole::CUSTOMER->value,
        ]);

        $tenant = Tenant::factory()->create([
            'business_type' => 'service',
            'is_active' => true,
        ]);

        $this->actingAsTenant((string) $tenant->id);
        $snapshot = app(TenantOnboardingProgressService::class)->buildForTenant($tenant, $customer, 'team');

        self::assertSame('customer_follow', (string) $snapshot['nextAction']['key']);
    }
}
