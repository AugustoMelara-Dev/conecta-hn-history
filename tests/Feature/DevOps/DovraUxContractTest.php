<?php

declare(strict_types=1);

namespace Tests\Feature\DevOps;

use App\Domain\TenantGovernance\Services\TenantOnboardingProgressService;
use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

final class DovraUxContractTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Model::setEventDispatcher(app('events'));

        parent::tearDown();
    }

    public function test_program_ten_fixture_keeps_brand_truth_and_non_honduras_market_selection_visible(): void
    {
        $fixture = $this->fixture();

        self::assertSame('Dovra AI', $fixture['brand']['name']);
        self::assertSame('Dovra', $fixture['brand']['shortName']);
        self::assertSame('react-component-preview', $fixture['evidence']['surface']);
        self::assertFalse($fixture['evidence']['applicationSurface']);
        self::assertSame('not-exposed', $fixture['evidence']['routeExposure']);
        self::assertSame('fixture-preview-only', $fixture['evidence']['marketAuthority']);
        self::assertSame('non-honduras', $fixture['evidence']['marketDefaultPolicy']);

        self::assertSame(['products', 'services', 'hybrid'], array_column($fixture['scenarios'], 'key'));

        self::assertSame(['GT', 'SV', 'PA'], array_column($fixture['scenarios'], 'defaultMarketCode'));

        foreach ($fixture['scenarios'] as $scenario) {
            $defaultMarket = collect($scenario['marketOptions'])
                ->firstWhere('code', $scenario['defaultMarketCode']);

            self::assertIsArray($defaultMarket);
            self::assertFalse($defaultMarket['isHonduras']);
            self::assertSame('fixture-preview', $scenario['defaultMarketSource']);
            self::assertStringContainsString('preview no-Honduras', $defaultMarket['note']);
            self::assertNotEmpty($scenario['buyer']['reversibleCopy']);
            self::assertNotEmpty($scenario['buyer']['liveMessage']);
            self::assertNotEmpty($scenario['onboarding']['authoritativeNote']);
        }
    }

    public function test_routed_browser_artifacts_use_authoritative_application_surfaces(): void
    {
        self::assertFileExists(base_path('resources/js/__tests__/dovra-ux.contract.test.jsx'));
        self::assertFileDoesNotExist(base_path('resources/js/__tests__/dovra-program10.contract.test.jsx'));
        self::assertFileExists(base_path('tests/e2e/dovra-launch-critical.spec.ts'));
        self::assertFileExists(base_path('tests/e2e/support/dovra-routed-surface.ts'));
        self::assertFileDoesNotExist(base_path('tests/e2e/dovra-program10-critical.spec.ts'));
        self::assertFileExists(base_path('docs/dovra/evidence/program-10-2026-08-01.md'));

        foreach (['tests/e2e/dovra-launch-critical.spec.ts', 'tests/e2e/dovra-accessibility.spec.ts'] as $path) {
            $source = (string) file_get_contents(base_path($path));

            self::assertStringNotContainsString('page.setContent(', $source);
            self::assertStringNotContainsString('buildMarkup', $source);
            self::assertStringNotContainsString('mountFixture', $source);
            self::assertStringNotContainsString('dovra-program-10.fixtures.json', $source);
            self::assertStringNotContainsString('HTML harness evidence only', $source);
            self::assertStringNotContainsString('applicationSurface: false', $source);
        }

        $routedHelper = (string) file_get_contents(base_path('tests/e2e/support/dovra-routed-surface.ts'));
        self::assertStringContainsString('getComputedStyle(', $routedHelper);
        self::assertStringContainsString('transitionDuration', $routedHelper);
        self::assertStringContainsString('animationDuration', $routedHelper);

        $page = (string) file_get_contents(base_path('resources/js/Pages/Auth/RegisterTenant.jsx'));
        $wizard = (string) file_get_contents(base_path('resources/js/Components/Auth/RegisterTenantWizard.jsx'));
        $layout = (string) file_get_contents(base_path('resources/js/Components/Auth/AuthSplitLayout.jsx'));

        self::assertStringContainsString('<RegisterTenantWizard {...props} />', $page);
        self::assertStringNotContainsString('<main', $page);
        self::assertStringNotContainsString('SkipLink', $page);
        self::assertStringContainsString('<AuthSplitLayout', $wizard);
        self::assertStringContainsString('SkipLink', $layout);
        self::assertStringContainsString('id="main-content"', $layout);
        self::assertStringContainsString('tabIndex={-1}', $layout);

        $wizard = (string) file_get_contents(base_path('resources/js/Components/Auth/RegisterTenantWizard.jsx'));
        $directory = (string) file_get_contents(base_path('resources/js/Pages/Directory/Index.jsx'));
        self::assertStringContainsString('motion-reduce:transition-none', $wizard);
        self::assertStringContainsString('motion-reduce:transition-none', $directory);
    }

    public function test_directory_route_renders_the_authoritative_inertia_surface(): void
    {
        $response = $this->get('/directorio');

        $response->assertSuccessful();
        $response->assertInertia(
            fn (AssertableInertia $page) => $page->component('Directory/Index'),
        );
    }

    public function test_registration_route_renders_the_authoritative_inertia_surface(): void
    {
        $response = $this->get('/registrar-negocio');

        $response->assertSuccessful();
        $response->assertInertia(
            fn (AssertableInertia $page) => $page->component('Auth/RegisterTenant'),
        );
    }

    public function test_client_acknowledgement_alone_does_not_mark_authoritative_onboarding_complete(): void
    {
        $owner = User::factory()->create([
            'role' => UserRole::TENANT_OWNER->value,
        ]);

        $tenant = Tenant::factory()->forUser($owner)->create([
            'business_type' => 'retail',
            'commercial_mode' => 'products',
            'description' => null,
            'whatsapp_number' => null,
            'is_active' => false,
        ]);

        $this->setTenantContext($tenant);
        $service = app(TenantOnboardingProgressService::class);
        $wizard = $service->firstRunWizardFor($tenant, $owner);
        $firstStepKey = (string) ($wizard['currentStepKey'] ?? '');

        self::assertNotSame('', $firstStepKey);
        self::assertFalse((bool) $wizard['completed']);

        $advanced = $service->completeFirstRunStep($tenant->fresh(), $owner, $firstStepKey);

        self::assertFalse((bool) $advanced['completed']);
        self::assertSame($firstStepKey, (string) $advanced['currentStepKey']);
        self::assertNotContains($firstStepKey, $advanced['completedSteps']);
        self::assertSame(0, count($advanced['completedSteps']));
    }

    /** @return array<string, mixed> */
    private function fixture(): array
    {
        return json_decode(
            (string) file_get_contents(resource_path('stories/dovra-program-10.fixtures.json')),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
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
