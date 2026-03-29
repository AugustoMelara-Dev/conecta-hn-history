<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\ProductResource\Pages;

use App\Filament\App\Resources\Pages\CreateTenantRecord;
use App\Filament\App\Resources\ProductResource;
use App\Infrastructure\TenantGovernance\Presentation\TenantPlanPresentationReader;
use App\Models\Tenant;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;

class CreateProduct extends CreateTenantRecord
{
    protected static string $resource = ProductResource::class;

    protected function beforeCreate(): void
    {
        $tenant = $this->getTenantRecord();

        if (! $tenant instanceof Tenant) {
            return;
        }

        $plan = app(TenantPlanPresentationReader::class)->forTenant($tenant);

        if (($plan['isCatalogUnlimited'] ?? false) === true) {
            return;
        }

        $limit = (int) ($plan['productLimit'] ?? 0);
        $currentCount = $tenant->products()->count();

        if ($currentCount >= $limit) {
            Notification::make()
                ->danger()
                ->title('Limite del catalogo alcanzado')
                ->body($plan['catalogStatusCopy'] ?? "Has alcanzado el limite de {$limit} productos de tu plan.")
                ->persistent()
                ->actions([
                    Action::make('upgrade')
                        ->label($plan['billingCta'] ?? 'Activar PRO')
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
