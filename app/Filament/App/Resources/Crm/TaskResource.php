<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Crm;

use App\Filament\App\Resources\Crm\TaskResource\Pages;
use App\Models\CustomerTask;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TaskResource extends Resource
{
    protected static ?string $model = CustomerTask::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationGroup = 'CRM';

    protected static ?string $navigationLabel = 'Tareas';

    protected static ?string $modelLabel = 'tarea';

    protected static ?string $pluralModelLabel = 'Tareas';

    protected static ?int $navigationSort = 15;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Detalles')->components([
                    Grid::make(2)->components([
                        TextInput::make('title')
                            ->label('Título')
                            ->required()
                            ->maxLength(200),
                        Select::make('priority')
                            ->label('Prioridad')
                            ->options(['high' => 'Alta', 'medium' => 'Media', 'low' => 'Baja'])
                            ->default('medium'),
                        DateTimePicker::make('due_at')
                            ->label('Vence')
                            ->timezone('America/Tegucigalpa')
                            ->displayFormat('d/m/Y H:i'),
                        Select::make('assignee_id')
                            ->label('Asignado a')
                            ->relationship('assignee', 'name')
                            ->searchable()
                            ->preload(),
                        Select::make('contact_id')
                            ->label('Contacto')
                            ->relationship('contact', 'id')
                            ->getOptionLabelFromRecordUsing(fn ($r) => $r->display_name ?? '—')
                            ->searchable(['first_name', 'last_name', 'email'])
                            ->preload(),
                        Select::make('deal_id')
                            ->label('Deal')
                            ->relationship('deal', 'name')
                            ->searchable()
                            ->preload(),
                        Toggle::make('is_completed')
                            ->label('Completada')
                            ->default(false),
                    ]),
                    \Filament\Forms\Components\Textarea::make('description')
                        ->label('Descripción')
                        ->rows(3)
                        ->columnSpanFull(),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->label('Título')->searchable()->weight('medium'),
                TextColumn::make('priority')->label('Prioridad')->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'high' => 'danger', 'medium' => 'warning', 'low' => 'gray', default => 'gray',
                    }),
                IconColumn::make('is_completed')->label('Completada')->boolean(),
                TextColumn::make('due_at')->label('Vence')->dateTime('d/m/Y H:i')->placeholder('—')->sortable(),
                TextColumn::make('assignee.name')->label('Asignado a')->placeholder('—'),
                TextColumn::make('contact.display_name')->label('Contacto')->placeholder('—')->toggleable(),
                TextColumn::make('deal.name')->label('Deal')->placeholder('—')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('priority')->label('Prioridad')->options(['high' => 'Alta', 'medium' => 'Media', 'low' => 'Baja']),
                TernaryFilter::make('is_completed')->label('Completada'),
                Tables\Filters\Filter::make('overdue')
                    ->label('Vencidas')
                    ->query(fn (Builder $q) => $q->whereNotNull('due_at')->where('due_at', '<', now())->where('is_completed', false))
                    ->toggle(),
            ])
            ->actions([EditAction::make(), DeleteAction::make()])
            ->bulkActions([BulkActionGroup::make([DeleteBulkAction::make()])])
            ->defaultSort('due_at', 'asc');
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
            'index' => Pages\ListTasks::route('/'),
            'create' => Pages\CreateTask::route('/create'),
            'edit' => Pages\EditTask::route('/{record}/edit'),
        ];
    }
}
