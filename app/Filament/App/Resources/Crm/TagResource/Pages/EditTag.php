<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Crm\TagResource\Pages;

use App\Filament\App\Resources\Crm\TagResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTag extends EditRecord
{
    protected static string $resource = TagResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
