<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Crm\NoteResource\Pages;

use App\Filament\App\Resources\Crm\NoteResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListNotes extends ListRecords
{
    protected static string $resource = NoteResource::class;
    protected function getHeaderActions(): array { return [CreateAction::make()->label('Nueva nota')]; }
}
