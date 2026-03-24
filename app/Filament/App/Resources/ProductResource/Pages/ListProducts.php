<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\ProductResource\Pages;

use App\Filament\App\Resources\Pages\ListTenantRecords;
use App\Filament\App\Resources\ProductResource;
use App\Settings\GeneralSettings;
use Filament\Actions;
use Filament\Actions\Action;
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
        $limitInfo = ProductResource::getProductLimitInfo();

        if ($limitInfo['isPro'] ?? false) {
            return 'Administra tu catálogo y mantén visibles solo los productos que quieres ofrecer hoy.';
        }

        if (($limitInfo['percentage'] ?? 0) >= 80) {
            return sprintf(
                'Estás usando el %d%% del límite de productos del plan Base.',
                $limitInfo['percentage']
            );
        }

        return 'Agrega, edita y publica tu catálogo desde una sola vista.';
    }

    protected function getHeaderActions(): array
    {
        $limitInfo = ProductResource::getProductLimitInfo();

        return [
            Actions\Action::make('upgrade')
                ->label('Pasar a PRO')
                ->color('warning')
                ->icon('heroicon-o-sparkles')
                ->url(route('filament.app.pages.manage-billing', ['tenant' => ProductResource::currentTenantSlug()]))
                ->visible(! $limitInfo['canCreate'] && ! ($limitInfo['isPro'] ?? false)),

            Actions\CreateAction::make()
                ->label('Nuevo producto')
                ->disabled(! $limitInfo['canCreate'])
                ->before(function ($action) use ($limitInfo): void {
                    if ($limitInfo['canCreate']) {
                        return;
                    }

                    $settings = app(GeneralSettings::class);
                    $price = number_format((float) ($settings->pro_plan_price ?? 500), 2);

                    Notification::make()
                        ->danger()
                        ->title('Llegaste al límite del plan Base')
                        ->body("Ya usaste {$limitInfo['limit']} productos. Activa PRO para trabajar con catálogo ampliado.")
                        ->persistent()
                        ->actions([
                            Action::make('upgrade')
                                ->button()
                                ->label("Activar PRO - L. {$price}/mes")
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
        $limitInfo = ProductResource::getProductLimitInfo();

        if (! ($limitInfo['isPro'] ?? false) && ($limitInfo['percentage'] ?? 0) >= 80) {
            return [
                \App\Filament\App\Widgets\ProductLimitWarning::class,
            ];
        }

        return [];
    }
}
