<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Crm\NoteResource\Pages;

use App\Filament\App\Resources\Crm\NoteResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditNote extends EditRecord
{
    protected static string $resource = NoteResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
