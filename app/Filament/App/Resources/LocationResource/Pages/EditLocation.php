<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\LocationResource\Pages;

use App\Filament\App\Resources\LocationResource;
use App\Models\Location;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

/**
 * @property Location $record
 */
class EditLocation extends EditRecord
{
    protected static string $resource = LocationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(function (): bool {
                    $location = $this->getLocationRecord();

                    if (! $location instanceof Location) {
                        return true;
                    }

                    return ! $location->is_primary;
                }),
        ];
    }

    private function getLocationRecord(): ?Location
    {
        $record = $this->getRecord();

        return $record instanceof Location ? $record : null;
    }
}
