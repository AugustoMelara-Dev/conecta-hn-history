<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Application\Products\MerchantProductCatalogAdapter;
use App\Domain\Access\Enums\Role;
use App\Domain\Shared\Services\TenantContext;
use App\Filament\App\Resources\ProductResource\Pages\CreateProduct;
use App\Filament\App\Resources\ProductResource\Pages\EditProduct;
use App\Filament\App\Resources\ProductResource\Pages\ListProducts;
use App\Models\Category;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Support\GlobalMarketFixture;
use Tests\TestCase;

final class MerchantProductLivewireTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_and_edit_pages_use_the_canonical_filament_hooks(): void
    {
        [$user, $tenant] = $this->tenantUser();
        $category = Category::factory()->create(['is_active' => true]);
        $this->setPanelContext($user, $tenant);

        Livewire::test(CreateProduct::class)
            ->fillForm([
                'name' => 'Producto Livewire',
                'price' => '42.37',
                'category_id' => $category->getKey(),
                'is_visible' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $product = Product::withoutGlobalScopes()
            ->where('tenant_id', $tenant->getKey())
            ->where('name', 'Producto Livewire')
            ->firstOrFail();
        self::assertSame('42.37', (string) $product->price);

        Livewire::test(EditProduct::class, ['record' => $product->getKey()])
            ->fillForm([
                'name' => 'Producto Livewire Editado',
                'price' => '42.38',
                'category_id' => $category->getKey(),
                'is_visible' => false,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $product = Product::withoutGlobalScopes()->findOrFail($product->getKey());
        self::assertSame('Producto Livewire Editado', $product->name);
        self::assertSame('42.38', (string) $product->price);
        self::assertFalse((bool) $product->is_visible);
    }

    public function test_clone_retry_and_both_delete_actions_keep_filament_lifecycle(): void
    {
        [$user, $tenant] = $this->tenantUser();
        $this->setPanelContext($user, $tenant);
        $source = app(MerchantProductCatalogAdapter::class)->create(
            $tenant,
            ['name' => 'Original', 'price' => '10.00'],
            (string) Str::uuid(),
        );
        $attemptId = (string) Str::uuid();

        $list = Livewire::test(ListProducts::class)
            ->callTableAction('clone', $source, ['attempt_id' => $attemptId])
            ->assertNotified();
        $list->callTableAction('clone', $source, ['attempt_id' => $attemptId]);

        self::assertSame(2, Product::withoutGlobalScopes()->where('tenant_id', $tenant->getKey())->count());
        $copy = Product::withoutGlobalScopes()->where('tenant_id', $tenant->getKey())->whereKeyNot($source->getKey())->firstOrFail();
        $list->callTableAction('delete', $copy)->assertNotified();
        self::assertNotNull(Product::withoutGlobalScopes()->withTrashed()->findOrFail($copy->getKey())->deleted_at);

        Livewire::test(EditProduct::class, ['record' => $source->getKey()])
            ->callAction('delete')
            ->assertNotified()
            ->assertRedirect();
        self::assertNotNull(Product::withoutGlobalScopes()->withTrashed()->findOrFail($source->getKey())->deleted_at);
    }

    public function test_inline_price_visibility_and_featured_writes_are_canonical_and_tenant_scoped(): void
    {
        [$user, $tenant] = $this->tenantUser();
        $this->setPanelContext($user, $tenant);
        $adapter = app(MerchantProductCatalogAdapter::class);
        $product = $adapter->create(
            $tenant,
            ['name' => 'Edicion inline', 'price' => '10.00', 'is_visible' => true],
            (string) Str::uuid(),
        );

        $list = Livewire::test(ListProducts::class);
        $list->call('updateTableColumnState', 'price', (string) $product->getKey(), '19.99');
        $list->call('updateTableColumnState', 'is_visible', (string) $product->getKey(), false);
        $list->call('updateTableColumnState', 'is_featured', (string) $product->getKey(), true);

        $product = Product::withoutGlobalScopes()->findOrFail($product->getKey());
        self::assertSame('19.99', (string) $product->price);
        self::assertFalse((bool) $product->is_visible);
        self::assertTrue((bool) $product->is_featured);

        [, $otherTenant] = $this->tenantUser();
        app(TenantContext::class)->reset();
        app(TenantContext::class)->setTenant((string) $otherTenant->getKey());
        $foreignProduct = $adapter->create(
            $otherTenant,
            ['name' => 'Producto ajeno', 'price' => '33.00', 'is_visible' => true],
            (string) Str::uuid(),
        );
        app(TenantContext::class)->reset();
        app(TenantContext::class)->setTenant((string) $tenant->getKey());

        $list->call('updateTableColumnState', 'price', (string) $foreignProduct->getKey(), '1.00');
        $list->call('updateTableColumnState', 'is_visible', (string) $foreignProduct->getKey(), false);
        $list->call('updateTableColumnState', 'is_featured', (string) $foreignProduct->getKey(), true);

        $foreignProduct = Product::withoutGlobalScopes()->findOrFail($foreignProduct->getKey());
        self::assertSame('33.00', (string) $foreignProduct->price);
        self::assertTrue((bool) $foreignProduct->is_visible);
        self::assertFalse((bool) $foreignProduct->is_featured);
    }

    protected function tearDown(): void
    {
        app(TenantContext::class)->reset();
        Filament::setTenant(null, isQuiet: true);
        Filament::setCurrentPanel(null);
        parent::tearDown();
    }

    /** @return array{User, Tenant} */
    private function tenantUser(): array
    {
        $user = User::factory()->tenantOwner()->create();
        $tenant = Tenant::factory()->forUser($user)->create([
            'plan_type' => 'pro',
            'subscription_status' => 'active',
            'is_pro' => true,
            'product_limit' => 100,
            'business_type' => 'retail',
        ]);
        $tenant->memberships()->create([
            'user_id' => $user->getKey(),
            'role' => Role::OWNER,
            'accepted_at' => now(),
        ]);
        $user->forceFill(['tenant_id' => (string) $tenant->getKey()])->save();
        GlobalMarketFixture::assignCanada($tenant);

        return [$user->refresh(), $tenant];
    }

    private function setPanelContext(User $user, Tenant $tenant): void
    {
        $this->actingAs($user);
        app(TenantContext::class)->setTenant((string) $tenant->getKey());
        Filament::setCurrentPanel(Filament::getPanel('app'));
        Filament::setTenant($tenant, isQuiet: true);
    }
}
