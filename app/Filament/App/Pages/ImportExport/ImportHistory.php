<?php

declare(strict_types=1);

namespace App\Filament\App\Pages\ImportExport;

use App\Application\Imports\OperationalImportService;
use App\Domain\Catalog\Enums\CatalogImportStatus;
use App\Models\CatalogImportBatch;
use App\Models\CatalogImportItem;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

final class ImportHistory extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clock';

    protected static ?string $title = 'Historial de importaciones';

    protected static string|\UnitEnum|null $navigationGroup = 'Configuración';

    protected static ?string $navigationLabel = 'Historial';

    protected static ?int $navigationSort = 92;

    protected string $view = 'filament.app.pages.import-history';

    /** @return list<array<string, mixed>> */
    public function getRecentImports(): array
    {
        $tenantId = Filament::getTenant()?->getKey();
        if (! $tenantId) {
            return [];
        }

        return CatalogImportBatch::query()
            // INFRA_TENANT_FILTER: history is constrained to the active Filament tenant UUID.
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->withCount('items')
            ->latest()
            ->limit(50)
            ->get()
            ->map(static fn (CatalogImportBatch $batch): array => [
                'id' => (string) $batch->getKey(),
                'kind' => (string) $batch->kind,
                'file' => (string) $batch->original_name,
                'status' => $batch->status instanceof CatalogImportStatus ? $batch->status->value : (string) $batch->status,
                'total' => (int) $batch->total_items,
                'failed' => (int) $batch->failed_items,
                'items' => (int) $batch->items_count,
                'created_at' => $batch->created_at?->format('d/m/Y H:i'),
            ])->all();
    }

    public function approveAndPublish(string $batchId): void
    {
        $tenant = Filament::getTenant();
        $actorId = auth()->id();
        if ($tenant === null || ! is_string($actorId)) {
            abort(403);
        }
        $tenantId = (string) $tenant->getKey();
        $decisions = CatalogImportItem::query()
            // INFRA_TENANT_FILTER: review decisions require both active tenant and selected import batch.
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('batch_id', $batchId)
            ->where('status', CatalogImportStatus::NeedsReview->value)
            ->pluck('id')
            ->mapWithKeys(static fn (string $id): array => [$id => 'approve'])
            ->all();

        $imports = app(OperationalImportService::class);
        if ($decisions !== []) {
            $imports->review($tenantId, $actorId, $batchId, $decisions);
        }
        $report = $imports->publish($tenantId, $actorId, $batchId);

        Notification::make()
            ->title('Lote publicado')
            ->body("{$report->published} registro(s) publicados; {$report->failed} fallidos.")
            ->success()
            ->send();
    }
}
