<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Domain\Identity\Enums\UserRole;
use App\Models\Campaign;
use App\Models\Category;
use App\Models\City;
use App\Models\CustomerProfile;
use App\Models\Lead;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

final class BusinessRecordPageSmokeTest extends TestCase
{
    use DatabaseTransactions;

    public function test_business_owner_record_pages_render_with_real_data(): void
    {
        [$user, $tenant, $city, $category] = $this->makeTenantOwner(isPro: true, slug: 'cafe-norte-record-pages');

        $product = Product::factory()
            ->forTenant($tenant)
            ->create([
                'city_id' => $city->getKey(),
                'category_id' => $category->getKey(),
                'name' => 'Cafe latte QA',
                'slug' => 'cafe-latte-qa',
                'price' => 54.50,
            ]);

        $lead = Lead::query()->create([
            'tenant_id' => $tenant->getKey(),
            'product_id' => $product->getKey(),
            'name' => 'Ana Castillo',
            'email' => 'ana.castillo@example.com',
            'phone' => '9999-1111',
            'source' => 'manual',
            'message' => 'Quiero informacion del menu ejecutivo.',
            'status' => 'new',
        ]);

        $campaign = Campaign::query()->create([
            'tenant_id' => $tenant->getKey(),
            'subject' => 'Campana QA',
            'content' => '<p>Promocion de temporada.</p>',
            'audience_type' => 'followers',
            'status' => 'draft',
            'recipients_count' => 0,
        ]);

        $order = Order::factory()
            ->forTenant($tenant)
            ->create([
                'customer_name' => 'Carlos Romero',
                'customer_phone' => '9888-0000',
                'customer_email' => 'carlos.romero@example.com',
                'order_number' => '#QA1001',
                'payment_method' => 'cash',
                'payment_status' => 'pending',
                'status' => Order::STATUS_PENDING,
            ]);

        OrderItem::factory()
            ->forOrder($order)
            ->forProduct($product)
            ->create([
                'quantity' => 2,
                'selected_options' => [
                    ['group' => 'Tamano', 'option' => 'Grande'],
                ],
            ]);

        $this->setAppPanelTenantContext($tenant, $user);

        $this->get(route('filament.app.resources.products.edit', ['tenant' => $tenant->slug, 'record' => $product], false))
            ->assertOk()
            ->assertSee('Nombre del Producto')
            ->assertSee('Cafe latte QA');

        $this->get(route('filament.app.resources.leads.edit', ['tenant' => $tenant->slug, 'record' => $lead], false))
            ->assertOk()
            ->assertSee('Nombre')
            ->assertSee('Ana Castillo');

        $this->get(route('filament.app.resources.orders.view', ['tenant' => $tenant->slug, 'record' => $order], false))
            ->assertOk()
            ->assertSee('Cliente')
            ->assertSee('Ticket de orden')
            ->assertSee('#QA1001');

        $customerProfile = CustomerProfile::query()->firstOrFail();

        $this->get(route('filament.app.resources.customer-profiles.edit', ['tenant' => $tenant->slug, 'record' => $customerProfile], false))
            ->assertOk()
            ->assertSee('Operacion comercial')
            ->assertSee('Historial de pedidos')
            ->assertSee('Historial de prospectos')
            ->assertSee('Historial de contactos')
            ->assertSee('Notas del cliente');

        $this->get(route('filament.app.resources.campaigns.edit', ['tenant' => $tenant->slug, 'record' => $campaign], false))
            ->assertOk()
            ->assertSee('Detalles de la campaña')
            ->assertSee('Campana QA');
    }

    protected function tearDown(): void
    {
        Filament::setTenant(null, isQuiet: true);
        Filament::setCurrentPanel(null);

        parent::tearDown();
    }

    /**
     * @return array{0: User, 1: Tenant, 2: City, 3: Category}
     */
    private function makeTenantOwner(bool $isPro, string $slug): array
    {
        $user = User::factory()->create([
            'role' => UserRole::TENANT_OWNER,
        ]);

        $city = City::factory()->create();
        $category = Category::factory()->create();

        $tenantFactory = Tenant::factory()->state([
            'user_id' => $user->id,
            'slug' => $slug,
            'business_type' => 'retail',
            'city_id' => $city->getKey(),
            'category_id' => $category->getKey(),
        ]);

        if ($isPro) {
            $tenantFactory = $tenantFactory->pro();
        }

        $tenant = $tenantFactory->create();

        $user->forceFill(['tenant_id' => $tenant->id])->save();

        return [$user, $tenant, $city, $category];
    }

    private function setAppPanelTenantContext(Tenant $tenant, User $user): void
    {
        $this->actingAs($user);
        Filament::setCurrentPanel('app');
        Filament::setTenant($tenant, isQuiet: true);
        $this->actingAsTenant((string) $tenant->getKey());
    }
}
