<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\ProductResource\Pages;

use App\Application\Products\MerchantProductCatalogAdapter;
use App\Filament\App\Resources\ProductResource;
use App\Models\Product;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Str;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    public string $editAttemptId;

    public function mount(int|string $record): void
    {
        parent::mount($record);
        $this->editAttemptId = (string) Str::uuid();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->using(function (Product $record): bool {
                    app(MerchantProductCatalogAdapter::class)->delete(ProductResource::trustedTenant(), $record);

                    return true;
                }),
        ];
    }

    /** @param array<string, mixed> $data */
    protected function handleRecordUpdate(\Illuminate\Database\Eloquent\Model $record, array $data): Product
    {
        if (! $record instanceof Product) {
            throw new \LogicException('The merchant product editor received an invalid record.');
        }

        $updated = app(MerchantProductCatalogAdapter::class)->update(
            ProductResource::trustedTenant(),
            $record,
            $data,
            'filament-edit:'.$this->editAttemptId,
        );
        $this->editAttemptId = (string) Str::uuid();

        return $updated;
    }
}
