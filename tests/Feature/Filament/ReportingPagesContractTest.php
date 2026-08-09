<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Application\Access\Contracts\TenantPermissionGuardInterface;
use App\Application\Reporting\Contracts\ReportInterface;
use App\Application\Reporting\ReportExecutor;
use App\Application\Reporting\ReportExporter;
use App\Application\Reporting\ReportRegistry;
use App\Application\Reporting\ReportScheduler;
use App\Application\Reporting\ValueObjects\ReportData;
use App\Domain\Access\Enums\Permission;
use App\Domain\Reporting\Enums\ReportKey;
use App\Domain\Reporting\ValueObjects\ReportRange;
use App\Domain\TenantGovernance\Enums\Capability;
use App\Filament\App\Pages\Reports\ReportsCompare;
use App\Filament\App\Pages\Reports\ReportsLibrary;
use App\Filament\App\Pages\Reports\ReportsOverview;
use App\Filament\App\Pages\Reports\ReportsSchedule;
use App\Filament\App\Pages\Reports\ReportsShowcase;
use App\Models\ReportSchedule;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\TestCase;

final class ReportingPagesContractTest extends TestCase
{
    use RefreshDatabase;

    /** @return iterable<string, array{class-string, string, string}> */
    public static function pageViews(): iterable
    {
        yield 'overview' => [ReportsOverview::class, 'filament.app.pages.reports.reports-overview', 'Centro de reportes'];
        yield 'showcase' => [ReportsShowcase::class, 'filament.app.pages.reports.reports-showcase', 'Vista del reporte'];
        yield 'schedule' => [ReportsSchedule::class, 'filament.app.pages.reports.reports-schedule', 'Programar reporte'];
        yield 'library' => [ReportsLibrary::class, 'filament.app.pages.reports.reports-library', 'Biblioteca de reportes'];
        yield 'compare' => [ReportsCompare::class, 'filament.app.pages.reports.reports-compare', 'Comparar reportes'];
    }

    #[Test]
    #[DataProvider('pageViews')]
    public function reporting_pages_use_real_accessible_responsive_views(string $pageClass, string $viewName, string $landmark): void
    {
        $property = (new ReflectionClass($pageClass))->getProperty('view');
        $page = (new ReflectionClass($pageClass))->newInstanceWithoutConstructor();

        self::assertSame($viewName, $property->getValue($page));
        self::assertTrue(view()->exists($viewName));

        $source = (string) file_get_contents(resource_path('views/'.str_replace('.', '/', $viewName).'.blade.php'));
        self::assertStringContainsString('aria-labelledby=', $source);
        self::assertStringContainsString('class="grid ', $source);
        self::assertStringContainsString($landmark, $source);
        self::assertStringNotContainsString('gradient', strtolower($source));
        self::assertStringNotContainsString('placeholder', strtolower($source));
    }

    #[Test]
    public function every_reporting_page_requires_analytics_view_for_the_exact_tenant(): void
    {
        [$user, $tenant] = $this->tenantUser();
        $this->actingAs($user);
        Filament::setTenant($tenant, isQuiet: true);

        $guard = Mockery::mock(TenantPermissionGuardInterface::class);
        $guard->shouldReceive('allows')
            ->times(5)
            ->with($user, $tenant, Capability::ANALYTICS_BASIC, Permission::ANALYTICS_VIEW)
            ->andReturnTrue();
        $this->app->instance(TenantPermissionGuardInterface::class, $guard);

        foreach ([ReportsOverview::class, ReportsShowcase::class, ReportsSchedule::class, ReportsLibrary::class, ReportsCompare::class] as $page) {
            self::assertTrue($page::canAccess());
        }
    }

    #[Test]
    public function overview_and_library_are_derived_only_from_registered_reports(): void
    {
        $registry = new ReportRegistry;
        $registry->register(new ReportingContractReport(ReportKey::RevenueByMonth));
        $this->app->instance(ReportRegistry::class, $registry);

        self::assertSame(['Ingresos'], array_keys((new ReportsOverview)->getViewData()['reportsByCategory']));
        self::assertSame([ReportKey::RevenueByMonth], array_map(
            static fn (ReportInterface $report): ReportKey => $report->getKey(),
            (new ReportsLibrary)->getViewData()['reports'],
        ));
    }

    #[Test]
    public function registered_report_links_route_with_the_canonical_key_and_select_its_heading(): void
    {
        [$user, $tenant] = $this->tenantUser();
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('app'));

        $guard = Mockery::mock(TenantPermissionGuardInterface::class);
        $guard->shouldReceive('allows')->andReturnTrue();
        $this->app->instance(TenantPermissionGuardInterface::class, $guard);

        $registry = new ReportRegistry;
        $registry->register(new ReportingContractReport(ReportKey::RevenueByMonth));
        $this->app->instance(ReportRegistry::class, $registry);

        $executor = Mockery::mock(ReportExecutor::class);
        $executor->shouldReceive('execute')->once()->andReturn(new ReportData([], []));
        $this->app->instance(ReportExecutor::class, $executor);

        $overviewUrl = ReportsOverview::getUrl(tenant: $tenant);
        $libraryUrl = ReportsLibrary::getUrl(tenant: $tenant);
        $showcaseUrl = ReportsShowcase::getUrl(['key' => ReportKey::RevenueByMonth->value], tenant: $tenant);

        $this->get($overviewUrl)
            ->assertOk()
            ->assertSee($showcaseUrl, escape: false);

        $this->get($libraryUrl)
            ->assertOk()
            ->assertSee($showcaseUrl, escape: false);

        $this->get($showcaseUrl)
            ->assertOk()
            ->assertSee(ReportKey::RevenueByMonth->label())
            ->assertDontSee('Reporte no disponible');
    }

    #[Test]
    public function showcase_accepts_only_registered_keys_and_closed_ranges(): void
    {
        [, $tenant] = $this->tenantUser();
        Filament::setTenant($tenant, isQuiet: true);
        $registry = new ReportRegistry;
        $registry->register(new ReportingContractReport(ReportKey::RevenueByMonth));
        $this->app->instance(ReportRegistry::class, $registry);

        $executor = Mockery::mock(ReportExecutor::class);
        $executor->shouldReceive('execute')->once()->withArgs(
            fn (ReportKey $key, string $tenantId, ReportRange $range): bool => $key === ReportKey::RevenueByMonth
                && $tenantId === (string) $tenant->getKey()
                && $range->days() === 30,
        )->andReturn(new ReportData([], []));
        $this->app->instance(ReportExecutor::class, $executor);

        $page = new ReportsShowcase;
        $page->reportKey = ReportKey::RevenueByMonth->value;
        $page->range = 'raw-user-range';

        self::assertSame([], $page->getViewData()['data']['rows']);

        $page->reportKey = ReportKey::WinRate->value;
        self::assertNull($page->getViewData()['report']);
    }

    #[Test]
    public function comparison_requires_two_distinct_registered_report_keys(): void
    {
        [, $tenant] = $this->tenantUser();
        Filament::setTenant($tenant, isQuiet: true);
        $registry = new ReportRegistry;
        $registry->register(new ReportingContractReport(ReportKey::RevenueByMonth));
        $registry->register(new ReportingContractReport(ReportKey::WinRate));
        $this->app->instance(ReportRegistry::class, $registry);

        $executor = Mockery::mock(ReportExecutor::class);
        $executor->shouldReceive('execute')->twice()->andReturn(new ReportData([], []));
        $this->app->instance(ReportExecutor::class, $executor);

        $page = new ReportsCompare;
        $page->primaryKey = ReportKey::RevenueByMonth->value;
        $page->secondaryKey = ReportKey::WinRate->value;
        self::assertNotNull($page->getViewData()['comparison']);

        $page->secondaryKey = ReportKey::RevenueByMonth->value;
        self::assertNull($page->getViewData()['comparison']);
    }

    #[Test]
    public function schedule_normalizes_closed_input_and_never_trusts_submitted_identity(): void
    {
        CarbonImmutable::setTestNow('2026-08-09 10:00:00');
        [$user, $tenant] = $this->tenantUser();
        $this->actingAs($user);
        Filament::setTenant($tenant, isQuiet: true);
        Mail::fake();

        $guard = Mockery::mock(TenantPermissionGuardInterface::class);
        $guard->shouldReceive('allows')->once()->with(
            $user,
            $tenant,
            Capability::ANALYTICS_BASIC,
            Permission::ANALYTICS_EXPORT,
        )->andReturnTrue();
        $this->app->instance(TenantPermissionGuardInterface::class, $guard);

        $registry = new ReportRegistry;
        $registry->register(new ReportingContractReport(ReportKey::RevenueByMonth));
        $this->app->instance(ReportRegistry::class, $registry);

        $this->app->instance(
            ReportScheduler::class,
            new ReportScheduler(
                $registry,
                Mockery::mock(ReportExecutor::class),
                Mockery::mock(ReportExporter::class),
            ),
        );

        (new ReportsSchedule)->createSchedule([
            'tenant_id' => 'foreign-tenant',
            'user_id' => 'foreign-user',
            'report_key' => ReportKey::RevenueByMonth->value,
            'frequency' => 'weekly',
            'format' => 'csv',
            'recipients' => [' Owner@Example.Test ', 'invalid', 'owner@example.test', 'ops@example.test'],
        ]);

        $this->assertDatabaseHas('report_schedules', [
            'tenant_id' => (string) $tenant->getKey(),
            'user_id' => null,
            'report_key' => ReportKey::RevenueByMonth->value,
            'frequency' => 'weekly',
            'format' => 'csv',
            'next_run_at' => '2026-08-10 08:00:00',
        ]);
        self::assertSame(
            ['owner@example.test', 'ops@example.test'],
            ReportSchedule::query()->sole()->recipients,
        );
        Mail::assertNothingSent();
        CarbonImmutable::setTestNow();
    }

    #[Test]
    public function schedule_action_is_closed_when_analytics_export_is_denied(): void
    {
        [$user, $tenant] = $this->tenantUser();
        $this->actingAs($user);
        Filament::setTenant($tenant, isQuiet: true);

        $guard = Mockery::mock(TenantPermissionGuardInterface::class);
        $guard->shouldReceive('allows')->once()->with(
            $user,
            $tenant,
            Capability::ANALYTICS_BASIC,
            Permission::ANALYTICS_EXPORT,
        )->andReturnFalse();
        $this->app->instance(TenantPermissionGuardInterface::class, $guard);

        (new ReportsSchedule)->createSchedule([
            'report_key' => ReportKey::RevenueByMonth->value,
            'frequency' => 'daily',
            'format' => 'pdf',
            'recipients' => ['owner@example.test'],
        ]);

        $this->assertDatabaseCount('report_schedules', 0);
    }

    /** @return array{User, Tenant} */
    private function tenantUser(): array
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->forUser($user)->create();
        $user->forceFill(['tenant_id' => $tenant->getKey()])->save();

        return [$user->refresh(), $tenant];
    }
}

final class ReportingContractReport implements ReportInterface
{
    public function __construct(private readonly ReportKey $key) {}

    public function getKey(): ReportKey
    {
        return $this->key;
    }

    public function getName(): string
    {
        return $this->key->label();
    }

    public function getDescription(): string
    {
        return 'Operational report';
    }

    public function getChartType(): string
    {
        return $this->key->chartType();
    }

    public function getColumns(): array
    {
        return [];
    }

    public function getFilters(): array
    {
        return [];
    }

    public function getData(string $tenantId, ReportRange $range): ReportData
    {
        return new ReportData([], []);
    }
}
