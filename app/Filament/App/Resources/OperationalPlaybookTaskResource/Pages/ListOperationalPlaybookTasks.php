<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\OperationalPlaybookTaskResource\Pages;

use App\Filament\App\Resources\OperationalPlaybookTaskResource;
use App\Filament\App\Resources\Pages\ListTenantRecords;

class ListOperationalPlaybookTasks extends ListTenantRecords
{
    protected static string $resource = OperationalPlaybookTaskResource::class;

    public function getSubheading(): ?string
    {
        return 'Aqui ejecutas leads, citas, recuperacion, recompra y renovacion con responsable y SLA claros.';
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
