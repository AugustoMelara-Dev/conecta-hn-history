<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Crm\CustomFieldResource\Pages;

use App\Filament\App\Resources\Crm\CustomFieldResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCustomFields extends ListRecords
{
    protected static string $resource = CustomFieldResource::class;
    protected function getHeaderActions(): array { return [CreateAction::make()->label('Nuevo campo')]; }
}
