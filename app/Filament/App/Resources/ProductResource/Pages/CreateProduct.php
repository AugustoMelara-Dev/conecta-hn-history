<?php

namespace App\Filament\App\Resources\ProductResource\Pages;

use App\Filament\App\Resources\ProductResource;
use App\Settings\GeneralSettings;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    /**
     * SAFETY NET: Validación del lado del servidor
     * Previene creación de productos si se supera el límite del plan
     */
    protected function beforeCreate(): void
    {
        $tenant = Filament::getTenant();

        // PRO tiene productos ilimitados
        if ($tenant?->is_pro) {
            return;
        }

        // Obtener límite dinámico desde settings o tenant
        $settings = app(GeneralSettings::class);
        $limit = $tenant?->product_limit ?? $settings->free_plan_product_limit ?? 10;

        $currentCount = $tenant->products()->count();

        if ($currentCount >= $limit) {
            Notification::make()
                ->danger()
                ->title('Límite del Plan Alcanzado')
                ->body("Has alcanzado el límite de {$limit} productos de tu plan. Actualiza a PRO para productos ilimitados.")
                ->persistent()
                ->actions([
                    \Filament\Notifications\Actions\Action::make('upgrade')
                        ->label('Ver Plan PRO')
                        ->url(route('filament.app.pages.manage-billing', ['tenant' => $tenant->slug]))
                        ->color('warning'),
                ])
                ->send();

            $this->halt();
        }
    }

    /**
     * Automáticamente asignar tenant_id al crear
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $tenant = Filament::getTenant();

        $data['tenant_id'] = $tenant?->id;
        $data['city_id'] = $tenant?->city_id;

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

