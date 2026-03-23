<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Domain\Identity\Enums\UserRole;
use App\Filament\App\Widgets\AccountCompletenessWidget;
use App\Filament\App\Widgets\FollowerCountWidget;
use App\Filament\App\Widgets\FreePlanUpsellWidget;
use App\Filament\App\Widgets\PendingReviewAlert;
use App\Filament\App\Widgets\ProductLimitWarning;
use App\Filament\App\Widgets\ProductLimitWidget;
use App\Filament\App\Widgets\QuickActionsWidget;
use App\Filament\App\Widgets\TenantStatsWidget;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class AppPanelWidgetSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_widgets_render_without_livewire_errors(): void
    {
        [$user, $tenant] = $this->makeTenantOwner();

        $this->actingAs($user);
        $this->setAppPanelTenantContext($tenant);

        Livewire::test(TenantStatsWidget::class)->assertOk();
        Livewire::test(ProductLimitWidget::class)->assertOk();
        Livewire::test(AccountCompletenessWidget::class)->assertOk();
        Livewire::test(QuickActionsWidget::class)->assertOk();
        Livewire::test(ProductLimitWarning::class)->assertOk();
        Livewire::test(PendingReviewAlert::class)->assertOk();
        Livewire::test(FreePlanUpsellWidget::class)->assertOk();
    }

    public function test_follower_widget_renders_on_the_followers_page_context(): void
    {
        [$user, $tenant] = $this->makeTenantOwner();

        $this->actingAs($user);
        $this->setAppPanelTenantContext($tenant);

        Livewire::test(FollowerCountWidget::class)
            ->assertOk()
            ->assertSee('Seguidores');
    }

    protected function tearDown(): void
    {
        Filament::setTenant(null, isQuiet: true);
        Filament::setCurrentPanel(null);

        parent::tearDown();
    }

    /**
     * @return array{0: User, 1: Tenant}
     */
    private function makeTenantOwner(): array
    {
        $user = User::factory()->create([
            'role' => UserRole::TENANT_OWNER,
        ]);

        $tenant = Tenant::factory()->create([
            'user_id' => $user->id,
            'slug' => 'cafe-norte-oficial',
        ]);

        $user->forceFill(['tenant_id' => $tenant->id])->save();

        return [$user, $tenant];
    }

    private function setAppPanelTenantContext(Tenant $tenant): void
    {
        Filament::setCurrentPanel('app');
        Filament::setTenant($tenant, isQuiet: true);
        $this->actingAsTenant((string) $tenant->getKey());
    }
}
