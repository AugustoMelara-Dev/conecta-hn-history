<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Automation;

use App\Filament\App\Resources\Automation\WorkflowRunResource\Pages;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * WorkflowRunResource - ReadOnly; muestra ejecuciones de workflows.
 */
class WorkflowRunResource extends Resource
{
    protected static ?string $model = \App\Models\WorkflowRun::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-play';

    protected static string | \UnitEnum | null $navigationGroup = 'Automatización';

    protected static ?string $navigationLabel = 'Runs';

    protected static ?string $modelLabel = 'run';

    protected static ?string $pluralModelLabel = 'Runs';

    protected static ?int $navigationSort = 3;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('workflow.name')->label('Workflow')->searchable(),
                TextColumn::make('status')->label('Estado')->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success',
                        'running' => 'info',
                        'failed' => 'danger',
                        'partial_failure' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('duration_ms')->label('Duración (ms)')->numeric(),
                TextColumn::make('started_at')->label('Inicio')->dateTime('d/m/Y H:i:s')->sortable(),
                TextColumn::make('completed_at')->label('Fin')->dateTime('d/m/Y H:i:s')->placeholder('En curso'),
            ])
            ->defaultSort('started_at', 'desc');
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

    public static function getRelations(): array { return []; }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWorkflowRuns::route('/'),
            'view' => Pages\ViewWorkflowRun::route('/{record}'),
        ];
    }
}
