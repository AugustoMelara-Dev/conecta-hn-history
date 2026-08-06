<?php

declare(strict_types=1);

namespace App\Filament\Actions;

use Filament\Actions\Action;
use Filament\Forms\Components\Select;

/**
 * ExportCsvAction - Header action para exportar a CSV/Excel/PDF.
 */
class ExportCsvAction
{
    public static function make(string $entity): Action
    {
        return Action::make('export_csv_' . $entity)
            ->label('Exportar')
            ->icon('heroicon-o-arrow-down-tray')
            ->color('gray')
            ->form([
                Select::make('format')
                    ->label('Formato')
                    ->options([
                        'xlsx' => 'Excel (.xlsx)',
                        'csv' => 'CSV (.csv)',
                        'pdf' => 'PDF (.pdf)',
                    ])
                    ->default('xlsx')
                    ->required(),
            ])
            ->action(function (array $data) use ($entity) {
                $tenantId = \Filament\Facades\Filament::getTenant()?->getKey();
                if (! $tenantId) {
                    return null;
                }
                try {
                    $exporter = app(\App\Application\Imports\ExportService::class)
                        ->getExporterClass($entity, $tenantId);

                    if ($data['format'] === 'pdf') {
                        return app(\App\Infrastructure\Imports\PdfExporter::class)->download(
                            'exports.generic',
                            ['entity' => $entity],
                            "{$entity}.pdf",
                        );
                    }

                    return app(\App\Infrastructure\Imports\ExcelExporter::class)->download(
                        $exporter,
                        "{$entity}.{$data['format']}",
                        $data['format'],
                    );
                } catch (\Throwable $e) {
                    \Filament\Notifications\Notification::make()
                        ->title('Error: ' . $e->getMessage())
                        ->danger()
                        ->send();
                    return null;
                }
            });
    }
}
