<?php

declare(strict_types=1);

namespace App\Filament\App\Resources;

use App\Application\Reservations\CanonicalReservationWriter;
use App\Application\Reservations\Commands\TransitionReservationCommand;
use App\Filament\App\Resources\AppointmentResource\Pages;
use App\Models\Appointment;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Product\TenantAccessMatrix;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
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

    public static function canViewAny(): bool
    {
        return TenantAccessMatrix::currentTenantCanAccess(TenantAccessMatrix::MODULE_APPOINTMENTS);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Servicio y horario')->components([
                Grid::make(2)->components([
                    Select::make('service_id')
                        ->label('Servicio')
                        ->relationship('service', 'name')
                        ->required()
                        ->searchable()
                        ->preload(),
                    Select::make('service_provider_id')
                        ->label('Profesional')
                        ->relationship('provider', 'name')
                        ->required()
                        ->searchable()
                        ->preload(),
                    Select::make('location_id')
                        ->label('Ubicación')
                        ->relationship('location', 'name')
                        ->required()
                        ->searchable()
                        ->preload(),
                    DateTimePicker::make('scheduled_at')
                        ->label('Inicio')
                        ->required()
                        ->seconds(false)
                        ->timezone('America/Tegucigalpa')
                        ->displayFormat('d/m/Y H:i'),
                    Select::make('status')
                        ->label('Estado inicial')
                        ->options([
                            Appointment::STATUS_PENDING => 'Pendiente',
                            Appointment::STATUS_CONFIRMED => 'Confirmada',
                        ])
                        ->default(Appointment::STATUS_PENDING)
                        ->required(),
                ]),
            ]),
            Section::make('Cliente')->components([
                Grid::make(2)->components([
                    TextInput::make('customer_name')->label('Nombre')->required()->maxLength(255),
                    TextInput::make('customer_phone')->label('Teléfono')->required()->tel()->maxLength(40),
                    TextInput::make('customer_email')->label('Correo')->required()->email()->maxLength(255),
                    Select::make('user_id')->label('Usuario vinculado')->relationship('user', 'name')->searchable()->preload(),
                    Textarea::make('customer_notes')->label('Notas')->rows(3)->columnSpanFull(),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('customer_name')->label('Cliente')->searchable()->sortable(),
                TextColumn::make('service_name_snapshot')->label('Servicio')->searchable(),
                TextColumn::make('provider.name')->label('Profesional'),
                TextColumn::make('scheduled_at')->label('Inicio')->dateTime('d/m/Y H:i')->timezone('America/Tegucigalpa')->sortable(),
                TextColumn::make('end_at')->label('Fin')->dateTime('d/m/Y H:i')->timezone('America/Tegucigalpa'),
                TextColumn::make('status')->label('Estado')->badge()->color(fn (string $state): string => match ($state) {
                    Appointment::STATUS_PENDING => 'warning',
                    Appointment::STATUS_CONFIRMED => 'info',
                    Appointment::STATUS_COMPLETED => 'success',
                    Appointment::STATUS_CANCELLED => 'danger',
                    Appointment::STATUS_NO_SHOW => 'gray',
                    default => 'gray',
                }),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    Appointment::STATUS_PENDING => 'Pendiente',
                    Appointment::STATUS_CONFIRMED => 'Confirmada',
                    Appointment::STATUS_COMPLETED => 'Completada',
                    Appointment::STATUS_CANCELLED => 'Cancelada',
                    Appointment::STATUS_NO_SHOW => 'No se presentó',
                ]),
            ])
            ->actions([
                Action::make('confirm')
                    ->label('Confirmar')
                    ->icon('heroicon-o-check-circle')
                    ->visible(fn (Appointment $record): bool => $record->status === Appointment::STATUS_PENDING)
                    ->action(fn (Appointment $record) => self::transition($record, Appointment::STATUS_CONFIRMED)),
                Action::make('complete')
                    ->label('Completar')
                    ->icon('heroicon-o-check')
                    ->visible(fn (Appointment $record): bool => $record->status === Appointment::STATUS_CONFIRMED)
                    ->requiresConfirmation()
                    ->action(fn (Appointment $record) => self::transition($record, Appointment::STATUS_COMPLETED)),
                Action::make('cancel')
                    ->label('Cancelar')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Appointment $record): bool => in_array($record->status, [Appointment::STATUS_PENDING, Appointment::STATUS_CONFIRMED], true))
                    ->requiresConfirmation()
                    ->action(fn (Appointment $record) => self::transition($record, Appointment::STATUS_CANCELLED, 'Cancelada desde la agenda operativa')),
            ])
            ->emptyStateHeading('No tienes citas programadas')
            ->emptyStateActions([CreateAction::make()->label('Crear primera cita')])
            ->defaultSort('scheduled_at');
    }

    public static function getEloquentQuery(): Builder
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Tenant
            ? parent::getEloquentQuery()->where('tenant_id', $tenant->getKey())
            : parent::getEloquentQuery()->whereRaw('1 = 0');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAppointments::route('/'),
            'create' => Pages\CreateAppointment::route('/create'),
        ];
    }

    private static function transition(Appointment $appointment, string $status, ?string $reason = null): void
    {
        $tenant = Filament::getTenant();
        $actor = Filament::auth()->user();
        if (! $tenant instanceof Tenant || ! $actor instanceof User) {
            throw new \RuntimeException('Appointment transition requires tenant and actor context.');
        }
        $command = new TransitionReservationCommand(
            tenantId: (string) $tenant->getKey(),
            actorId: (string) $actor->getKey(),
            appointmentId: (int) $appointment->getKey(),
            targetStatus: $status,
            reason: $reason,
        );
        $writer = app(CanonicalReservationWriter::class);
        $status === Appointment::STATUS_CANCELLED ? $writer->cancel($command) : $writer->transition($command);
    }
}
