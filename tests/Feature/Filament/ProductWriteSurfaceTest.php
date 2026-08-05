<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Filament\App\Resources\ProductResource as MerchantProductResource;
use App\Filament\Resources\ProductResource as AdminProductResource;
use App\Models\Product;
use Tests\TestCase;

final class ProductWriteSurfaceTest extends TestCase
{
    public function test_platform_admin_product_resource_is_read_only(): void
    {
        self::assertSame(['index'], array_keys(AdminProductResource::getPages()));
        self::assertFalse(AdminProductResource::canCreate());
        self::assertFalse(AdminProductResource::canEdit(new Product));
        self::assertFalse(AdminProductResource::canDelete(new Product));
        self::assertFalse(AdminProductResource::canDeleteAny());
    }

    public function test_merchant_media_uploads_use_private_tenant_staging_and_canonical_dehydration(): void
    {
        self::assertTrue(MerchantProductResource::merchantMediaWritesEnabled());

        $source = (string) file_get_contents(app_path('Filament/App/Resources/ProductResource.php'));
        self::assertStringContainsString("FileUpload::make('pending_image_path')", $source);
        self::assertStringContainsString("->disk('local')", $source);
        self::assertStringContainsString("'manual-product-staging/'.static::trustedTenant()->getKey()", $source);
        self::assertStringContainsString("->visibility('private')", $source);
        self::assertStringContainsString("Placeholder::make('current_image_preview')", $source);
        self::assertStringContainsString("Toggle::make('remove_image')", $source);
        self::assertStringContainsString('->maxSize(10240)', $source);
        self::assertStringContainsString('->maxFiles(1)', $source);
        self::assertStringNotContainsString("FileUpload::make('image_path')", $source);
        self::assertStringNotContainsString("Forms\Components\Hidden::make('tenant_id')", $source);
        self::assertStringContainsString('->createOptionUsing(', $source);
        self::assertStringNotContainsString("->prefix('L.')", $source);
        self::assertStringContainsString('TenantMarketResolver::class', $source);
    }

    public function test_orphan_cleaner_recurses_into_canonical_product_directories(): void
    {
        $source = (string) file_get_contents(app_path('Console/Commands/CleanOrphanedUploads.php'));

        self::assertStringContainsString('$disk->allFiles($productDir)', $source);
        self::assertStringNotContainsString('$disk->files($productDir)', $source);
    }

    public function test_merchant_resource_has_no_implicit_product_mutation_actions(): void
    {
        $source = (string) file_get_contents(app_path('Filament/App/Resources/ProductResource.php'));

        self::assertStringNotContainsString('ReplicateAction::make()', $source);
        self::assertStringNotContainsString('DeleteBulkAction::make()', $source);
        self::assertStringNotContainsString('$record->update(', $source);
        self::assertStringContainsString('MerchantProductCatalogAdapter::class', $source);
    }

    public function test_legacy_product_bus_and_repository_crud_are_absent(): void
    {
        $provider = (string) file_get_contents(app_path('Providers/AppServiceProvider.php'));

        foreach (['CreateProductHandler', 'UpdateProductHandler', 'DeleteProductHandler', 'ToggleProductVisibilityHandler'] as $handler) {
            self::assertStringNotContainsString($handler.'::class', $provider);
        }
        self::assertFileDoesNotExist(app_path('Domain/Product/Repositories/ProductRepositoryInterface.php'));
        self::assertFileDoesNotExist(app_path('Infrastructure/Repositories/Products/EloquentProductRepository.php'));
    }
}
