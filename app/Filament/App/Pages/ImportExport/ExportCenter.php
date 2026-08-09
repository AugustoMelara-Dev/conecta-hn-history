<?php

declare(strict_types=1);

namespace App\Filament\App\Pages\ImportExport;

use App\Application\Imports\ExportService;
use App\Application\Imports\ImportService;
use App\Infrastructure\Imports\ExcelExporter;
use App\Infrastructure\Imports\PdfExporter;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * ExportCenter - Exporta datos a Excel/CSV/PDF.
 */
class ExportCenter extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrow-down-tray';

    protected static string|\UnitEnum|null $navigationGroup = 'Configuración';

    protected static ?string $title = 'Exportar datos';

    protected static ?string $navigationLabel = 'Exportar';

    protected static ?int $navigationSort = 91;

    protected string $view = 'filament.app.pages.export-center';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Configuración de exportación')
                    ->components([
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
        $tenantId = Filament::getTenant()->getKey();

        try {
            $exportClass = app(ExportService::class)->getExporterClass($state['entity'], $tenantId);

            if ($state['format'] === 'pdf') {
                return app(PdfExporter::class)->download(
                    'exports.generic',
                    ['entity' => $state['entity']],
                    "{$state['entity']}.pdf",
                );
            }

            return app(ExcelExporter::class)->download(
                new $exportClass,
                "{$state['entity']}.{$state['format']}",
                $state['format'],
            );
        } catch (\Throwable $e) {
            Notification::make()->title('Error: '.$e->getMessage())->danger()->send();

            return null;
        }
    }
}
