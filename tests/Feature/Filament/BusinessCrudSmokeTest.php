<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Domain\Identity\Enums\UserRole;
use App\Filament\App\Resources\CampaignResource\Pages\CreateCampaign;
use App\Filament\App\Resources\Leads\Pages\CreateLead;
use App\Filament\App\Resources\ProductResource\Pages\CreateProduct;
use App\Models\Campaign;
use App\Models\Category;
use App\Models\City;
use App\Models\Lead;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class BusinessCrudSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_pro_owner_can_create_campaign_from_filament_page(): void
    {
        [$user, $tenant] = $this->makeTenantOwner(isPro: true);

        $this->actingAs($user);
        $this->setAppPanelTenantContext($tenant);

        Livewire::test(CreateCampaign::class)
            ->set('data.audience_type', 'followers')
            ->set('data.subject', 'Lanzamiento del menu de temporada')
            ->set('data.content', '<p>Ven a conocer nuestras novedades.</p>')
            ->call('create')
            ->assertHasNoErrors()
            ->assertRedirect(route('filament.app.resources.campaigns.index', ['tenant' => $tenant->slug], false));

        $campaign = Campaign::query()->first();

        $this->assertNotNull($campaign);
        $this->assertSame((string) $tenant->getKey(), (string) $campaign->tenant_id);
        $this->assertSame('Lanzamiento del menu de temporada', $campaign->subject);
    }

    public function test_owner_can_create_lead_from_filament_page(): void
    {
        [$user, $tenant] = $this->makeTenantOwner(isPro: false);

        $this->actingAs($user);
        $this->setAppPanelTenantContext($tenant);

        Livewire::test(CreateLead::class)
            ->set('data.name', 'Maria Lopez')
            ->set('data.email', 'maria@example.com')
            ->set('data.phone', '9999-1111')
            ->set('data.source', 'manual')
            ->set('data.status', 'new')
            ->set('data.message', 'Cliente interesada en pedir por WhatsApp.')
            ->call('create')
            ->assertHasNoErrors()
            ->assertRedirect(route('filament.app.resources.leads.index', ['tenant' => $tenant->slug], false));

        $lead = Lead::query()->first();

        $this->assertNotNull($lead);
        $this->assertSame((string) $tenant->getKey(), (string) $lead->tenant_id);
        $this->assertSame('Maria Lopez', $lead->name);
    }

    public function test_owner_can_create_product_from_filament_page(): void
    {
        [$user, $tenant] = $this->makeTenantOwner(isPro: false);
        $category = Category::factory()->create();

        $this->actingAs($user);
        $this->setAppPanelTenantContext($tenant);

        Livewire::test(CreateProduct::class)
            ->set('data.name', 'Cafe latte')
            ->set('data.slug', 'cafe-latte')
            ->set('data.price', '49.50')
            ->set('data.description', 'Cafe con leche y espuma.')
            ->set('data.category_id', $category->getKey())
            ->set('data.is_visible', true)
            ->call('create')
            ->assertHasNoErrors()
            ->assertRedirect(route('filament.app.resources.products.index', ['tenant' => $tenant->slug], false));

        $product = Product::query()->withoutGlobalScopes()->first();

        $this->assertNotNull($product);
        $this->assertSame((string) $tenant->getKey(), (string) $product->tenant_id);
        $this->assertSame((string) $tenant->city_id, (string) $product->city_id);
        $this->assertSame('Cafe latte', $product->name);
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
    private function makeTenantOwner(bool $isPro): array
    {
        $user = User::factory()->create([
            'role' => UserRole::TENANT_OWNER,
        ]);

        $city = City::factory()->create();
        $category = Category::factory()->create();

        $tenantFactory = Tenant::factory()->state([
            'user_id' => $user->id,
            'slug' => $isPro ? 'cafe-norte-pro-crud' : 'cafe-norte-base-crud',
            'business_type' => 'retail',
            'city_id' => $city->getKey(),
            'category_id' => $category->getKey(),
        ]);

        if ($isPro) {
            $tenantFactory = $tenantFactory->pro();
        }

        $tenant = $tenantFactory->create();

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
