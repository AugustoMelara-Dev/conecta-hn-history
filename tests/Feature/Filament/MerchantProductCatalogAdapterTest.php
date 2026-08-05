<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Application\Products\CanonicalProductWriter;
use App\Application\Products\MerchantProductCatalogAdapter;
use App\Application\Products\ProductImageStorageInterface;
use App\Application\Products\ProductMediaCleanupQueue;
use App\Infrastructure\Security\SafeCatalogImageNormalizer;
use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\Support\GlobalMarketFixture;
use Tests\TestCase;

final class MerchantProductCatalogAdapterTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_uses_the_trusted_tenant_exact_market_minor_units_and_replays_a_livewire_attempt(): void
    {
        $tenant = $this->marketTenant();
        $adapter = app(MerchantProductCatalogAdapter::class);

        $first = $adapter->create($tenant, [
            'tenant_id' => $this->marketTenant()->getKey(),
            'name' => 'Café filtrado',
            'price' => '123.45',
            'stock_quantity' => '4',
            'is_visible' => true,
        ], 'f9dbf0f1-707b-4fb7-898d-4bbc8bbf5d9c');
        $replay = $adapter->create($tenant, [
            'name' => 'Café filtrado',
            'price' => '123.45',
            'stock_quantity' => '4',
            'is_visible' => true,
        ], 'f9dbf0f1-707b-4fb7-898d-4bbc8bbf5d9c');

        self::assertSame($first->getKey(), $replay->getKey());
        self::assertSame((string) $tenant->getKey(), (string) $first->tenant_id);
        self::assertSame('123.45', (string) $first->getRawOriginal('price'));
        self::assertSame(4, (int) $first->stock_quantity);
        self::assertSame(1, DB::table('products')->where('tenant_id', $tenant->getKey())->count());
        self::assertSame(1, DB::table('inventory_movements')->where('product_id', $first->getKey())->count());
    }

    public function test_edit_is_tenant_scoped_and_metadata_price_visibility_and_stock_commit_together(): void
    {
        $owner = $this->marketTenant();
        $other = $this->marketTenant();
        $adapter = app(MerchantProductCatalogAdapter::class);
        $product = $adapter->create($owner, ['name' => 'Original', 'price' => '10.00'], '0d2e593a-f254-48b7-8f86-c3e2d2cc24f8');

        $updated = $adapter->update($owner, $product, [
            'name' => 'Actualizado',
            'price' => '10.01',
            'is_visible' => true,
            'is_featured' => true,
            'is_impulse_buy' => true,
            'stock_quantity' => 3,
        ], 'catalog-edit-1');

        self::assertSame('Actualizado', $updated->name);
        self::assertSame('10.01', (string) $updated->getRawOriginal('price'));
        self::assertTrue((bool) $updated->is_visible);
        self::assertTrue((bool) $updated->is_featured);
        self::assertTrue((bool) $updated->is_impulse_buy);
        self::assertSame(3, (int) $updated->stock_quantity);

        $this->expectException(ModelNotFoundException::class);
        $adapter->update($other, $product, ['name' => 'No autorizado'], 'catalog-edit-cross-tenant');
    }

    public function test_edit_rolls_back_metadata_when_the_stock_payload_fails(): void
    {
        $tenant = $this->marketTenant();
        $adapter = app(MerchantProductCatalogAdapter::class);
        $product = $adapter->create($tenant, ['name' => 'Original', 'price' => '10.00'], 'd97b8e53-09ee-4ce7-b311-4f424779d93e');

        try {
            $adapter->update($tenant, $product, [
                'name' => 'No debe persistir',
                'price' => '11.00',
                'is_visible' => true,
                'stock_quantity' => -1,
            ], 'catalog-edit-rollback');
            self::fail('Invalid stock must abort the complete catalog edit.');
        } catch (ValidationException) {
            $fresh = Product::withoutGlobalScopes()->findOrFail($product->getKey());
            self::assertSame('Original', $fresh->name);
            self::assertSame('10.00', (string) $fresh->price);
            self::assertFalse((bool) $fresh->is_visible);
            self::assertSame(0, (int) $fresh->stock_quantity);
        }
    }

    public function test_decimal_parser_rejects_binary_float_input(): void
    {
        $tenant = $this->marketTenant();

        $this->expectException(ValidationException::class);
        app(MerchantProductCatalogAdapter::class)->create(
            $tenant,
            ['name' => 'Precio inseguro', 'price' => 10.01],
            '8175e0f3-a2fc-4cc2-af9a-8b41daf850d2',
        );
    }

    public function test_decimal_parser_rejects_values_outside_decimal_ten_two(): void
    {
        $tenant = $this->marketTenant();

        $this->expectException(ValidationException::class);
        app(MerchantProductCatalogAdapter::class)->create(
            $tenant,
            ['name' => 'Precio fuera de rango', 'price' => '999999999.99'],
            'fe0efdfb-3768-4427-b03a-5dde6ef83eec',
        );
    }

    public function test_manual_media_is_rejected_at_the_application_boundary(): void
    {
        $tenant = $this->marketTenant();

        $this->expectException(ValidationException::class);
        app(MerchantProductCatalogAdapter::class)->create($tenant, [
            'name' => 'Imagen hostil',
            'price' => '1.00',
            'image_path' => 'products/untrusted.jpg',
        ], '82890092-25fe-4139-9a25-49ac0a815858');
    }

    public function test_private_staged_image_is_normalized_published_and_removed_on_create(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $tenant = $this->marketTenant();
        $stagedPath = "manual-product-staging/{$tenant->getKey()}/source.png";
        Storage::disk('local')->put($stagedPath, $this->imageWithAppendedPayload());

        $product = app(MerchantProductCatalogAdapter::class)->create($tenant, [
            'name' => 'Imagen segura',
            'price' => '10.00',
            'pending_image_path' => $stagedPath,
        ], '2d748b61-7e8a-4f95-b772-600c7a8d7ab2');

        self::assertIsString($product->image_path);
        self::assertStringStartsWith("tenants/{$tenant->getKey()}/products/{$product->getKey()}/", $product->image_path);
        Storage::disk('public')->assertExists($product->image_path);
        Storage::disk('local')->assertMissing($stagedPath);
        self::assertStringNotContainsString('APPENDED-HOSTILE-PAYLOAD', Storage::disk('public')->get($product->image_path));

        $replay = app(MerchantProductCatalogAdapter::class)->create($tenant, [
            'name' => 'Imagen segura',
            'price' => '10.00',
            'pending_image_path' => $stagedPath,
        ], '2d748b61-7e8a-4f95-b772-600c7a8d7ab2');

        self::assertSame($product->getKey(), $replay->getKey());
        self::assertSame($product->image_path, $replay->image_path);
        self::assertCount(1, Storage::disk('public')->allFiles());
        self::assertSame(1, DB::table('product_media_operations')->count());
    }

    public function test_media_edit_replay_does_not_reapply_stale_metadata_over_a_later_edit(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $tenant = $this->marketTenant();
        $adapter = app(MerchantProductCatalogAdapter::class);
        $product = $adapter->create(
            $tenant,
            ['name' => 'Inicial', 'price' => '10.00'],
            '12c05349-f4ba-4b86-b331-43df9e50393b',
        );
        $stagedPath = "manual-product-staging/{$tenant->getKey()}/replay-edit.png";
        Storage::disk('local')->put($stagedPath, $this->imageWithAppendedPayload());
        $attemptPayload = [
            'name' => 'Nombre del intento K',
            'pending_image_path' => $stagedPath,
        ];

        $first = $adapter->update($tenant, $product, $attemptPayload, 'media-edit-k');
        $later = $adapter->update($tenant, $first, ['name' => 'Nombre posterior']);
        $replay = $adapter->update($tenant, $later, $attemptPayload, 'media-edit-k');

        self::assertSame('Nombre posterior', $replay->name);
        self::assertSame($first->image_path, $replay->image_path);
        self::assertCount(1, Storage::disk('public')->allFiles());
        self::assertSame(1, DB::table('product_media_operations')->where('product_id', $product->getKey())->count());
        self::assertSame(0, DB::table('product_media_cleanup_tasks')->count());
    }

    public function test_oversized_staging_is_rejected_at_the_storage_boundary_and_cleaned(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $tenant = $this->marketTenant();
        $stagedPath = "manual-product-staging/{$tenant->getKey()}/oversized.png";
        Storage::disk('local')->put(
            $stagedPath,
            $this->imageWithAppendedPayload().str_repeat('X', SafeCatalogImageNormalizer::MAX_INPUT_BYTES),
        );

        try {
            app(MerchantProductCatalogAdapter::class)->create($tenant, [
                'name' => 'Imagen demasiado grande',
                'price' => '10.00',
                'pending_image_path' => $stagedPath,
            ], 'ab5a16c8-b176-45a6-afca-873aa8fa1073');
            self::fail('The application boundary must reject oversized staged objects.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('pending_image_path', $exception->errors());
            Storage::disk('local')->assertMissing($stagedPath);
            self::assertSame([], Storage::disk('public')->allFiles());
        }
    }

    public function test_published_delete_rejects_traversal_and_preserves_the_other_tenant_object(): void
    {
        Storage::fake('public');
        $owner = $this->marketTenant();
        $attacker = $this->marketTenant();
        $adapter = app(MerchantProductCatalogAdapter::class);
        $ownerProduct = $adapter->create($owner, ['name' => 'Owner', 'price' => '1.00'], '7d74b468-c39d-4f2c-b094-a377f1dd06f1');
        $attackerProduct = $adapter->create($attacker, ['name' => 'Attacker', 'price' => '1.00'], '1e43caba-c7e5-46a0-b072-1e5587d6c357');
        $foreignPath = "tenants/{$owner->getKey()}/products/{$ownerProduct->getKey()}/22222222-2222-4222-8222-222222222222.png";
        Storage::disk('public')->put($foreignPath, 'foreign-object');
        $traversal = "tenants/{$attacker->getKey()}/products/{$attackerProduct->getKey()}/../../../../tenants/{$owner->getKey()}/products/{$ownerProduct->getKey()}/22222222-2222-4222-8222-222222222222.png";

        try {
            app(ProductImageStorageInterface::class)->discardPublished(
                (string) $attacker->getKey(),
                (int) $attackerProduct->getKey(),
                $traversal,
            );
            self::fail('A non-canonical published path must fail closed.');
        } catch (\InvalidArgumentException) {
            Storage::disk('public')->assertExists($foreignPath);
        }
    }

    public function test_cross_tenant_staging_path_is_rejected_without_deleting_the_owners_file(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $owner = $this->marketTenant();
        $attacker = $this->marketTenant();
        $stagedPath = "manual-product-staging/{$owner->getKey()}/source.png";
        Storage::disk('local')->put($stagedPath, $this->imageWithAppendedPayload());

        try {
            app(MerchantProductCatalogAdapter::class)->create($attacker, [
                'name' => 'Ataque',
                'price' => '1.00',
                'pending_image_path' => $stagedPath,
            ], '310840c4-d8f9-4af0-aa61-a0eeffabb331');
            self::fail('A tenant must not consume another tenant staging object.');
        } catch (ValidationException) {
            Storage::disk('local')->assertExists($stagedPath);
            self::assertSame(0, Product::withoutGlobalScopes()->where('tenant_id', $attacker->getKey())->count());
        }
    }

    public function test_replacing_an_image_deletes_the_previous_owned_file_only_after_success(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $tenant = $this->marketTenant();
        $adapter = app(MerchantProductCatalogAdapter::class);
        $product = $adapter->create($tenant, ['name' => 'Original', 'price' => '10.00'], '9a475dd4-f900-48bb-a6f0-fab558565303');
        $oldPath = "tenants/{$tenant->getKey()}/products/{$product->getKey()}/11111111-1111-4111-8111-111111111111.png";
        Storage::disk('public')->put($oldPath, 'old-image');
        $product = app(CanonicalProductWriter::class)->update(
            (string) $tenant->getKey(),
            (int) $product->getKey(),
            ['image_path' => $oldPath],
        );
        $stagedPath = "manual-product-staging/{$tenant->getKey()}/replacement.png";
        Storage::disk('local')->put($stagedPath, $this->imageWithAppendedPayload());

        $updated = $adapter->update($tenant, $product, [
            'pending_image_path' => $stagedPath,
        ], 'replace-image-1');

        self::assertNotSame($oldPath, $updated->image_path);
        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($updated->image_path);
        Storage::disk('local')->assertMissing($stagedPath);
    }

    public function test_failed_image_database_mutation_compensates_public_and_staging_files(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $tenant = $this->marketTenant();
        $stagedPath = "manual-product-staging/{$tenant->getKey()}/failure.png";
        Storage::disk('local')->put($stagedPath, $this->imageWithAppendedPayload());
        Product::updating(static function (Product $product): void {
            if ($product->isDirty('image_path')) {
                throw new RuntimeException('forced image database failure');
            }
        });

        try {
            app(MerchantProductCatalogAdapter::class)->create($tenant, [
                'name' => 'Debe revertir',
                'price' => '10.00',
                'pending_image_path' => $stagedPath,
            ], '341722a7-5bcf-4e96-9071-25132bff2c67');
            self::fail('The forced image database failure must escape.');
        } catch (RuntimeException $exception) {
            self::assertSame('forced image database failure', $exception->getMessage());
            Storage::disk('local')->assertMissing($stagedPath);
            self::assertSame([], Storage::disk('public')->allFiles());
            self::assertSame(0, Product::withoutGlobalScopes()->where('tenant_id', $tenant->getKey())->count());
        }
    }

    public function test_invalid_image_bytes_roll_back_product_and_clean_staging(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $tenant = $this->marketTenant();
        $stagedPath = "manual-product-staging/{$tenant->getKey()}/not-an-image.jpg";
        Storage::disk('local')->put($stagedPath, 'not-an-image');

        try {
            app(MerchantProductCatalogAdapter::class)->create($tenant, [
                'name' => 'Payload inválido',
                'price' => '2.00',
                'pending_image_path' => $stagedPath,
            ], 'c194e563-9b45-49a8-8392-62bd3fc655b0');
            self::fail('Undecodable bytes must fail closed.');
        } catch (ValidationException) {
            Storage::disk('local')->assertMissing($stagedPath);
            self::assertSame([], Storage::disk('public')->allFiles());
            self::assertSame(0, Product::withoutGlobalScopes()->where('tenant_id', $tenant->getKey())->count());
        }
    }

    public function test_multiple_staged_files_are_rejected_before_any_catalog_write(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $tenant = $this->marketTenant();
        $first = "manual-product-staging/{$tenant->getKey()}/first.png";
        $second = "manual-product-staging/{$tenant->getKey()}/second.png";
        Storage::disk('local')->put($first, $this->imageWithAppendedPayload());
        Storage::disk('local')->put($second, $this->imageWithAppendedPayload());

        try {
            app(MerchantProductCatalogAdapter::class)->create($tenant, [
                'name' => 'Galería no canónica',
                'price' => '2.00',
                'pending_image_path' => [$first, $second],
            ], '9e833fd9-05e2-456b-993f-841c2f58af7e');
            self::fail('The primary-image boundary accepts exactly one staged file.');
        } catch (ValidationException) {
            Storage::disk('local')->assertExists($first);
            Storage::disk('local')->assertExists($second);
            self::assertSame(0, Product::withoutGlobalScopes()->where('tenant_id', $tenant->getKey())->count());
        }
    }

    public function test_catalog_validation_failure_cleans_an_owned_staging_file_before_any_write(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $tenant = $this->marketTenant();
        $stagedPath = "manual-product-staging/{$tenant->getKey()}/invalid-price.png";
        Storage::disk('local')->put($stagedPath, $this->imageWithAppendedPayload());

        try {
            app(MerchantProductCatalogAdapter::class)->create($tenant, [
                'name' => 'Precio inválido',
                'price' => 10.01,
                'pending_image_path' => $stagedPath,
            ], '00bc1ca9-011d-480c-a86f-097b08aa5194');
            self::fail('Invalid catalog data must abort the staged write.');
        } catch (ValidationException) {
            Storage::disk('local')->assertMissing($stagedPath);
            self::assertSame([], Storage::disk('public')->allFiles());
            self::assertSame(0, Product::withoutGlobalScopes()->where('tenant_id', $tenant->getKey())->count());
        }
    }

    public function test_remove_image_is_explicit_idempotent_and_uses_durable_cleanup(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $tenant = $this->marketTenant();
        $adapter = app(MerchantProductCatalogAdapter::class);
        $stagedPath = "manual-product-staging/{$tenant->getKey()}/remove-source.png";
        Storage::disk('local')->put($stagedPath, $this->imageWithAppendedPayload());
        $product = $adapter->create($tenant, [
            'name' => 'Con imagen',
            'price' => '5.00',
            'pending_image_path' => $stagedPath,
        ], '6ed61d47-b8d4-4c64-a6e4-c9affaaab62d');
        $publishedPath = (string) $product->image_path;

        $removed = $adapter->update($tenant, $product, ['remove_image' => true], 'remove-image-attempt');
        $replay = $adapter->update($tenant, $removed, ['remove_image' => true], 'remove-image-attempt');

        self::assertNull($removed->image_path);
        self::assertNull($replay->image_path);
        Storage::disk('public')->assertMissing($publishedPath);
        self::assertSame(2, DB::table('product_media_operations')->count());
        self::assertSame(0, DB::table('product_media_cleanup_tasks')->count());
    }

    public function test_durable_cleanup_command_reconciles_a_canonical_public_object(): void
    {
        Storage::fake('public');
        $tenant = $this->marketTenant();
        $product = app(MerchantProductCatalogAdapter::class)->create(
            $tenant,
            ['name' => 'Reconciliable', 'price' => '1.00'],
            'ce0e666e-54ed-4bd2-a8de-05b839ce183a',
        );
        $path = "tenants/{$tenant->getKey()}/products/{$product->getKey()}/33333333-3333-4333-8333-333333333333.webp";
        Storage::disk('public')->put($path, 'orphan');
        app(ProductMediaCleanupQueue::class)->enqueue(
            (string) $tenant->getKey(),
            'public',
            $path,
            (int) $product->getKey(),
        );

        self::assertSame(1, DB::table('product_media_cleanup_tasks')->count());
        $this->artisan('storage:process-product-media-cleanup')->assertSuccessful();

        Storage::disk('public')->assertMissing($path);
        self::assertSame(0, DB::table('product_media_cleanup_tasks')->count());
    }

    public function test_soft_delete_retains_media_and_force_delete_schedules_retention_cleanup(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $tenant = $this->marketTenant();
        $stage = "manual-product-staging/{$tenant->getKey()}/retention.png";
        Storage::disk('local')->put($stage, $this->imageWithAppendedPayload());
        $product = app(MerchantProductCatalogAdapter::class)->create($tenant, [
            'name' => 'Restaurable',
            'price' => '4.00',
            'pending_image_path' => $stage,
        ], '8f5f60ac-3988-4b11-b40a-3b4e0f74bb82');
        $path = (string) $product->image_path;

        app(MerchantProductCatalogAdapter::class)->delete($tenant, $product);
        Storage::disk('public')->assertExists($path);

        Product::withoutGlobalScopes()->withTrashed()->findOrFail($product->getKey())->forceDelete();
        self::assertSame(1, DB::table('product_media_cleanup_tasks')->count());
        $this->artisan('storage:process-product-media-cleanup')->assertSuccessful();

        Storage::disk('public')->assertMissing($path);
        self::assertSame(0, DB::table('product_media_cleanup_tasks')->count());
    }

    public function test_inline_section_creation_ignores_submitted_authority_and_sets_server_fields(): void
    {
        $owner = $this->marketTenant();
        $foreign = $this->marketTenant();

        $section = app(MerchantProductCatalogAdapter::class)->createSection($owner, [
            'name' => 'Postres',
            'tenant_id' => $foreign->getKey(),
            'is_active' => false,
            'sort_order' => 999,
        ]);

        self::assertSame((string) $owner->getKey(), (string) $section->tenant_id);
        self::assertTrue((bool) $section->is_active);
        self::assertSame(1, (int) $section->sort_order);
    }

    public function test_inline_section_creation_enforces_the_canonical_plan_limit_under_lock(): void
    {
        $tenant = $this->marketTenant(['is_pro' => false]);
        $adapter = app(MerchantProductCatalogAdapter::class);
        foreach (['Uno', 'Dos', 'Tres'] as $name) {
            $adapter->createSection($tenant, ['name' => $name]);
        }

        $this->expectException(ValidationException::class);
        $adapter->createSection($tenant, ['name' => 'Cuatro']);
    }

    public function test_clone_section_assignment_and_delete_remain_tenant_scoped_canonical_operations(): void
    {
        $tenant = $this->marketTenant();
        $adapter = app(MerchantProductCatalogAdapter::class);
        $product = $adapter->create($tenant, ['name' => 'Taza', 'price' => '5.00'], '4aa6d770-4f80-4437-b08b-a30a645d2a1d');
        $sectionId = DB::table('menu_sections')->insertGetId([
            'tenant_id' => $tenant->getKey(), 'name' => 'Bebidas', 'is_active' => true,
            'sort_order' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $copy = $adapter->clone($tenant, $product, 'ca49b083-f85f-4c2c-a036-4163e8cd3da6');
        $adapter->assignSection($tenant, [$product, $copy], $sectionId);
        $copyAfterSection = Product::withoutGlobalScopes()->findOrFail($copy->getKey());
        $adapter->delete($tenant, $copy);

        self::assertNotSame($product->getKey(), $copy->getKey());
        self::assertFalse((bool) $copy->is_visible);
        self::assertSame($sectionId, (int) $product->fresh()->menu_section_id);
        self::assertSame($sectionId, (int) $copyAfterSection->menu_section_id);
        self::assertNotNull(Product::withoutGlobalScopes()->withTrashed()->findOrFail($copy->getKey())->deleted_at);
    }

    public function test_bulk_delete_rolls_back_when_any_record_is_outside_the_trusted_tenant(): void
    {
        $owner = $this->marketTenant();
        $other = $this->marketTenant();
        $adapter = app(MerchantProductCatalogAdapter::class);
        $owned = $adapter->create($owner, ['name' => 'Propio', 'price' => '1.00'], 'a41c2a53-a5bf-4a11-8be6-91f46bb1f3e9');
        $foreign = $adapter->create($other, ['name' => 'Ajeno', 'price' => '1.00'], '00ef774f-4250-452e-a89a-c5c1f94daf65');

        try {
            $adapter->deleteMany($owner, [$owned, $foreign]);
            self::fail('A cross-tenant bulk delete must fail atomically.');
        } catch (ModelNotFoundException) {
            self::assertNull(Product::withoutGlobalScopes()->withTrashed()->findOrFail($owned->getKey())->deleted_at);
            self::assertNull(Product::withoutGlobalScopes()->withTrashed()->findOrFail($foreign->getKey())->deleted_at);
        }
    }

    /** @param array<string, mixed> $attributes */
    private function marketTenant(array $attributes = []): Tenant
    {
        $tenant = Tenant::factory()->create($attributes);
        GlobalMarketFixture::assignCanada($tenant);

        return $tenant;
    }

    private function imageWithAppendedPayload(): string
    {
        $image = imagecreatetruecolor(4, 4);
        ob_start();
        imagepng($image);
        $bytes = ob_get_clean();
        imagedestroy($image);

        self::assertIsString($bytes);

        return $bytes.'APPENDED-HOSTILE-PAYLOAD';
    }
}
