<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Crm\ActivityResource\Pages;

use App\Filament\App\Resources\Crm\ActivityResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditActivity extends EditRecord
{
    protected static string $resource = ActivityResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
