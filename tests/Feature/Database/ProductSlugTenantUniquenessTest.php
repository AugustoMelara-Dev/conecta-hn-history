<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ProductSlugTenantUniquenessTest extends TestCase
{
    use RefreshDatabase;

    public function test_different_tenants_can_publish_the_same_product_slug(): void
    {
        $tenantA = Tenant::factory()->create(['slug' => 'negocio-a']);
        $tenantB = Tenant::factory()->create(['slug' => 'negocio-b']);

        Product::withoutEvents(fn (): Product => Product::factory()
            ->forTenant($tenantA)
            ->create(['name' => 'Café', 'slug' => 'cafe']));

        $productB = Product::withoutEvents(fn (): Product => Product::factory()
            ->forTenant($tenantB)
            ->create(['name' => 'Café', 'slug' => 'cafe']));

        self::assertSame('cafe', $productB->slug);
        self::assertSame($tenantB->id, $productB->tenant_id);
    }

    public function test_rollback_refuses_to_restore_global_uniqueness_after_cross_tenant_duplicates_exist(): void
    {
        $tenantA = Tenant::factory()->create(['slug' => 'rollback-a']);
        $tenantB = Tenant::factory()->create(['slug' => 'rollback-b']);

        foreach ([$tenantA, $tenantB] as $tenant) {
            Product::withoutEvents(fn (): Product => Product::factory()
                ->forTenant($tenant)
                ->create(['name' => 'Café', 'slug' => 'cafe']));
        }

        $migration = require database_path('migrations/2026_07_25_000003_scope_product_slugs_to_tenant.php');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('cross-tenant duplicate slugs exist');

        $migration->down();
    }

    public function test_one_tenant_cannot_publish_the_same_product_slug_twice(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'negocio-unico']);

        Product::withoutEvents(fn (): Product => Product::factory()
            ->forTenant($tenant)
            ->create(['name' => 'Café original', 'slug' => 'cafe']));

        $this->expectException(QueryException::class);

        Product::withoutEvents(fn (): Product => Product::factory()
            ->forTenant($tenant)
            ->create(['name' => 'Café duplicado', 'slug' => 'cafe']));
    }

    public function test_irreversible_slug_cutover_has_an_explicit_forward_recovery_runbook(): void
    {
        $runbook = base_path('docs/runbooks/product-slug-tenant-cutover.md');

        self::assertFileExists($runbook);
        $contents = (string) file_get_contents($runbook);
        self::assertStringContainsString('roll-forward', $contents);
        self::assertStringContainsString('migrate:rollback', $contents);
        self::assertStringContainsString('pre-deploy snapshot', $contents);
    }
}
