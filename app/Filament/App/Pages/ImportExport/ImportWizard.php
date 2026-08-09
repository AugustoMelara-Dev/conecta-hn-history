<?php

declare(strict_types=1);

namespace App\Filament\App\Pages\ImportExport;

use App\Application\Imports\ImportService;
use App\Application\Imports\OperationalImportService;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class ImportWizard extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrow-up-tray';

    protected static string|\UnitEnum|null $navigationGroup = 'Configuración';

    protected static ?string $title = 'Importar datos';

    protected static ?string $navigationLabel = 'Importar';

    protected static ?int $navigationSort = 90;

    protected string $view = 'filament.app.pages.import-wizard';

    public ?array $data = [];

    public int $step = 1;

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('1. Tipo de datos')->components([
                Select::make('entity')
                    ->label('Datos a importar')
                    ->options(fn (): array => app(OperationalImportService::class)->kinds())
                    ->required()
                    ->live(),
            ]),
            Section::make('2. Archivo')->components([
                FileUpload::make('file')
                    ->label('CSV o Excel')
                    ->disk('local')
                    ->directory('imports')
                    ->acceptedFileTypes([
                        'text/csv',
                        'text/plain',
                        'application/vnd.ms-excel',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    ])
                    ->maxSize(10240)
                    ->required(),
                Placeholder::make('expected_headers')
                    ->label('Columnas esperadas')
                    ->content(fn (Get $get): string => $this->getExpectedHeaders($get('entity'))),
            ]),
            Section::make('3. Revisión')->components([
                Placeholder::make('review_notice')
                    ->label('Publicación controlada')
                    ->content('El archivo se valida en segundo plano. Después podrás revisar y publicar el lote desde Historial.'),
            ]),
        ])->statePath('data');
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('import')
                ->label('Validar archivo')
                ->color('success')
                ->requiresConfirmation()
                ->action(function (): void {
                    $state = $this->form->getState();
                    $tenant = Filament::getTenant();
                    $actorId = auth()->id();
                    if ($tenant === null || ! is_string($actorId)) {
                        abort(403);
                    }

                    try {
                        $storedPath = (string) $state['file'];
                        $path = Storage::disk('local')->path($storedPath);
                        $batchId = app(OperationalImportService::class)->stage(
                            tenantId: (string) $tenant->getKey(),
                            actorId: $actorId,
                            file: new UploadedFile($path, basename($storedPath), null, null, true),
                            kind: (string) $state['entity'],
                            idempotencyKey: 'filament-import:'.Str::uuid(),
                        );

                        Notification::make()
                            ->title('Importación encolada')
                            ->body("Lote {$batchId}. Revisa el historial antes de publicar.")
                            ->success()
                            ->send();
                        $this->form->fill();
                    } catch (\Throwable $exception) {
                        report($exception);
                        Notification::make()
                            ->title('No se pudo validar el archivo')
                            ->body($exception->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }

    private function getExpectedHeaders(?string $entity): string
    {
        if (! is_string($entity) || $entity === '') {
            return 'Selecciona un tipo de datos.';
        }

        if (in_array($entity, ['contacts', 'companies'], true)) {
            $importer = app(ImportService::class)->makeImporter($entity, '');

            return implode(', ', $importer->templateHeaders());
        }

        return implode(', ', app(OperationalImportService::class)->columns($entity));
    }
}
