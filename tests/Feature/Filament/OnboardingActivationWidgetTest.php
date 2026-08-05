<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Domain\Shared\Services\TenantContext;
use App\Domain\TenantGovernance\Services\TenantOnboardingProgressService;
use App\Filament\App\Widgets\OnboardingActivationWidget;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

final class OnboardingActivationWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_app_panel_registers_the_onboarding_widget(): void
    {
        $provider = (string) file_get_contents(app_path('Providers/Filament/AppPanelProvider.php'));

        self::assertStringContainsString(OnboardingActivationWidget::class, $provider);
    }

    #[DataProvider('commercialModes')]
    public function test_widget_exposes_adaptive_real_first_run_steps(
        string $businessType,
        string $commercialMode,
        string $expectedTitle,
    ): void {
        [$owner, $tenant] = $this->ownerTenant([
            'business_type' => $businessType,
            'commercial_mode' => $commercialMode,
            'is_active' => false,
            'description' => null,
            'whatsapp_number' => null,
            'phone' => null,
            'simple_schedule' => null,
        ]);
        $this->setPanelContext($owner, $tenant);

        $method = new ReflectionMethod(OnboardingActivationWidget::class, 'getViewData');
        $method->setAccessible(true);
        $data = $method->invoke(new OnboardingActivationWidget);

        self::assertFalse((bool) $data['wizard']['completed']);
        self::assertFalse((bool) $data['wizard']['dismissed']);
        self::assertNotEmpty($data['wizard']['steps']);
        self::assertContains(
            $expectedTitle,
            array_column((array) $data['wizard']['steps'], 'title'),
        );

        Livewire::test(OnboardingActivationWidget::class)
            ->assertSeeHtml('data-testid="first-run-wizard"')
            ->assertSeeHtml('aria-current="step"')
            ->assertSeeHtml('data-testid="launch-readiness-card"')
            ->assertSee($expectedTitle);
    }

    public function test_widget_renders_ready_pending_and_blocked_by_plan_as_explicit_text_states(): void
    {
        [$owner, $tenant] = $this->ownerTenant([
            'business_type' => 'retail',
            'commercial_mode' => 'products',
            'product_limit' => 0,
            'description' => 'Negocio con perfil verificable.',
            'whatsapp_number' => '+50499990005',
        ]);
        $this->setPanelContext($owner, $tenant);

        Livewire::test(OnboardingActivationWidget::class)
            ->assertSeeHtml('data-testid="launch-readiness-card"')
            ->assertSeeHtml('data-testid="readiness-status-ready"')
            ->assertSeeHtml('data-testid="readiness-status-pending"')
            ->assertSeeHtml('data-testid="readiness-status-blocked_by_plan"')
            ->assertSee('Listo')
            ->assertSee('Pendiente')
            ->assertSee('Bloqueado por plan')
            ->assertDontSeeHtml('href="#"');
    }

    public function test_outsider_cannot_dismiss_another_tenants_wizard(): void
    {
        [$owner, $tenant] = $this->ownerTenant();
        [$outsider] = $this->ownerTenant();

        Filament::setCurrentPanel(Filament::getPanel('app'));
        Filament::setTenant($tenant, isQuiet: true);
        app(TenantContext::class)->setTenant((string) $tenant->getKey());
        $this->actingAs($outsider);

        try {
            (new OnboardingActivationWidget)->dismissFirstRunWizard();
            self::fail('Expected the foreign tenant action to be forbidden.');
        } catch (HttpException $exception) {
            self::assertSame(403, $exception->getStatusCode());
        }

        $this->assertDatabaseMissing('tenant_onboarding_states', [
            'tenant_id' => (string) $tenant->getKey(),
            'user_id' => (string) $outsider->getKey(),
            'first_run_skipped_reason' => 'owner_deferred',
        ]);

        $this->setPanelContext($owner, $tenant);
        (new OnboardingActivationWidget)->dismissFirstRunWizard();

        $this->assertDatabaseHas('tenant_onboarding_states', [
            'tenant_id' => (string) $tenant->getKey(),
            'user_id' => (string) $owner->getKey(),
            'first_run_skipped_reason' => 'owner_deferred',
        ]);

        $snapshot = app(TenantOnboardingProgressService::class)->persist($tenant->fresh(), $owner);
        self::assertLessThan(100, (int) $snapshot['progress']);
    }

    /** @return iterable<string, array{string,string,string}> */
    public static function commercialModes(): iterable
    {
        yield 'products' => ['retail', 'products', 'Primera oferta vendible'];
        yield 'appointments' => ['service', 'appointments', 'Primer servicio reservable'];
        yield 'consulting' => ['service', 'consulting_leads', 'Consulta lista para captar prospectos'];
        yield 'hybrid' => ['service', 'hybrid', 'Primer flujo comercial combinado'];
    }

    protected function tearDown(): void
    {
        app(TenantContext::class)->reset();
        Filament::setTenant(null, isQuiet: true);
        Filament::setCurrentPanel(null);

        parent::tearDown();
    }

    /** @return array{User,Tenant} */
    private function ownerTenant(array $tenantAttributes = []): array
    {
        $owner = User::factory()->tenantOwner()->create();
        $tenant = Tenant::factory()->forUser($owner)->create($tenantAttributes);
        $owner->forceFill(['tenant_id' => (string) $tenant->getKey()])->save();

        return [$owner->refresh(), $tenant];
    }

    private function setPanelContext(User $user, Tenant $tenant): void
    {
        $this->actingAs($user);
        app(TenantContext::class)->reset();
        app(TenantContext::class)->setTenant((string) $tenant->getKey());
        Filament::setCurrentPanel(Filament::getPanel('app'));
        Filament::setTenant($tenant, isQuiet: true);
    }
}
