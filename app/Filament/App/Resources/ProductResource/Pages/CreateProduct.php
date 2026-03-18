<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\ProductResource\Pages;

use App\Filament\App\Resources\ProductResource;
use App\Models\Tenant;
use App\Settings\GeneralSettings;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    protected function beforeCreate(): void
    {
        $tenant = $this->getTenantRecord();

        if (! $tenant instanceof Tenant) {
            return;
        }

        if ($tenant->getIsProAttribute()) {
            return;
        }

        $settings = app(GeneralSettings::class);
        $limit = is_numeric($tenant->getAttribute('product_limit'))
            ? (int) $tenant->getAttribute('product_limit')
            : (int) ($settings->free_plan_product_limit ?? 10);

        $currentCount = $tenant->products()->count();

        if ($currentCount >= $limit) {
            Notification::make()
                ->danger()
                ->title('Limite del Plan Alcanzado')
                ->body("Has alcanzado el limite de {$limit} productos de tu plan. Actualiza a PRO para productos ilimitados.")
                ->persistent()
                ->actions([
                    Action::make('upgrade')
                        ->label('Ver Plan PRO')
                        ->url(route('filament.app.pages.manage-billing', [
                            'tenant' => (string) ($tenant->getAttribute('slug') ?? ''),
                        ]))
                        ->color('warning'),
                ])
                ->send();

            $this->halt();
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $tenant = $this->getTenantRecord();

        $data['tenant_id'] = $tenant?->getKey();
        $data['city_id'] = $tenant?->getAttribute('city_id');

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    private function getTenantRecord(): ?Tenant
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Tenant ? $tenant : null;
    }
}
