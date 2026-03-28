<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\MenuSectionResource\Pages;

use App\Filament\App\Resources\MenuSectionResource;
use App\Models\MenuSection;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditMenuSection extends EditRecord
{
    protected static string $resource = MenuSectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->action(function (): void {
                    /** @var MenuSection $record */
                    $record = $this->getRecord();

                    static::getResource()::deleteOwnedMenuSection($record);
                }),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var MenuSection $record */
        return static::getResource()::updateOwnedMenuSection($record, $data);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
