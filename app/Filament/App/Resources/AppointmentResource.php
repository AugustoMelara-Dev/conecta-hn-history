<?php

declare(strict_types=1);

namespace App\Filament\App\Resources;

use App\Application\Reservations\CanonicalReservationWriter;
use App\Application\Reservations\Commands\TransitionReservationCommand;
use App\Filament\App\Resources\AppointmentResource\Pages;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Product\TenantAccessMatrix;
use Carbon\Carbon;
use Filament\Facades\Filament;
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

    public static function canViewAny(): bool
    {
        return TenantAccessMatrix::currentTenantCanAccess(TenantAccessMatrix::MODULE_APPOINTMENTS);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
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
                                    ->required()
                                    ->placeholder('Ej. María López')
                                    ->maxLength(255),

                                Forms\Components\TextInput::make('customer_phone')
                                    ->required()
                                    ->label('Teléfono')
                                    ->tel()
                                    ->placeholder('Ej. 50499999999'),

                                Forms\Components\TextInput::make('customer_email')
                                    ->label('Correo')
                                    ->required()
                                    ->email(),
                            ]),

                        Forms\Components\Select::make('service_id')
                            ->label('Servicio')
                            ->relationship('service', 'name')
                            ->required()
                            ->preload()
                            ->reactive()
                            ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                if ($state && $get('scheduled_at')) {
                                    $service = Service::find($state);
                                    if ($service) {
                                        $start = Carbon::parse($get('scheduled_at'));
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

                        Forms\Components\Select::make('location_id')
                            ->label('Ubicación')
                            ->relationship('location', 'name')
                            ->required()
                            ->preload(),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\DateTimePicker::make('scheduled_at')
                                    ->label('Inicio')
                                    ->required()
                                    ->seconds(false)
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                        if ($state && $get('service_id')) {
                                            $service = Service::find($get('service_id'));
                                            if ($service) {
                                                $start = Carbon::parse($state);
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
                        'no_show' => 'No se presentó',
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
                    ->action(fn (Appointment $record) => self::transition(
                        $record,
                        Appointment::STATUS_CONFIRMED,
                    )),

                Tables\Actions\Action::make('complete')
                    ->label('Completar')
                    ->icon('heroicon-o-check-circle')
                    ->color('info')
                    ->visible(fn ($record) => $record->status === 'confirmed')
                    ->requiresConfirmation()
                    ->modalHeading('Marcar cita como completada')
                    ->modalDescription('Usa esta acción solo cuando el servicio ya fue atendido.')
                    ->action(fn (Appointment $record) => self::transition(
                        $record,
                        Appointment::STATUS_COMPLETED,
                    )),

                Tables\Actions\Action::make('no_show')
                    ->label('No se presentó')
                    ->visible(fn (Appointment $record): bool => $record->status === Appointment::STATUS_CONFIRMED)
                    ->requiresConfirmation()
                    ->action(fn (Appointment $record) => self::transition(
                        $record,
                        Appointment::STATUS_NO_SHOW,
                        'Customer did not arrive',
                    )),

                Tables\Actions\Action::make('cancel')
                    ->label('Cancelar')
                    ->color('danger')
                    ->visible(fn (Appointment $record): bool => in_array(
                        $record->status,
                        [Appointment::STATUS_PENDING, Appointment::STATUS_CONFIRMED],
                        true,
                    ))
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label('Motivo')
                            ->required(),
                    ])
                    ->action(fn (Appointment $record, array $data) => self::transition(
                        $record,
                        Appointment::STATUS_CANCELLED,
                        (string) $data['reason'],
                    )),
            ])
            ->bulkActions([]);
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
        ];
    }

    private static function transition(
        Appointment $appointment,
        string $targetStatus,
        ?string $reason = null,
    ): void {
        $tenant = Filament::getTenant();
        $actor = auth()->user();
        if (! $tenant instanceof Tenant || ! $actor instanceof User) {
            throw new \RuntimeException('Authenticated tenant context is required.');
        }
        $command = new TransitionReservationCommand(
            tenantId: (string) $tenant->getKey(),
            actorId: (string) $actor->getKey(),
            appointmentId: (int) $appointment->getKey(),
            targetStatus: $targetStatus,
            reason: $reason,
        );
        $writer = app(CanonicalReservationWriter::class);
        if ($targetStatus === Appointment::STATUS_CANCELLED) {
            $writer->cancel($command);

            return;
        }
        $writer->transition($command);
    }
}
