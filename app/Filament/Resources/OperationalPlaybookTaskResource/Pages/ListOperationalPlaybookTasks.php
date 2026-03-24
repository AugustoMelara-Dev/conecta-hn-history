<?php

declare(strict_types=1);

namespace App\Filament\Resources\OperationalPlaybookTaskResource\Pages;

use App\Filament\Resources\OperationalPlaybookTaskResource;
use Filament\Resources\Pages\ListRecords;

class ListOperationalPlaybookTasks extends ListRecords
{
    protected static string $resource = OperationalPlaybookTaskResource::class;

    public function getSubheading(): ?string
    {
        return 'Asigna owner interno, deja criterio, programa siguiente toque y protege churn o conversion entre tenants.';
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
