<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Crm;

use App\Domain\Crm\Enums\ActivityType;
use App\Filament\App\Resources\Crm\ActivityResource\Pages;
use App\Models\CrmActivity;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ActivityResource extends Resource
{
    protected static ?string $model = CrmActivity::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationGroup = 'CRM';

    protected static ?string $navigationLabel = 'Actividades';

    protected static ?string $modelLabel = 'actividad';

    protected static ?string $pluralModelLabel = 'Actividades';

    protected static ?int $navigationSort = 13;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Detalles')
                    ->components([
                        Grid::make(2)
                            ->components([
                                Select::make('type')
                                    ->label('Tipo')
                                    ->options(ActivityType::options())
                                    ->required()
                                    ->native(false)
                                    ->live(),
                                TextInput::make('subject')
                                    ->label('Asunto')
                                    ->maxLength(200),
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
                                Select::make('owner_id')
                                    ->label('Responsable')
                                    ->relationship('owner', 'name')
                                    ->searchable()
                                    ->preload(),
                                DateTimePicker::make('due_at')
                                    ->label('Fecha límite')
                                    ->timezone('America/Tegucigalpa')
                                    ->displayFormat('d/m/Y H:i'),
                                Select::make('priority')
                                    ->label('Prioridad')
                                    ->options([1 => 'Alta', 2 => 'Media', 3 => 'Baja'])
                                    ->default(3),
                            ]),
                        Textarea::make('body')
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
                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ActivityType::tryFrom($state)?->label() ?? $state)
                    ->color(fn (string $state): string => ActivityType::tryFrom($state)?->color() ?? 'gray'),
                TextColumn::make('subject')->label('Asunto')->searchable()->placeholder('—'),
                TextColumn::make('contact.display_name')->label('Contacto')->placeholder('—'),
                TextColumn::make('deal.name')->label('Deal')->placeholder('—'),
                TextColumn::make('owner.name')->label('Responsable')->placeholder('—'),
                TextColumn::make('due_at')->label('Vence')->dateTime('d/m/Y H:i')->placeholder('—')->sortable(),
                TextColumn::make('completed_at')->label('Completada')->dateTime('d/m/Y H:i')->placeholder('Pendiente'),
            ])
            ->filters([
                SelectFilter::make('type')->label('Tipo')->options(ActivityType::options()),
                SelectFilter::make('owner_id')->label('Responsable')->relationship('owner', 'name'),
                Tables\Filters\Filter::make('overdue')
                    ->label('Vencidas')
                    ->query(fn (Builder $query) => $query->overdue())
                    ->toggle(),
                Tables\Filters\Filter::make('pending')
                    ->label('Pendientes')
                    ->query(fn (Builder $query) => $query->pending())
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

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListActivities::route('/'),
            'create' => Pages\CreateActivity::route('/create'),
            'edit' => Pages\EditActivity::route('/{record}/edit'),
        ];
    }
}
