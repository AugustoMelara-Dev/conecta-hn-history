<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Automation\ToolPolicyResource\Pages;

use App\Filament\App\Resources\Automation\ToolPolicyResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListToolPolicies extends ListRecords
{
    protected static string $resource = ToolPolicyResource::class;
    protected function getHeaderActions(): array { return [CreateAction::make()->label('Nueva política')]; }
}
