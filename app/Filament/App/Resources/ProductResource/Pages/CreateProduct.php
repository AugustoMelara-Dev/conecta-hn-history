<?php

namespace App\Filament\App\Resources\ProductResource\Pages;

use App\Filament\App\Resources\ProductResource;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    protected function beforeCreate(): void
    {
        $tenant = Filament::getTenant();

        if (! $tenant->is_pro && $tenant->products()->count() >= 10) {
            Notification::make()
                ->danger()
                ->title('Límite del Plan Gratuito alcanzado')
                ->body('Has alcanzado el límite de 10 productos. Actualiza a PRO para subir productos ilimitados.')
                ->persistent()
                ->send();

            $this->halt();
        }
    }
}
