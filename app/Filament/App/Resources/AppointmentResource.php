<?php

namespace App\Filament\App\Resources;

use App\Filament\App\Resources\AppointmentResource\Pages;
use App\Models\Appointment;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AppointmentResource extends Resource
{
    protected static ?string $model = Appointment::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationGroup = 'Servicios';

    protected static ?string $navigationLabel = 'Agenda';

    protected static ?int $navigationSort = 3;

    public static function getModelLabel(): string
    {
        return 'cita';
    }

    public static function getPluralModelLabel(): string
    {
        return 'citas';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Detalles de la cita')
                    ->description('Registra el servicio, la persona que atiende y el horario confirmado para el cliente.')
                    ->schema([
                        Forms\Components\Select::make('user_id')
                            ->label('Cliente registrado')
                            ->relationship('user', 'name')
                            ->searchable()
                            ->preload()
                            ->helperText('Opcional. Déjalo vacío si el cliente no tiene cuenta.'),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('customer_name')
                                    ->label('Nombre del cliente')
                                    ->placeholder('Ej. María López')
                                    ->maxLength(255),

                                Forms\Components\TextInput::make('customer_phone')
                                    ->label('Teléfono')
                                    ->tel()
                                    ->placeholder('Ej. 50499999999'),
                            ]),

                        Forms\Components\Select::make('service_id')
                            ->label('Servicio')
                            ->relationship('service', 'name')
                            ->required()
                            ->preload()
                            ->reactive()
                            ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                if ($state && $get('scheduled_at')) {
                                    $service = \App\Models\Service::find($state);
                                    if ($service) {
                                        $start = \Carbon\Carbon::parse($get('scheduled_at'));
                                        $set('end_at', $start->copy()->addMinutes($service->duration_minutes)->format('Y-m-d H:i:s'));
                                    }
                                }
                            }),

                        Forms\Components\Select::make('service_provider_id')
                            ->label('Atendido por')
                            ->relationship('provider', 'name')
                            ->required()
                            ->preload()
                            ->helperText('Selecciona quién prestará el servicio.'),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\DateTimePicker::make('scheduled_at')
                                    ->label('Inicio')
                                    ->required()
                                    ->seconds(false)
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                        if ($state && $get('service_id')) {
                                            $service = \App\Models\Service::find($get('service_id'));
                                            if ($service) {
                                                $start = \Carbon\Carbon::parse($state);
                                                $set('end_at', $start->copy()->addMinutes($service->duration_minutes)->format('Y-m-d H:i:s'));
                                            }
                                        }
                                    }),

                                Forms\Components\DateTimePicker::make('end_at')
                                    ->label('Fin')
                                    ->required()
                                    ->seconds(false),
                            ]),

                        Forms\Components\Select::make('status')
                            ->label('Estado')
                            ->options([
                                'pending' => 'Pendiente',
                                'confirmed' => 'Confirmada',
                                'completed' => 'Completada',
                                'cancelled' => 'Cancelada',
                            ])
                            ->default('confirmed')
                            ->required()
                            ->native(false),

                        Forms\Components\Textarea::make('staff_notes')
                            ->label('Notas internas')
                            ->placeholder('Indicaciones para el equipo. No se muestran al cliente.')
                            ->rows(2)
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->emptyStateIcon('heroicon-o-calendar-days')
            ->emptyStateHeading('Aún no hay citas')
            ->emptyStateDescription('Cuando un cliente reserve un servicio, lo verás aquí con fecha, persona asignada y estado.')
            ->emptyStateActions([
                Tables\Actions\CreateAction::make()
                    ->label('Crear cita'),
            ])
            ->columns([
                Tables\Columns\TextColumn::make('scheduled_at')
                    ->label('Fecha y hora')
                    ->dateTime('D d M, h:i A')
                    ->sortable(),

                Tables\Columns\TextColumn::make('customer_display_name')
                    ->label('Cliente')
                    ->searchable(['customer_name']),

                Tables\Columns\TextColumn::make('service.name')
                    ->label('Servicio')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('provider.name')
                    ->label('Atiende')
                    ->placeholder('Sin asignar'),

                Tables\Columns\TextColumn::make('status_label')
                    ->label('Estado')
                    ->badge()
                    ->color(fn ($record) => $record->status_color),
            ])
            ->defaultSort('scheduled_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'pending' => 'Pendiente',
                        'confirmed' => 'Confirmada',
                        'completed' => 'Completada',
                        'cancelled' => 'Cancelada',
                    ]),

                Tables\Filters\SelectFilter::make('service_provider_id')
                    ->label('Atiende')
                    ->relationship('provider', 'name'),
            ])
            ->actions([
                Tables\Actions\Action::make('confirm')
                    ->label('Confirmar')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn ($record) => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->modalHeading('Confirmar cita')
                    ->modalDescription('La cita pasará a estado confirmada.')
                    ->action(fn ($record) => $record->confirm()),

                Tables\Actions\Action::make('complete')
                    ->label('Completar')
                    ->icon('heroicon-o-check-circle')
                    ->color('info')
                    ->visible(fn ($record) => $record->status === 'confirmed')
                    ->requiresConfirmation()
                    ->modalHeading('Marcar cita como completada')
                    ->modalDescription('Usa esta acción solo cuando el servicio ya fue atendido.')
                    ->action(fn ($record) => $record->complete()),

                Tables\Actions\EditAction::make()
                    ->label('Editar'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->label('Eliminar seleccionadas'),
                ]),
            ]);
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
