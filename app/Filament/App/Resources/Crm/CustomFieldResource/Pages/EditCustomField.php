<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Crm\CustomFieldResource\Pages;

use App\Filament\App\Resources\Crm\CustomFieldResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCustomField extends EditRecord
{
    protected static string $resource = CustomFieldResource::class;
    protected function getHeaderActions(): array { return [DeleteAction::make()]; }
}
