<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Crm\ContactResource\Pages;

use App\Filament\App\Resources\Crm\ContactResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditContact extends EditRecord
{
    protected static string $resource = ContactResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
