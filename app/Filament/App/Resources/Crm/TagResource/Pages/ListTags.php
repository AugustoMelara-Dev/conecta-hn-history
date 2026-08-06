<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Crm\TagResource\Pages;

use App\Filament\App\Resources\Crm\TagResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTags extends ListRecords
{
    protected static string $resource = TagResource::class;
    protected function getHeaderActions(): array { return [CreateAction::make()->label('Nuevo tag')]; }
}
