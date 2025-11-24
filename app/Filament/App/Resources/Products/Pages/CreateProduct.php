<?php

namespace App\Filament\App\Resources\Products\Pages;

use App\Filament\App\Resources\Products\ProductResource;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    protected function beforeCreate(): void
    {
        $tenant = Filament::getTenant();

        if ($tenant->is_pro) {
            return;
        }

        if ($tenant->products()->count() >= 10) {
            Notification::make()
                ->title('Límite Alcanzado')
                ->danger()
                ->body('Has alcanzado el límite de 10 productos de tu Plan Gratuito. Actualiza a Pro para ilimitados.')
                ->persistent()
                ->actions([
                    \Filament\Notifications\Actions\Action::make('upgrade')
                        ->label('Actualizar a PRO')
                        ->url('#') // Link to billing
                        ->button(),
                ])
                ->send();

            $this->halt();
        }
    }
}
