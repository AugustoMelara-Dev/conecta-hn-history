<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use App\Models\Tenant;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $tenant = Filament::getTenant();

            if ($tenant instanceof Tenant) {
                $limit = (int) ($tenant->getAttribute('product_limit') ?? 12);
                $planType = (string) $tenant->getRawOriginal('plan_type');
                $currentCount = $tenant->products()->count();

            if ($planType === 'semilla' && $currentCount >= $limit) {
                Notification::make()
                    ->danger()
                    ->title('Limite de Plan Semilla Alcanzado')
                    ->body("Has llegado al limite de {$limit} productos. Actualiza a Plan PRO para inventario ilimitado.")
                    ->actions([
                        Action::make('upgrade')
                            ->button()
                            ->label('Actualizar Plan')
                            ->url(route('filament.admin.pages.billing')),
                    ])
                    ->persistent()
                    ->send();

                $this->halt();
            }
        }

        return $data;
    }
}
