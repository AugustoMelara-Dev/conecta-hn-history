<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Automation;

use App\Domain\Automation\Enums\WorkflowStatus;
use App\Filament\App\Resources\Automation\WorkflowResource\Pages;
use App\Models\WorkflowDefinition;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class WorkflowResource extends Resource
{
    protected static ?string $model = WorkflowDefinition::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-arrows-pointing-out';

    protected static string | \UnitEnum | null $navigationGroup = 'Automatización';

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
                        Grid::make(2)
                            ->components([
                                TextInput::make('name')
                                    ->label('Nombre')
                                    ->required()
                                    ->maxLength(200),
                                Select::make('status')
                                    ->label('Estado')
                                    ->options(WorkflowStatus::options())
                                    ->default(WorkflowStatus::Draft->value)
                                    ->required(),
                                Select::make('trigger_type')
                                    ->label('Trigger')
                                    ->options(\App\Domain\Automation\Enums\TriggerType::options())
                                    ->required(),
                                TextInput::make('description')
                                    ->label('Descripción')
                                    ->maxLength(500),
                                Toggle::make('is_active')
                                    ->label('Activo')
                                    ->default(false),
                            ]),
                    ]),

                Section::make('Grafo del workflow (JSON)')
                    ->description('Define el DAG del workflow en formato JSON.')
                    ->components([
                        Textarea::make('graph_json')
                            ->label('Graph JSON')
                            ->rows(15)
                            ->placeholder('{"nodes": [...], "edges": [...]}')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),
                TextColumn::make('trigger_type')
                    ->label('Trigger')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => \App\Domain\Automation\Enums\TriggerType::tryFrom($state)?->label() ?? $state),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (string $state): string => WorkflowStatus::tryFrom($state)?->color() ?? 'gray'),
                IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean(),
                TextColumn::make('executions_count')
                    ->label('Ejecuciones')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('last_run_at')
                    ->label('Última ejecución')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('Nunca'),
            ])
            ->filters([
                SelectFilter::make('status')->label('Estado')->options(WorkflowStatus::options()),
                SelectFilter::make('trigger_type')->label('Trigger')->options(\App\Domain\Automation\Enums\TriggerType::options()),
            ])
            ->actions([
                EditAction::make(),
                Action::make('run_now')
                    ->label('Ejecutar ahora')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        \App\Jobs\RunWorkflowJob::dispatch($record->id);
                        Notification::make()->title('Workflow encolado para ejecución')->success()->send();
                    }),
                Action::make('pause')
                    ->label('Pausar')
                    ->icon('heroicon-o-pause')
                    ->color('warning')
                    ->visible(fn ($record) => $record->is_active)
                    ->action(function ($record) {
                        $record->update(['is_active' => false, 'status' => 'paused']);
                        Notification::make()->title('Workflow pausado')->success()->send();
                    }),
                Action::make('clone')
                    ->label('Clonar')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('info')
                    ->action(function ($record) {
                        $clone = $record->replicate();
                        $clone->name = $record->name . ' (copia)';
                        $clone->is_active = false;
                        $clone->status = 'draft';
                        $clone->save();
                        Notification::make()->title('Workflow clonado')->success()->send();
                    }),
                Action::make('export_json')
                    ->label('Exportar JSON')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(function ($record) {
                        return response()->streamDownload(
                            fn () => print(json_encode(['graph' => $record->graph_json], JSON_PRETTY_PRINT)),
                            "{$record->slug}.json",
                            ['Content-Type' => 'application/json'],
                        );
                    }),
                DeleteAction::make(),
            ])
            ->bulkActions([BulkActionGroup::make([DeleteBulkAction::make()])])
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
