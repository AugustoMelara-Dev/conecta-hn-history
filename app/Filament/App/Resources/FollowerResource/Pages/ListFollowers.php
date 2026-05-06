<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\FollowerResource\Pages;

use App\Filament\App\Resources\FollowerResource;
use App\Filament\App\Widgets\FollowerCountWidget;
use Filament\Resources\Pages\ListRecords;

class ListFollowers extends ListRecords
{
    protected static string $resource = FollowerResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            FollowerCountWidget::class,
        ];
    }
}
