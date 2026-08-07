<?php

declare(strict_types=1);

namespace App\Filament\App\Pages\ImportExport;

use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

/**
 * ImportHistory - Lista de pasadas importaciones con status.
 */
class ImportHistory extends Page
{
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-clock';

    protected static ?string $title = 'Historial de importaciones';

    protected static string | \UnitEnum | null $navigationGroup = 'Configuración';

    protected static ?string $navigationLabel = 'Historial';

    protected static ?int $navigationSort = 92;

    protected string $view = 'filament.app.pages.import-history';

    public function getRecentImports(): array
    {
        $tenantId = \Filament\Facades\Filament::getTenant()?->getKey();
        if (! $tenantId) return [];

        return DB::table('import_jobs')
            ->where('tenant_id', $tenantId)
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get()
            ->toArray();
    }
}
