<?php

declare(strict_types=1);

namespace App\Filament\App\Resources;

use App\Domain\TenantGovernance\Enums\Capability;
use App\Filament\App\Resources\AppointmentResource\Pages;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\Tenant;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

class AppointmentResource extends Resource
{
    protected static ?string $model = Appointment::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static string|\UnitEnum|null $navigationGroup = 'Servicios';

    protected static ?string $navigationLabel = 'Agenda de citas';

    protected static ?int $navigationSort = 3;

    public static function canViewAny(): bool
    {
        $tenant = static::getTenantRecord();

        return $tenant instanceof Tenant
            && $tenant->hasCapability(Capability::APPOINTMENT_BOOKING)
            && Gate::allows('viewAny', Appointment::class);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function canAccess(): bool
    {
        return static::canViewAny();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Detalles de la cita')
                    ->schema([
                        Forms\Components\Select::make('user_id')
                            ->label('Cliente registrado')
                            ->relationship('user', 'name')
                            ->searchable()
                            ->preload()
                            ->helperText('Dejar vacío para clientes sin cuenta'),

                        Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('customer_name')
                                    ->label('Nombre (Walk-in)')
                                    ->maxLength(255),

                                Forms\Components\TextInput::make('customer_phone')
                                    ->label('Teléfono')
                                    ->tel(),
                            ]),

                        Forms\Components\Select::make('service_id')
                            ->label('Servicio')
                            ->relationship('service', 'name')
                            ->required()
                            ->preload()
                            ->reactive()
                            ->afterStateUpdated(function ($state, Set $set, Get $get): void {
                                if (! $state || ! $get('scheduled_at')) {
                                    return;
                                }

                                $service = Service::find($state);
                                if (! $service instanceof Service) {
                                    return;
                                }

                                $start = Carbon::parse((string) $get('scheduled_at'));
                                $set('end_at', $start->copy()->addMinutes($service->total_duration)->format('Y-m-d H:i:s'));
                            }),

                        Forms\Components\Select::make('service_provider_id')
                            ->label('Atendido por')
                            ->relationship('provider', 'name')
                            ->required()
                            ->preload(),

                        Grid::make(2)
                            ->schema([
                                Forms\Components\DateTimePicker::make('scheduled_at')
                                    ->label('Hora de inicio')
                                    ->required()
                                    ->seconds(false)
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, Set $set, Get $get): void {
                                        if (! $state || ! $get('service_id')) {
                                            return;
                                        }

                                        $service = Service::find($get('service_id'));
                                        if (! $service instanceof Service) {
                                            return;
                                        }

                                        $start = Carbon::parse((string) $state);
                                        $set('end_at', $start->copy()->addMinutes($service->total_duration)->format('Y-m-d H:i:s'));
                                    }),

                                Forms\Components\DateTimePicker::make('end_at')
                                    ->label('Hora de fin')
                                    ->required()
                                    ->seconds(false),
                            ]),

                        Forms\Components\Select::make('status')
                            ->label('Estado')
                            ->options([
                                Appointment::STATUS_PENDING => 'Pendiente',
                                Appointment::STATUS_CONFIRMED => 'Confirmada',
                                Appointment::STATUS_COMPLETED => 'Completada',
                                Appointment::STATUS_CANCELLED => 'Cancelada',
                            ])
                            ->default(Appointment::STATUS_CONFIRMED)
                            ->required()
                            ->native(false),

                        Forms\Components\Textarea::make('staff_notes')
                            ->label('Notas internas')
                            ->rows(2)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('scheduled_at')
                    ->label('Fecha/Hora')
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
                    ->label('Staff'),

                Tables\Columns\TextColumn::make('status_label')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (Appointment $record): string => $record->status_color),
            ])
            ->defaultSort('scheduled_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        Appointment::STATUS_PENDING => 'Pendiente',
                        Appointment::STATUS_CONFIRMED => 'Confirmada',
                        Appointment::STATUS_COMPLETED => 'Completada',
                        Appointment::STATUS_CANCELLED => 'Cancelada',
                    ]),

                Tables\Filters\SelectFilter::make('service_provider_id')
                    ->label('Staff')
                    ->relationship('provider', 'name'),
            ])
            ->actions([
                Action::make('confirm')
                    ->label('Confirmar')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (Appointment $record): bool => $record->status === Appointment::STATUS_PENDING)
                    ->action(fn (Appointment $record): bool => $record->confirm()),

                Action::make('complete')
                    ->label('Completar')
                    ->icon('heroicon-o-check-circle')
                    ->color('info')
                    ->visible(fn (Appointment $record): bool => $record->status === Appointment::STATUS_CONFIRMED)
                    ->action(fn (Appointment $record): bool => $record->complete()),

                Action::make('no_show')
                    ->label('No asistió')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Appointment $record): bool => in_array($record->status, [Appointment::STATUS_PENDING, Appointment::STATUS_CONFIRMED], true))
                    ->requiresConfirmation()
                    ->action(fn (Appointment $record): bool => $record->markAsNoShow()),

                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
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

    protected static function getTenantRecord(): ?Tenant
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Tenant ? $tenant : null;
    }
}
