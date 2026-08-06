<?php

declare(strict_types=1);

namespace App\Filament\App\Pages\ImportExport;

use App\Application\Imports\ExportService;
use App\Application\Imports\ImportService;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;

/**
 * ExportCenter - Exporta datos a Excel/CSV/PDF.
 */
class ExportCenter extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-arrow-down-tray';

    protected static ?string $navigationGroup = 'Configuración';

    protected static ?string $title = 'Exportar datos';

    protected static ?string $navigationLabel = 'Exportar';

    protected static ?int $navigationSort = 91;

    protected static string $view = 'filament.app.pages.export-center';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Configuración de exportación')
                    ->schema([
                        Select::make('entity')
                            ->label('Entidad')
                            ->options(fn () => app(ImportService::class)->entities())
                            ->required()
                            ->live(),
                        Select::make('format')
                            ->label('Formato')
                            ->options([
                                'xlsx' => 'Excel (.xlsx)',
                                'csv' => 'CSV (.csv)',
                                'pdf' => 'PDF (.pdf)',
                            ])
                            ->default('xlsx')
                            ->required(),
                    ]),
            ])
            ->statePath('data');
    }

    public function export(): mixed
    {
        $state = $this->form->getState();
        $tenantId = \Filament\Facades\Filament::getTenant()->getKey();

        try {
            $exportClass = app(ExportService::class)->getExporterClass($state['entity'], $tenantId);

            if ($state['format'] === 'pdf') {
                return app(\App\Infrastructure\Imports\PdfExporter::class)->download(
                    'exports.generic',
                    ['entity' => $state['entity']],
                    "{$state['entity']}.pdf",
                );
            }

            return app(\App\Infrastructure\Imports\ExcelExporter::class)->download(
                new $exportClass(),
                "{$state['entity']}.{$state['format']}",
                $state['format'],
            );
        } catch (\Throwable $e) {
            Notification::make()->title('Error: ' . $e->getMessage())->danger()->send();
            return null;
        }
    }
}



