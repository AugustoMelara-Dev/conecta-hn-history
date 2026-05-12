<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\ProductResource\Pages;

use App\Filament\App\Resources\ProductResource;
use App\Settings\GeneralSettings;
use Filament\Actions;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    /**
     * Header con indicador de límite
     */
    public function getHeading(): string
    {
        $limitInfo = ProductResource::getProductLimitInfo();

        if ($limitInfo['isPro'] ?? false) {
            return "Mis productos ({$limitInfo['count']})";
        }

        return "Mis productos ({$limitInfo['count']}/{$limitInfo['limit']})";
    }

    /**
     * Subheading con mensaje de upsell si está cerca del límite
     */
    public function getSubheading(): ?string
    {
        $limitInfo = ProductResource::getProductLimitInfo();

        if ($limitInfo['isPro'] ?? false) {
            return null;
        }

        if ($limitInfo['percentage'] >= 80) {
            return "Estas usando el {$limitInfo['percentage']}% de tu limite.";
        }

        return null;
    }

    /**
     * Acciones del header con lógica de límite
     */
    protected function getHeaderActions(): array
    {
        $limitInfo = ProductResource::getProductLimitInfo();

        return [
            // Botón de upgrade si alcanzó el límite
            Actions\Action::make('upgrade')
                ->label('Ampliar catalogo con PRO')
                ->color('warning')
                ->icon('heroicon-o-sparkles')
                ->url(fn () => route('filament.app.pages.manage-billing', ['tenant' => Filament::getTenant()?->slug]))
                ->visible(! $limitInfo['canCreate'] && ! ($limitInfo['isPro'] ?? false)),

            // Botón de crear
            Actions\CreateAction::make()
                ->disabled(! $limitInfo['canCreate'])
                ->before(function ($action) use ($limitInfo) {
                    if (! $limitInfo['canCreate']) {
                        $settings = app(GeneralSettings::class);
                        $price = number_format($settings->pro_plan_price ?? 500, 2);

                        Notification::make()
                            ->danger()
                            ->title('Tu catalogo esta creciendo')
                            ->body("Has alcanzado el limite de {$limitInfo['limit']} productos del Plan Semilla. Mejora a PRO para ampliar catalogo, reservas/reportes, campanas e IA activable.")
                            ->persistent()
                            ->actions([
                                \Filament\Notifications\Actions\Action::make('upgrade')
                                    ->button()
                                    ->label("Mejorar a PRO - L. {$price}/mes")
                                    ->url(route('filament.app.pages.manage-billing', ['tenant' => Filament::getTenant()?->slug]))
                                    ->markAsRead(),
                            ])
                            ->send();

                        $action->halt();
                    }
                }),
        ];
    }

    /**
     * Widgets del header (Banner de upsell)
     */
    protected function getHeaderWidgets(): array
    {
        $limitInfo = ProductResource::getProductLimitInfo();

        // Mostrar banner si está al 80% o más del límite
        if (! ($limitInfo['isPro'] ?? false) && $limitInfo['percentage'] >= 80) {
            return [
                \App\Filament\App\Widgets\ProductLimitWarning::class,
            ];
        }

        return [];
    }
}
