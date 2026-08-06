<?php

declare(strict_types=1);

namespace App\Filament\App\Pages\ImportExport;

use App\Application\Imports\ImportService;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;

/**
 * ImportWizard - 5-step wizard para importar datos.
 *
 * Steps:
 * 1. Select entity
 * 2. Upload file
 * 3. Map columns
 * 4. Preview rows
 * 5. Run import
 */
class ImportWizard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-arrow-up-tray';

    protected static ?string $navigationGroup = 'Configuración';

    protected static ?string $title = 'Importar datos';

    protected static ?string $navigationLabel = 'Importar';

    protected static ?int $navigationSort = 90;

    protected static string $view = 'filament.app.pages.import-wizard';

    public ?array $data = [];

    public int $step = 1;

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Paso 1: Selecciona la entidad')
                    ->visible(fn () => $this->step === 1)
                    ->schema([
                        Select::make('entity')
                            ->label('Entidad a importar')
                            ->options(fn () => app(ImportService::class)->entities())
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn () => $this->step = 2),
                    ]),

                Section::make('Paso 2: Subir archivo')
                    ->visible(fn () => $this->step === 2)
                    ->schema([
                        FileUpload::make('file')
                            ->label('Archivo CSV o Excel')
                            ->disk('local')
                            ->directory('imports')
                            ->acceptedFileTypes([
                                'text/csv',
                                'application/vnd.ms-excel',
                                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            ])
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn () => $this->step = 3),
                    ]),

                Section::make('Paso 3: Mapear columnas')
                    ->description('Si los headers de tu archivo coinciden con los esperados, no hay que mapear.')
                    ->visible(fn () => $this->step === 3)
                    ->schema([
                        \Filament\Forms\Components\Placeholder::make('expected_headers')
                            ->label('Headers esperados')
                            ->content(fn (Get $get) => $this->getExpectedHeaders($get('entity'))),
                    ]),

                Section::make('Paso 4: Vista previa')
                    ->visible(fn () => $this->step === 4)
                    ->schema([
                        \Filament\Forms\Components\Placeholder::make('preview')
                            ->label('Primeras filas')
                            ->content('Las primeras filas del archivo se muestran aquí.'),
                    ]),

                Section::make('Paso 5: Ejecutar importación')
                    ->visible(fn () => $this->step === 5)
                    ->schema([
                        \Filament\Forms\Components\Placeholder::make('confirm')
                            ->label('Listo para importar')
                            ->content('Click en "Importar" para iniciar.'),
                    ]),
            ])
            ->statePath('data');
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('previous')
                ->label('? Anterior')
                ->visible(fn () => $this->step > 1)
                ->action(fn () => $this->step = max(1, $this->step - 1)),
            Action::make('next')
                ->label('Siguiente ?')
                ->visible(fn () => $this->step < 5)
                ->action(fn () => $this->step = min(5, $this->step + 1)),
            Action::make('import')
                ->label('Importar ahora')
                ->visible(fn () => $this->step === 5)
                ->color('success')
                ->requiresConfirmation()
                ->action(function () {
                    $state = $this->form->getState();
                    $tenantId = \Filament\Facades\Filament::getTenant()->getKey();

                    try {
                        $result = app(\App\Infrastructure\Imports\ExcelImporter::class)->import(
                            app(ImportService::class)->getImporterClass($state['entity']),
                            storage_path('app/' . $state['file']),
                        );

                        if ($result['success']) {
                            Notification::make()
                                ->title("Importación completa: {$result['imported']} filas")
                                ->success()
                                ->send();
                            $this->step = 1;
                            $this->form->fill();
                        } else {
                            Notification::make()
                                ->title('Errores de importación')
                                ->body(implode("\n", array_slice($result['errors'], 0, 5)))
                                ->danger()
                                ->send();
                        }
                    } catch (\Throwable $e) {
                        Notification::make()->title('Error: ' . $e->getMessage())->danger()->send();
                    }
                }),
        ];
    }

    private function getExpectedHeaders(?string $entity): string
    {
        if (! $entity) {
            return 'Selecciona una entidad primero.';
        }
        $importer = app(ImportService::class)->makeImporter($entity, '');
        return implode(', ', $importer->templateHeaders());
    }
}



