<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant;

use App\Domain\TenantGovernance\Services\TenantOnboardingProgressService;
use App\Enums\UserRole;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\TenantOnboardingState;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class OnboardingProgressServiceTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

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

    public function test_persist_keeps_last_completed_at_stable_without_new_completed_milestones(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-01 08:00:00'));

        $owner = User::factory()->create([
            'role' => UserRole::TENANT_OWNER->value,
        ]);

        $tenant = Tenant::factory()->forUser($owner)->create([
            'description' => 'Tienda local con productos para el hogar.',
            'address' => 'Tegucigalpa',
            'whatsapp_number' => '+50499990000',
            'is_active' => true,
        ]);

        $this->actingAsTenant((string) $tenant->id);
        $service = app(TenantOnboardingProgressService::class);
        $service->persist($tenant, $owner);

        $firstState = TenantOnboardingState::query()
            ->where('tenant_id', (string) $tenant->id)
            ->where('user_id', (string) $owner->id)
            ->where('role', UserRole::TENANT_OWNER->value)
            ->firstOrFail();
        $firstCompletedAt = $firstState->last_completed_at;

        Carbon::setTestNow(Carbon::parse('2026-05-02 09:30:00'));
        $service->persist($tenant->fresh(), $owner);

        $secondState = $firstState->fresh();

        self::assertInstanceOf(Carbon::class, $firstCompletedAt);
        self::assertInstanceOf(Carbon::class, $secondState->last_completed_at);
        self::assertTrue($firstCompletedAt->equalTo($secondState->last_completed_at));
    }

    public function test_persist_records_milestone_events_only_when_completion_changes(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-01 08:00:00'));

        $owner = User::factory()->create([
            'role' => UserRole::TENANT_OWNER->value,
        ]);

        $tenant = Tenant::factory()->forUser($owner)->create([
            'description' => 'Ferreteria de barrio.',
            'address' => 'Tegucigalpa',
            'whatsapp_number' => '+50499991111',
            'is_active' => true,
        ]);

        $this->actingAsTenant((string) $tenant->id);
        $service = app(TenantOnboardingProgressService::class);
        $service->persist($tenant, $owner);

        $initialEvents = DB::table('tenant_onboarding_milestone_events')
            ->where('tenant_id', (string) $tenant->id)
            ->where('user_id', (string) $owner->id)
            ->where('event_type', 'completed')
            ->pluck('milestone_key')
            ->all();

        self::assertContains('owner_profile', $initialEvents);

        Carbon::setTestNow(Carbon::parse('2026-05-01 09:00:00'));
        $service->persist($tenant->fresh(), $owner);

        self::assertSame(
            count($initialEvents),
            DB::table('tenant_onboarding_milestone_events')
                ->where('tenant_id', (string) $tenant->id)
                ->where('user_id', (string) $owner->id)
                ->where('event_type', 'completed')
                ->count(),
        );

        Carbon::setTestNow(Carbon::parse('2026-05-01 10:00:00'));
        Product::factory()->forTenant($tenant)->create();
        $service->persist($tenant->fresh(), $owner);

        $this->assertDatabaseHas('tenant_onboarding_milestone_events', [
            'tenant_id' => (string) $tenant->id,
            'user_id' => (string) $owner->id,
            'role' => UserRole::TENANT_OWNER->value,
            'milestone_key' => 'owner_catalog',
            'event_type' => 'completed',
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

    public function test_service_business_owner_catalog_action_points_to_services(): void
    {
        $owner = User::factory()->create([
            'role' => UserRole::TENANT_OWNER->value,
        ]);

        $tenant = Tenant::factory()->forUser($owner)->create([
            'description' => 'Consultoria fiscal para pymes.',
            'address' => 'Tegucigalpa',
            'business_type' => 'service',
        ]);

        $this->actingAsTenant((string) $tenant->id);
        $snapshot = app(TenantOnboardingProgressService::class)->buildForTenant($tenant, $owner, 'catalog');

        self::assertSame('owner_catalog', (string) $snapshot['nextAction']['key']);
        self::assertSame('Primer servicio reservable', (string) $snapshot['nextAction']['title']);
        self::assertStringContainsString('/services', (string) $snapshot['nextAction']['href']);
        self::assertSame('Servicios y citas', (string) $snapshot['businessLabel']);
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
