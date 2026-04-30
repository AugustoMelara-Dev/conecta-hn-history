<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\ProductResource\Pages;

use App\Filament\App\Resources\Pages\ListTenantRecords;
use App\Filament\App\Resources\ProductResource;
use App\Infrastructure\TenantGovernance\Presentation\TenantPlanPresentationReader;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;

class ListProducts extends ListTenantRecords
{
    protected static string $resource = ProductResource::class;

    public function getHeading(): string
    {
        $limitInfo = ProductResource::getProductLimitInfo();

        if ($limitInfo['isPro'] ?? false) {
            return sprintf('Productos (%d)', $limitInfo['count']);
        }

        return sprintf('Productos (%d/%d)', $limitInfo['count'], $limitInfo['limit']);
    }

    public function getSubheading(): ?string
    {
        $plan = $this->getPlanPresentation();

        if (($plan['isCatalogUnlimited'] ?? false) === true) {
            return $plan['catalogCopy'];
        }

        return $plan['catalogStatusCopy'] ?? 'Agrega, edita y publica tu catalogo desde una sola vista.';
    }

    protected function getHeaderActions(): array
    {
        $limitInfo = ProductResource::getProductLimitInfo();
        $plan = $this->getPlanPresentation();

        return [
            Actions\Action::make('upgrade')
                ->label($plan['billingCta'] ?? 'Activar PRO')
                ->color('warning')
                ->icon('heroicon-o-sparkles')
                ->url(route('filament.app.pages.manage-billing', ['tenant' => ProductResource::currentTenantSlug()]))
                ->visible(! $limitInfo['canCreate'] && (bool) ($plan['isUpgradeable'] ?? false)),

            Actions\CreateAction::make()
                ->label('Nuevo producto')
                ->disabled(! $limitInfo['canCreate'])
                ->before(function ($action) use ($limitInfo, $plan): void {
                    if ($limitInfo['canCreate']) {
                        return;
                    }

                    Notification::make()
                        ->danger()
                        ->title('Llegaste al límite del catálogo')
                        ->body("Ya usaste {$limitInfo['limit']} productos. {$plan['catalogStatusCopy']}")
                        ->persistent()
                        ->actions([
                            Action::make('upgrade')
                                ->button()
                                ->label(($plan['billingCta'] ?? 'Activar PRO').' - '.($plan['priceLabel'] ?? 'L. 99.00/mes'))
                                ->url(route('filament.app.pages.manage-billing', ['tenant' => ProductResource::currentTenantSlug()]))
                                ->markAsRead(),
                        ])
                        ->send();

                    $action->halt();
                }),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        $widgets = parent::getHeaderWidgets();
        $limitInfo = ProductResource::getProductLimitInfo();

        if (! ($limitInfo['isPro'] ?? false) && ($limitInfo['percentage'] ?? 0) >= 80) {
            $widgets[] = \App\Filament\App\Widgets\ProductLimitWarning::class;
        }

        return $widgets;
    }

    /**
     * @return array<string, mixed>
     */
    private function getPlanPresentation(): array
    {
        $tenant = Filament::getTenant();

        if (! $tenant instanceof \App\Models\Tenant) {
            return [];
        }

        return app(TenantPlanPresentationReader::class)->forTenant($tenant);
    }
}
