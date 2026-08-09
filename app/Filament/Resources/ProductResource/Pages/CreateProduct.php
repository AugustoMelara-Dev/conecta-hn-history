<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use Filament\Facades\Filament;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $tenant = Filament::getTenant();

        // 🛑 GUILLOTINE LOGIC: Hard Stop for Plan Limits
        if ($tenant) {
            // Get effective limit (default 12 for semilla)
            $limit = $tenant->product_limit ?? 12;

            // Note: products() is already scoped by TenantScope if active,
            // but relying on relationship provides cleaner syntax here.
            $currentCount = $tenant->products()->count();

            if ($tenant->plan_type === 'semilla' && $currentCount >= $limit) {

                Notification::make()
                    ->danger()
                    ->title('🛑 Límite de Plan Semilla Alcanzado')
                    ->body("Has llegado al límite de {$limit} productos. ¡Actualiza a Plan PRO para inventario ilimitado!")
                    ->actions([
                        Action::make('upgrade')
                            ->button()
                            ->label('Actualizar Plan')
                            ->url('/admin/billing'), // Assuming this route exists
                    ])
                    ->persistent()
                    ->send();

                // ✋ HALT EXECUTION - The "Guillotine"
                $this->halt();
            }
        }

        // Automatic Tenant Assignment (Safety Net, although Trait handles it)
        $data['tenant_id'] = $tenant->id;

        return $data;
    }
}
