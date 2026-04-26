<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Dynamic\DynamicRecordResource\Pages;

use App\Filament\App\Resources\Dynamic\DynamicRecordResource;
use App\Filament\App\Resources\Pages\ListTenantRecords;
use Filament\Actions\CreateAction;

class ListDynamicRecords extends ListTenantRecords
{
    protected static string $resource = DynamicRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

