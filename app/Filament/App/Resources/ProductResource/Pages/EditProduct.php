<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\ProductResource\Pages;

use App\Filament\App\Resources\Pages\EditTenantRecord;
use App\Filament\App\Resources\ProductResource;
use Filament\Actions;

class EditProduct extends EditTenantRecord
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
