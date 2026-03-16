<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\LocationResource\Pages;

use App\Filament\App\Resources\LocationResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditLocation extends EditRecord
{
    protected static string $resource = LocationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn (): bool => ! ((bool) $this->getRecord()->is_primary)),
        ];
    }
}
