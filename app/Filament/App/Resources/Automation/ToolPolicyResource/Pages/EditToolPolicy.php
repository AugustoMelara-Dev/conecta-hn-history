<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Automation\ToolPolicyResource\Pages;

use App\Filament\App\Resources\Automation\ToolPolicyResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditToolPolicy extends EditRecord
{
    protected static string $resource = ToolPolicyResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
