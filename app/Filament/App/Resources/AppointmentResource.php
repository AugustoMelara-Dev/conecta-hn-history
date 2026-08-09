<?php

declare(strict_types=1);

namespace App\Filament\App\Resources;

use App\Filament\App\Resources\AppointmentResource\Pages;
use App\Models\Appointment;
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
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AppointmentResource extends Resource
{
    protected static ?string $model = Appointment::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static string|\UnitEnum|null $navigationGroup = 'Operaciones';

    protected static ?string $navigationLabel = 'Citas';

    protected static ?string $modelLabel = 'cita';

    protected static ?string $pluralModelLabel = 'Citas';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Datos de la cita')
                    ->description('Información principal de la cita agendada.')
                    ->components([
                        TextInput::make('title')
                            ->label('Título')
                            ->required()
                            ->maxLength(255),

                        Select::make('status')
                            ->label('Estado')
                            ->required()
                            ->options([
                                'pending' => 'Pendiente',
                                'confirmed' => 'Confirmada',
                                'cancelled' => 'Cancelada',
                                'completed' => 'Completada',
                            ])
                            ->default('pending'),
                    ]),

                Section::make('Programación')
                    ->components([
                        Grid::make(2)
                            ->components([
                                DateTimePicker::make('starts_at')
                                    ->label('Inicio')
                                    ->required()
                                    ->timezone('America/Tegucigalpa')
                                    ->displayFormat('d/m/Y H:i'),

                                DateTimePicker::make('ends_at')
                                    ->label('Fin')
                                    ->required()
                                    ->timezone('America/Tegucigalpa')
                                    ->displayFormat('d/m/Y H:i')
                                    ->after('starts_at'),

                                Select::make('customer_id')
                                    ->label('Cliente')
                                    ->relationship('customer', 'name')
                                    ->searchable()
                                    ->preload(),

                                Select::make('service_id')
                                    ->label('Servicio')
                                    ->relationship('service', 'name')
                                    ->searchable()
                                    ->preload(),

                                Textarea::make('notes')
                                    ->label('Notas')
                                    ->rows(3)
                                    ->columnSpanFull(),

                                Toggle::make('is_confirmed')
                                    ->label('Confirmada')
                                    ->default(false),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('Título')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('customer.name')
                    ->label('Cliente')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('service.name')
                    ->label('Servicio')
                    ->searchable(),

                TextColumn::make('starts_at')
                    ->label('Inicio')
                    ->dateTime('d/m/Y H:i')
                    ->timezone('America/Tegucigalpa')
                    ->sortable(),

                TextColumn::make('ends_at')
                    ->label('Fin')
                    ->dateTime('d/m/Y H:i')
                    ->timezone('America/Tegucigalpa')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'confirmed' => 'success',
                        'cancelled' => 'danger',
                        'completed' => 'info',
                        default => 'gray',
                    }),

                ToggleColumn::make('is_confirmed')
                    ->label('Confirmada'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'pending' => 'Pendiente',
                        'confirmed' => 'Confirmada',
                        'cancelled' => 'Cancelada',
                        'completed' => 'Completada',
                    ]),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No tienes citas programadas')
            ->emptyStateDescription('Crea una cita para empezar a gestionar tu agenda.')
            ->emptyStateIcon('heroicon-o-calendar-days')
            ->emptyStateActions([
                CreateAction::make()
                    ->label('Crear primera cita')
                    ->icon('heroicon-o-plus'),
            ])
            ->defaultSort('starts_at', 'asc');
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $tenant = Filament::getTenant();

        if (! $tenant) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('business_id', $tenant->getKey());
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAppointments::route('/'),
            'create' => Pages\CreateAppointment::route('/create'),
            'edit' => Pages\EditAppointment::route('/{record}/edit'),
        ];
    }
}
