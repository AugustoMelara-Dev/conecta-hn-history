<?php

declare(strict_types=1);

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\Pages\ListAdminRecords;
use App\Filament\Resources\UserResource;
use Filament\Actions;

class ListUsers extends ListAdminRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
