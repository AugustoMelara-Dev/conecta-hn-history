<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\ProductResource\Pages;

use App\Application\Products\MerchantProductCatalogAdapter;
use App\Filament\App\Resources\ProductResource;
use App\Models\Product;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    public string $createAttemptId;

    public function mount(): void
    {
        parent::mount();
        $this->createAttemptId = (string) Str::uuid();
    }

    /** @param array<string, mixed> $data */
    protected function handleRecordCreation(array $data): Product
    {
        return app(MerchantProductCatalogAdapter::class)->create(
            ProductResource::trustedTenant(),
            $data,
            $this->createAttemptId,
        );
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
