<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Application\Catalog\Commands\CreateMenuSectionCommand;
use App\Application\Catalog\Commands\DeleteMenuSectionCommand;
use App\Application\Catalog\Commands\ReorderMenuSectionsCommand;
use App\Application\Catalog\Commands\UpdateMenuSectionCommand;
use App\Application\Catalog\Handlers\CreateMenuSectionHandler;
use App\Application\Catalog\Handlers\DeleteMenuSectionHandler;
use App\Application\Catalog\Handlers\ReorderMenuSectionsHandler;
use App\Application\Catalog\Handlers\UpdateMenuSectionHandler;
use App\Domain\Catalog\Exceptions\MenuSectionNotFoundException;
use App\Models\MenuSection;
use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

final class MenuSectionWriteIsolationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_menu_section_handlers_only_mutate_the_target_tenant(): void
    {
        $tenantA = Tenant::factory()->create([
            'name' => 'Tenant A',
        ]);
        $tenantB = Tenant::factory()->create([
            'name' => 'Tenant B',
        ]);

        $tenantASection = $this->makeMenuSection($tenantA, 'Entradas', sortOrder: 1);
        $tenantBSection = $this->makeMenuSection($tenantB, 'Catalogo B', sortOrder: 1);

        $product = Product::factory()
            ->forTenant($tenantA)
            ->create([
                'menu_section_id' => $tenantASection->getKey(),
                'name' => 'Producto tenant A',
            ]);

        $createdSection = $this->app->make(CreateMenuSectionHandler::class)->handle(
            new CreateMenuSectionCommand(
                tenantId: (string) $tenantA->getKey(),
                name: 'Promociones',
                isActive: true,
            )
        );

        $this->app->make(UpdateMenuSectionHandler::class)->handle(
            new UpdateMenuSectionCommand(
                tenantId: (string) $tenantA->getKey(),
                menuSectionId: (int) $createdSection->getKey(),
                name: 'Promociones activas',
                isActive: false,
            )
        );

        $this->app->make(ReorderMenuSectionsHandler::class)->handle(
            new ReorderMenuSectionsCommand(
                tenantId: (string) $tenantA->getKey(),
                orderedMenuSectionIds: [
                    (int) $createdSection->getKey(),
                    (int) $tenantASection->getKey(),
                ],
            )
        );

        $this->app->make(DeleteMenuSectionHandler::class)->handle(
            new DeleteMenuSectionCommand(
                tenantId: (string) $tenantA->getKey(),
                menuSectionId: (int) $tenantASection->getKey(),
            )
        );

        $createdSection->refresh();
        $tenantBSection->refresh();
        $product->refresh();

        self::assertSame((string) $tenantA->getKey(), (string) $createdSection->tenant_id);
        self::assertSame('Promociones activas', $createdSection->name);
        self::assertFalse((bool) $createdSection->is_active);
        self::assertSame(1, (int) $createdSection->sort_order);
        self::assertNull($tenantASection->fresh());
        self::assertNull($product->menu_section_id);

        self::assertSame((string) $tenantB->getKey(), (string) $tenantBSection->tenant_id);
        self::assertSame('Catalogo B', $tenantBSection->name);
        self::assertTrue((bool) $tenantBSection->is_active);
        self::assertSame(1, (int) $tenantBSection->sort_order);
    }

    public function test_menu_section_handlers_reject_cross_tenant_mutations(): void
    {
        $tenantA = Tenant::factory()->create([
            'name' => 'Tenant A',
        ]);
        $tenantB = Tenant::factory()->create([
            'name' => 'Tenant B',
        ]);

        $tenantBSection = $this->makeMenuSection($tenantB, 'Privada B', sortOrder: 1, isActive: true);

        try {
            $this->app->make(UpdateMenuSectionHandler::class)->handle(
                new UpdateMenuSectionCommand(
                    tenantId: (string) $tenantA->getKey(),
                    menuSectionId: (int) $tenantBSection->getKey(),
                    name: 'Intrusion',
                )
            );

            self::fail('Expected cross-tenant update to be rejected.');
        } catch (MenuSectionNotFoundException) {
        }

        try {
            $this->app->make(ReorderMenuSectionsHandler::class)->handle(
                new ReorderMenuSectionsCommand(
                    tenantId: (string) $tenantA->getKey(),
                    orderedMenuSectionIds: [(int) $tenantBSection->getKey()],
                )
            );

            self::fail('Expected cross-tenant reorder to be rejected.');
        } catch (MenuSectionNotFoundException) {
        }

        try {
            $this->app->make(DeleteMenuSectionHandler::class)->handle(
                new DeleteMenuSectionCommand(
                    tenantId: (string) $tenantA->getKey(),
                    menuSectionId: (int) $tenantBSection->getKey(),
                )
            );

            self::fail('Expected cross-tenant delete to be rejected.');
        } catch (MenuSectionNotFoundException) {
        }

        $tenantBSection->refresh();

        self::assertSame('Privada B', $tenantBSection->name);
        self::assertTrue((bool) $tenantBSection->is_active);
        self::assertSame(1, (int) $tenantBSection->sort_order);
    }

    private function makeMenuSection(Tenant $tenant, string $name, int $sortOrder, bool $isActive = true): MenuSection
    {
        $menuSection = new MenuSection;
        $menuSection->forceFill([
            'tenant_id' => (string) $tenant->getKey(),
            'name' => $name,
            'is_active' => $isActive,
            'sort_order' => $sortOrder,
        ]);
        $menuSection->save();

        return $menuSection->fresh() ?? $menuSection;
    }
}
