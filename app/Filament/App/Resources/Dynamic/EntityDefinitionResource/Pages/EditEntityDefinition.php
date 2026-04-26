<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Dynamic\EntityDefinitionResource\Pages;

use App\Filament\App\Resources\Dynamic\EntityDefinitionResource;
use App\Filament\App\Resources\Pages\EditTenantRecord;
use Filament\Actions\DeleteAction;

class EditEntityDefinition extends EditTenantRecord
{
    protected static string $resource = EntityDefinitionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn (): bool => (string) $this->getRecord()->tenant_id !== ''),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

