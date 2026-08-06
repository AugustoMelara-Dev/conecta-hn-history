<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\WebhookResource\Pages;

use App\Filament\Admin\Resources\WebhookResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWebhooks extends ListRecords
{
    protected static string $resource = WebhookResource::class;
    protected function getHeaderActions(): array { return [CreateAction::make()->label('Nuevo webhook')]; }
}
