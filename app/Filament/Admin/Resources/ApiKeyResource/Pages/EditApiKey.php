<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ApiKeyResource\Pages;

use App\Filament\Admin\Resources\ApiKeyResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditApiKey extends EditRecord
{
    protected static string $resource = ApiKeyResource::class;
    protected function getHeaderActions(): array { return [DeleteAction::make()]; }
}
