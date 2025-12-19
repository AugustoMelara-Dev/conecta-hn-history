<?php

namespace App\Filament\App\Resources\MenuSectionResource\Pages;

use App\Filament\App\Resources\MenuSectionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMenuSection extends EditRecord
{
    protected static string $resource = MenuSectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

