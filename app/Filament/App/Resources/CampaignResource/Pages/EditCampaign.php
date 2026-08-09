<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\CampaignResource\Pages;

use App\Application\Marketing\CampaignDispatchService;
use App\Filament\App\Resources\CampaignResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCampaign extends EditRecord
{
    protected static string $resource = CampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('schedule')
                ->label('Programar')
                ->icon('heroicon-o-calendar')
                ->visible(fn (): bool => in_array($this->getRecord()->status, ['draft', 'paused'], true) && $this->getRecord()->scheduled_at !== null)
                ->action(fn () => app(CampaignDispatchService::class)->schedule($this->getRecord(), $this->getRecord()->scheduled_at)),
            Action::make('send_now')
                ->label('Enviar ahora')
                ->icon('heroicon-o-paper-airplane')
                ->requiresConfirmation()
                ->visible(fn (): bool => in_array($this->getRecord()->status, ['draft', 'paused', 'scheduled'], true))
                ->action(fn () => app(CampaignDispatchService::class)->schedule($this->getRecord(), now())),
            Action::make('pause')
                ->label('Pausar')
                ->icon('heroicon-o-pause')
                ->visible(fn (): bool => $this->getRecord()->status === 'scheduled')
                ->action(fn () => app(CampaignDispatchService::class)->pause($this->getRecord())),
            DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
