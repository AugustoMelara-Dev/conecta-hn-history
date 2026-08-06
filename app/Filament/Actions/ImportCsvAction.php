<?php

declare(strict_types=1);

namespace App\Filament\Actions;

use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;

/**
 * ImportCsvAction - Header action para importar CSV en cualquier Resource.
 */
class ImportCsvAction
{
    public static function make(string $entity): Action
    {
        return Action::make('import_csv_' . $entity)
            ->label('Importar CSV')
            ->icon('heroicon-o-arrow-up-tray')
            ->color('info')
            ->form([
                FileUpload::make('file')
                    ->label('Archivo CSV/Excel')
                    ->disk('local')
                    ->directory('imports')
                    ->acceptedFileTypes(['text/csv', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
                    ->required(),
                Select::make('mode')
                    ->label('Modo')
                    ->options([
                        'create' => 'Solo crear nuevos',
                        'update' => 'Crear o actualizar',
                        'replace' => 'Reemplazar existentes',
                    ])
                    ->default('create')
                    ->required(),
            ])
            ->action(function (array $data) use ($entity) {
                $tenantId = \Filament\Facades\Filament::getTenant()?->getKey();
                if (! $tenantId) {
                    Notification::make()->title('No tenant context')->danger()->send();
                    return;
                }

                try {
                    $importClass = app(\App\Application\Imports\ImportService::class)
                        ->getImporterClass($entity);
                    $importer = new $importClass($tenantId);

                    $result = app(\App\Infrastructure\Imports\ExcelImporter::class)->import(
                        $importClass,
                        storage_path('app/' . $data['file']),
                    );

                    if ($result['success']) {
                        Notification::make()
                            ->title("Importación completa: {$result['imported']} filas en {$result['duration_ms']}ms")
                            ->success()
                            ->send();
                    } else {
                        Notification::make()
                            ->title('Errores')
                            ->body(implode("\n", array_slice($result['errors'], 0, 5)))
                            ->danger()
                            ->send();
                    }
                } catch (\Throwable $e) {
                    Notification::make()->title('Error: ' . $e->getMessage())->danger()->send();
                }
            });
    }
}
