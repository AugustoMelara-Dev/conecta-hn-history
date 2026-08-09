<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Automation;

use App\Application\Automation\WorkflowDefinitionService;
use App\Filament\App\Resources\Automation\WorkflowResource\Pages;
use App\Jobs\RunWorkflowJob;
use App\Models\AutomationWorkflowDefinition;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class WorkflowResource extends Resource
{
    protected static ?string $model = AutomationWorkflowDefinition::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrows-pointing-out';

    protected static string|\UnitEnum|null $navigationGroup = 'Automatización';

    protected static ?string $navigationLabel = 'Workflows';

    protected static ?string $modelLabel = 'workflow';

    protected static ?string $pluralModelLabel = 'Workflows';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Información')
                    ->components([
                        TextInput::make('workflow_key')
                            ->label('Clave del workflow')
                            ->required()
                            ->disabled(fn (?AutomationWorkflowDefinition $record): bool => $record !== null)
                            ->maxLength(100),
                    ]),

                Section::make('Definición del workflow (JSON)')
                    ->description('Define los pasos de la versión borrador canónica.')
                    ->components([
                        Textarea::make('definition_json')
                            ->label('Definición JSON')
                            ->rows(15)
                            ->rules(['json'])
                            ->placeholder('{"steps": [...]}')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('workflow_key')
                    ->label('Clave')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),
                TextColumn::make('currentDraftVersion.version')
                    ->label('Borrador')
                    ->placeholder('Sin borrador'),
                TextColumn::make('currentPublishedVersion.version')
                    ->label('Publicada')
                    ->placeholder('Sin publicar'),
                TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([])
            ->actions([
                Action::make('run_now')
                    ->label('Ejecutar ahora')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (AutomationWorkflowDefinition $record): bool => $record->current_published_version_id !== null)
                    ->action(function (AutomationWorkflowDefinition $record): void {
                        $tenant = Filament::getTenant();
                        $actor = auth()->user();
                        if ($tenant === null || $actor === null || (string) $record->tenant_id !== (string) $tenant->getKey()) {
                            abort(403);
                        }

                        $published = $record->currentPublishedVersion;
                        if ($published === null) {
                            abort(409, 'Published workflow version not found.');
                        }

                        $role = $actor->getAttribute('role');
                        RunWorkflowJob::dispatch(
                            tenantId: (string) $tenant->getKey(),
                            workflowKey: (string) $record->workflow_key,
                            workflowVersion: (string) $published->version,
                            actorId: (string) $actor->getAuthIdentifier(),
                            actorRoles: [(string) ($role instanceof \BackedEnum ? $role->value : $role)],
                            plan: (string) ($tenant->getRawOriginal('plan_type') ?? 'semilla'),
                            idempotencyKey: (string) Str::uuid(),
                        );

                        Notification::make()->title('Workflow encolado para ejecución')->success()->send();
                    }),
                Action::make('publish')
                    ->label('Publicar')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->visible(fn (AutomationWorkflowDefinition $record): bool => $record->current_draft_version_id !== null)
                    ->form([
                        TextInput::make('published_version')
                            ->label('Versión')
                            ->required()
                            ->maxLength(100),
                    ])
                    ->action(function (AutomationWorkflowDefinition $record, array $data): void {
                        $tenant = Filament::getTenant();
                        $actor = auth()->user();
                        if ($tenant === null || $actor === null || (string) $record->tenant_id !== (string) $tenant->getKey()) {
                            abort(403);
                        }

                        app(WorkflowDefinitionService::class)->publishDraft(
                            (string) $tenant->getKey(),
                            (string) $record->workflow_key,
                            (string) $record->current_draft_version_id,
                            (string) $data['published_version'],
                            (string) $actor->getAuthIdentifier(),
                        );

                        Notification::make()->title('Workflow publicado')->success()->send();
                    }),
                EditAction::make(),
            ])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc');
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $tenant = Filament::getTenant();
        if (! $tenant) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('tenant_id', $tenant->getKey());
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWorkflows::route('/'),
            'create' => Pages\CreateWorkflow::route('/create'),
            'edit' => Pages\EditWorkflow::route('/{record}/edit'),
        ];
    }
}
