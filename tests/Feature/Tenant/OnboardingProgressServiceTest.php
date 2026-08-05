<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant;

use App\Domain\TenantGovernance\Services\TenantOnboardingProgressService;
use App\Enums\UserRole;
use App\Models\Product;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\TenantOnboardingState;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class OnboardingProgressServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Tenant::unsetEventDispatcher();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Model::setEventDispatcher(app('events'));

        parent::tearDown();
    }

    public function test_persists_onboarding_progress_snapshot_for_tenant_owner(): void
    {
        $owner = User::factory()->create([
            'role' => UserRole::TENANT_OWNER->value,
        ]);

        $tenant = Tenant::factory()->forUser($owner)->create([
            'description' => 'Clinica con servicios premium.',
            'whatsapp_number' => '+50499998888',
            'is_active' => true,
        ]);

        Product::factory()->forTenant($tenant)->create();

        $this->setTenantContext($tenant);
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
            'whatsapp_number' => '+50499990000',
            'is_active' => true,
        ]);

        $this->setTenantContext($tenant);
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
            'whatsapp_number' => '+50499991111',
            'is_active' => true,
        ]);

        $this->setTenantContext($tenant);
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
            'phone' => '+50422112211',
            'business_type' => 'service',
        ]);

        $this->setTenantContext($tenant);
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
            'is_active' => true,
            'business_type' => 'service',
        ]);

        $this->setTenantContext($tenant);
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
            'whatsapp_number' => '+50499887766',
            'is_active' => true,
            'business_type' => 'service',
        ]);

        $this->setTenantContext($tenant);
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
            'business_type' => 'service',
        ]);

        $this->setTenantContext($tenant);
        $snapshot = app(TenantOnboardingProgressService::class)->buildForTenant($tenant, $owner, 'catalog');

        self::assertSame('owner_catalog', (string) $snapshot['nextAction']['key']);
        self::assertSame('Primer servicio reservable', (string) $snapshot['nextAction']['title']);
        self::assertStringContainsString('/services', (string) $snapshot['nextAction']['href']);
        self::assertNotSame('Tienda y catalogo', (string) $snapshot['businessLabel']);
    }

    public function test_consulting_leads_owner_offer_action_points_to_settings_not_services(): void
    {
        $owner = User::factory()->create([
            'role' => UserRole::TENANT_OWNER->value,
        ]);

        $tenant = Tenant::factory()->forUser($owner)->create([
            'description' => 'Consultoria fiscal para pymes.',
            'business_type' => 'service',
            'commercial_mode' => 'consulting_leads',
        ]);

        $this->setTenantContext($tenant);
        $snapshot = app(TenantOnboardingProgressService::class)->buildForTenant($tenant, $owner, 'catalog');

        self::assertSame('owner_catalog', (string) $snapshot['nextAction']['key']);
        self::assertSame('Consulta lista para captar prospectos', (string) $snapshot['nextAction']['title']);
        self::assertStringNotContainsString('/services', (string) $snapshot['nextAction']['href']);
        self::assertStringContainsString('/settings', (string) $snapshot['nextAction']['href']);
        self::assertNotSame('Servicios y citas', (string) $snapshot['businessLabel']);
    }

    public function test_staff_context_prioritizes_agenda_next_action_when_incomplete(): void
    {
        $staff = User::factory()->create([
            'role' => UserRole::STAFF->value,
        ]);

        $tenant = Tenant::factory()->create([
            'description' => 'Clinica',
            'business_type' => 'service',
        ]);

        $this->setTenantContext($tenant);
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

        $this->setTenantContext($tenant);
        $snapshot = app(TenantOnboardingProgressService::class)->buildForTenant($tenant, $customer, 'team');

        self::assertSame('customer_follow', (string) $snapshot['nextAction']['key']);
    }

    public function test_first_run_wizard_uses_authoritative_progress_and_allows_navigation_and_dismissal(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-04 08:00:00'));

        $owner = User::factory()->create([
            'role' => UserRole::TENANT_OWNER->value,
        ]);

        $tenant = Tenant::factory()->forUser($owner)->create([
            'business_type' => 'retail',
            'is_active' => false,
        ]);

        $this->setTenantContext($tenant);
        $service = app(TenantOnboardingProgressService::class);

        $wizard = $service->firstRunWizardFor($tenant, $owner);
        $firstStepKey = (string) $wizard['currentStepKey'];

        self::assertFalse((bool) $wizard['dismissed']);
        self::assertFalse((bool) $wizard['completed']);
        self::assertNotSame('', $firstStepKey);
        self::assertNotEmpty($wizard['steps']);

        $this->assertDatabaseHas('tenant_onboarding_milestone_events', [
            'tenant_id' => (string) $tenant->id,
            'user_id' => (string) $owner->id,
            'event_type' => 'first_run_started',
        ]);

        $advanced = $service->completeFirstRunStep($tenant->fresh(), $owner, $firstStepKey);

        self::assertNotContains($firstStepKey, $advanced['completedSteps']);
        self::assertSame($firstStepKey, (string) $advanced['currentStepKey']);
        self::assertFalse((bool) $advanced['completed']);

        $service->moveFirstRunStep($tenant->fresh(), $owner, $firstStepKey);
        $movedState = TenantOnboardingState::query()
            ->where('tenant_id', (string) $tenant->id)
            ->where('user_id', (string) $owner->id)
            ->firstOrFail();

        self::assertSame($firstStepKey, (string) $movedState->first_run_step_key);

        $dismissed = $service->dismissFirstRunWizard($tenant->fresh(), $owner, 'needs_team_review');

        self::assertTrue((bool) $dismissed['dismissed']);
        $this->assertDatabaseHas('tenant_onboarding_states', [
            'tenant_id' => (string) $tenant->id,
            'user_id' => (string) $owner->id,
            'first_run_skipped_reason' => 'needs_team_review',
        ]);

        $snapshot = $service->persist($tenant->fresh(), $owner);

        self::assertArrayHasKey('nextAction', $snapshot);
        self::assertNotEmpty($snapshot['nextAction']['href']);
    }

    public function test_first_run_abandonment_metric_is_recorded_once(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-01 08:00:00'));

        $owner = User::factory()->create([
            'role' => UserRole::TENANT_OWNER->value,
        ]);

        $tenant = Tenant::factory()->forUser($owner)->create([
            'business_type' => 'service',
            'is_active' => false,
        ]);

        $this->setTenantContext($tenant);
        $service = app(TenantOnboardingProgressService::class);
        $service->firstRunWizardFor($tenant, $owner);

        $state = TenantOnboardingState::query()
            ->where('tenant_id', (string) $tenant->id)
            ->where('user_id', (string) $owner->id)
            ->firstOrFail();

        $state->forceFill([
            'first_run_last_seen_at' => Carbon::parse('2026-05-01 08:00:00'),
            'first_run_abandoned_at' => null,
        ])->save();

        Carbon::setTestNow(Carbon::parse('2026-05-03 09:00:00'));
        $service->firstRunWizardFor($tenant->fresh(), $owner);

        $abandonedAt = $state->fresh()?->first_run_abandoned_at;
        self::assertInstanceOf(Carbon::class, $abandonedAt);

        Carbon::setTestNow(Carbon::parse('2026-05-04 10:00:00'));
        $service->firstRunWizardFor($tenant->fresh(), $owner);

        $freshState = $state->fresh();
        self::assertInstanceOf(Carbon::class, $freshState?->first_run_abandoned_at);
        self::assertTrue($abandonedAt->equalTo($freshState->first_run_abandoned_at));
        self::assertSame(
            1,
            DB::table('tenant_onboarding_milestone_events')
                ->where('tenant_id', (string) $tenant->id)
                ->where('user_id', (string) $owner->id)
                ->where('event_type', 'first_run_abandoned')
                ->count(),
        );
    }

    public function test_authoritative_recompute_invalidates_a_stale_legacy_completion(): void
    {
        $owner = User::factory()->create([
            'role' => UserRole::TENANT_OWNER->value,
        ]);
        $tenant = Tenant::factory()->forUser($owner)->create([
            'business_type' => 'retail',
            'commercial_mode' => 'products',
            'is_active' => false,
        ]);

        $this->setTenantContext($tenant);
        $service = app(TenantOnboardingProgressService::class);
        $wizard = $service->firstRunWizardFor($tenant, $owner);
        $allStepKeys = array_column((array) $wizard['steps'], 'key');

        $state = TenantOnboardingState::query()
            ->where('tenant_id', (string) $tenant->getKey())
            ->where('user_id', (string) $owner->getKey())
            ->firstOrFail();
        $state->forceFill([
            'first_run_completed_steps' => $allStepKeys,
            'first_run_completed_at' => now(),
        ])->save();

        $recomputed = $service->firstRunWizardFor($tenant->fresh(), $owner);

        self::assertFalse((bool) $recomputed['completed']);
        self::assertNull($recomputed['completedAt']);
        self::assertNotEqualsCanonicalizing($allStepKeys, $recomputed['completedSteps']);
        $this->assertDatabaseHas('tenant_onboarding_milestone_events', [
            'tenant_id' => (string) $tenant->getKey(),
            'user_id' => (string) $owner->getKey(),
            'event_type' => 'first_run_regressed',
        ]);
    }

    public function test_offer_readiness_requires_a_visible_product(): void
    {
        $owner = User::factory()->create([
            'role' => UserRole::TENANT_OWNER->value,
        ]);
        $tenant = Tenant::factory()->forUser($owner)->create([
            'business_type' => 'retail',
            'commercial_mode' => 'products',
        ]);
        $product = Product::factory()->forTenant($tenant)->create([
            'is_visible' => false,
        ]);

        $this->setTenantContext($tenant);
        $service = app(TenantOnboardingProgressService::class);

        $hidden = collect($service->buildForTenant($tenant->fresh(), $owner)['milestones'])
            ->firstWhere('key', 'owner_catalog');
        self::assertFalse((bool) $hidden['completed']);

        $product->forceFill(['is_visible' => true])->save();
        $published = collect($service->buildForTenant($tenant->fresh(), $owner)['milestones'])
            ->firstWhere('key', 'owner_catalog');
        self::assertTrue((bool) $published['completed']);
    }

    public function test_offer_readiness_cannot_be_satisfied_by_the_wrong_commercial_capability(): void
    {
        $productsOwner = User::factory()->create(['role' => UserRole::TENANT_OWNER->value]);
        $productsTenant = Tenant::factory()->forUser($productsOwner)->create([
            'business_type' => 'retail',
            'commercial_mode' => 'products',
        ]);
        Service::factory()->create(['tenant_id' => $productsTenant->getKey(), 'is_active' => true]);

        $this->setTenantContext($productsTenant);
        $productsOffer = collect(app(TenantOnboardingProgressService::class)
            ->buildForTenant($productsTenant->fresh(), $productsOwner)['milestones'])
            ->firstWhere('key', 'owner_catalog');
        self::assertFalse((bool) $productsOffer['completed']);

        $appointmentsOwner = User::factory()->create(['role' => UserRole::TENANT_OWNER->value]);
        $appointmentsTenant = Tenant::factory()->forUser($appointmentsOwner)->create([
            'business_type' => 'service',
            'commercial_mode' => 'appointments',
        ]);
        Product::factory()->forTenant($appointmentsTenant)->create(['is_visible' => true]);

        $this->setTenantContext($appointmentsTenant);
        $appointmentsOffer = collect(app(TenantOnboardingProgressService::class)
            ->buildForTenant($appointmentsTenant->fresh(), $appointmentsOwner)['milestones'])
            ->firstWhere('key', 'owner_catalog');
        self::assertFalse((bool) $appointmentsOffer['completed']);
    }

    public function test_hybrid_offer_requires_both_a_visible_product_and_an_active_service(): void
    {
        $owner = User::factory()->create(['role' => UserRole::TENANT_OWNER->value]);
        $tenant = Tenant::factory()->forUser($owner)->create([
            'business_type' => 'service',
            'commercial_mode' => 'hybrid',
            'product_limit' => 0,
        ]);
        Product::factory()->forTenant($tenant)->create(['is_visible' => true]);

        $this->setTenantContext($tenant);
        $service = app(TenantOnboardingProgressService::class);
        $withProductOnly = collect($service->buildForTenant($tenant->fresh(), $owner)['milestones'])
            ->firstWhere('key', 'owner_catalog');

        self::assertFalse((bool) $withProductOnly['completed']);
        self::assertSame('pending', (string) $withProductOnly['status']);
        self::assertStringContainsString('/services', (string) $withProductOnly['href']);
        self::assertStringNotContainsString('billing', (string) $withProductOnly['href']);

        Service::factory()->create(['tenant_id' => $tenant->getKey(), 'is_active' => true]);
        $withBoth = collect($service->buildForTenant($tenant->fresh(), $owner)['milestones'])
            ->firstWhere('key', 'owner_catalog');

        self::assertTrue((bool) $withBoth['completed']);
        self::assertSame('ready', (string) $withBoth['status']);
    }

    public function test_plan_blocked_offer_is_explicit_and_routes_to_billing(): void
    {
        $owner = User::factory()->create(['role' => UserRole::TENANT_OWNER->value]);
        $tenant = Tenant::factory()->forUser($owner)->create([
            'business_type' => 'retail',
            'commercial_mode' => 'products',
            'product_limit' => 0,
        ]);

        $this->setTenantContext($tenant);
        $offer = collect(app(TenantOnboardingProgressService::class)
            ->buildForTenant($tenant->fresh(), $owner)['milestones'])
            ->firstWhere('key', 'owner_catalog');

        self::assertFalse((bool) $offer['completed']);
        self::assertSame('blocked_by_plan', (string) $offer['status']);
        self::assertNotSame('', (string) $offer['blockedReason']);
        self::assertNotSame('#', (string) $offer['href']);
        self::assertStringContainsString('billing', (string) $offer['href']);
    }

    public function test_route_helper_falls_back_to_the_tenant_dashboard_instead_of_hash(): void
    {
        $owner = User::factory()->create(['role' => UserRole::TENANT_OWNER->value]);
        $tenant = Tenant::factory()->forUser($owner)->create();
        $method = new \ReflectionMethod(TenantOnboardingProgressService::class, 'routeWithFallback');
        $method->setAccessible(true);

        $href = $method->invoke(
            app(TenantOnboardingProgressService::class),
            'missing.onboarding.route',
            'filament.app.pages.dashboard',
            ['tenant' => $tenant->slug],
        );

        self::assertIsString($href);
        self::assertNotSame('#', $href);
        self::assertStringContainsString('/app/'.$tenant->slug, $href);
    }

    public function test_all_onboarding_navigation_helpers_fail_closed_to_useful_non_hash_routes(): void
    {
        $owner = User::factory()->create(['role' => UserRole::TENANT_OWNER->value]);
        $tenant = Tenant::factory()->forUser($owner)->create();
        $service = app(TenantOnboardingProgressService::class);

        foreach (['micrositeHref', 'settingsHref', 'operationsHref', 'appointmentsHref', 'catalogHref'] as $methodName) {
            $method = new \ReflectionMethod(TenantOnboardingProgressService::class, $methodName);
            $method->setAccessible(true);
            $href = $method->invoke($service, $tenant->slug);

            self::assertIsString($href, $methodName);
            self::assertNotSame('#', $href, $methodName);
            self::assertStringContainsString('/'.$tenant->slug, $href, $methodName);
        }
    }

    public function test_wizard_refresh_advances_from_an_authoritatively_completed_step(): void
    {
        $owner = User::factory()->create(['role' => UserRole::TENANT_OWNER->value]);
        $tenant = Tenant::factory()->forUser($owner)->create([
            'business_type' => 'retail',
            'commercial_mode' => 'products',
            'description' => null,
            'whatsapp_number' => null,
        ]);

        $this->setTenantContext($tenant);
        $service = app(TenantOnboardingProgressService::class);
        $initial = $service->firstRunWizardFor($tenant, $owner);
        self::assertSame('owner_profile', (string) $initial['currentStepKey']);

        $tenant->forceFill([
            'description' => 'Tienda con oferta clara y atencion local.',
            'whatsapp_number' => '+50499990001',
        ])->save();

        $refreshed = $service->firstRunWizardFor($tenant->fresh(), $owner);

        self::assertContains('owner_profile', $refreshed['completedSteps']);
        self::assertSame('owner_catalog', (string) $refreshed['currentStepKey']);
        self::assertTrue((bool) collect($refreshed['steps'])->firstWhere('key', 'owner_catalog')['current']);
        $this->assertDatabaseHas('tenant_onboarding_states', [
            'tenant_id' => (string) $tenant->getKey(),
            'user_id' => (string) $owner->getKey(),
            'first_run_step_key' => 'owner_catalog',
        ]);
    }

    public function test_first_run_wizard_auto_completes_when_real_milestones_are_done(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-04 11:00:00'));

        $owner = User::factory()->create([
            'role' => UserRole::TENANT_OWNER->value,
        ]);

        $tenant = Tenant::factory()->forUser($owner)->create([
            'description' => 'Tienda completa con vitrina y atencion.',
            'whatsapp_number' => '+50499992222',
            'simple_schedule' => 'Lunes a viernes 8:00 AM - 5:00 PM',
            'is_active' => true,
            'business_type' => 'retail',
        ]);
        Product::factory()->forTenant($tenant)->create();

        $this->setTenantContext($tenant);
        $wizard = app(TenantOnboardingProgressService::class)->firstRunWizardFor($tenant, $owner);

        self::assertTrue((bool) $wizard['completed']);
        self::assertSame(100, (int) $wizard['progress']);

        $this->assertDatabaseHas('tenant_onboarding_milestone_events', [
            'tenant_id' => (string) $tenant->id,
            'user_id' => (string) $owner->id,
            'event_type' => 'first_run_completed',
        ]);
    }

    private function setTenantContext(Tenant $tenant): void
    {
        if (method_exists($this, 'actingAsTenant')) {
            $this->actingAsTenant((string) $tenant->id);

            return;
        }

        if (class_exists(\App\Domain\Shared\Services\TenantContext::class)) {
            $context = app(\App\Domain\Shared\Services\TenantContext::class);

            if (method_exists($context, 'reset')) {
                $context->reset();
            }

            if (method_exists($context, 'setAsSystemRoot')) {
                $context->setAsSystemRoot();
            }
        }
    }
}
