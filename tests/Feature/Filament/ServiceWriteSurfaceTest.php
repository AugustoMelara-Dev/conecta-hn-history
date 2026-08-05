<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use Tests\TestCase;

final class ServiceWriteSurfaceTest extends TestCase
{
    public function test_filament_service_pages_delegate_every_write_to_the_canonical_adapter(): void
    {
        $create = (string) file_get_contents(app_path('Filament/App/Resources/ServiceResource/Pages/CreateService.php'));
        $edit = (string) file_get_contents(app_path('Filament/App/Resources/ServiceResource/Pages/EditService.php'));
        $resource = (string) file_get_contents(app_path('Filament/App/Resources/ServiceResource.php'));

        self::assertStringContainsString('MerchantServiceCatalogAdapter::class', $create);
        self::assertStringContainsString('handleRecordCreation', $create);
        self::assertStringContainsString('MerchantServiceCatalogAdapter::class', $edit);
        self::assertStringContainsString('handleRecordUpdate', $edit);
        self::assertStringContainsString('mutateFormDataBeforeFill', $edit);
        self::assertStringContainsString('->using(', $edit);
        self::assertStringNotContainsString('DeleteBulkAction::make()', $resource);
        self::assertStringNotContainsString("->prefix('L.')", $resource);
    }

    public function test_filament_provider_pages_delegate_every_write_to_the_canonical_adapter(): void
    {
        $create = (string) file_get_contents(app_path('Filament/App/Resources/ServiceProviderResource/Pages/CreateServiceProvider.php'));
        $edit = (string) file_get_contents(app_path('Filament/App/Resources/ServiceProviderResource/Pages/EditServiceProvider.php'));
        $resource = (string) file_get_contents(app_path('Filament/App/Resources/ServiceProviderResource.php'));

        self::assertStringContainsString('MerchantServiceCatalogAdapter::class', $create);
        self::assertStringContainsString('handleRecordCreation', $create);
        self::assertStringContainsString('MerchantServiceCatalogAdapter::class', $edit);
        self::assertStringContainsString('handleRecordUpdate', $edit);
        self::assertStringContainsString('mutateFormDataBeforeFill', $edit);
        self::assertStringContainsString('->using(', $edit);
        self::assertStringNotContainsString('DeleteBulkAction::make()', $resource);
    }
}
