<?php

namespace App\Filament\App\Resources;

use App\Filament\App\Resources\AppointmentResource\Pages;
use App\Models\Appointment;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AppointmentResource extends Resource
{
    protected static ?string $model = Appointment::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-calendar-days';

    protected static string | \UnitEnum | null $navigationGroup = 'Servicios';

    protected static ?string $navigationLabel = 'Agenda de Citas';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\Section::make('Detalles de la Cita')
                    ->schema([
                        Forms\Components\Select::make('user_id')
                            ->label('Cliente Registrado')
                            ->relationship('user', 'name')
                            ->searchable()
                            ->preload()
                            ->helperText('Dejar vacío para clientes sin cuenta'),

                        Forms\Components\Grid::make(2)
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
                            ->preload(),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\DateTimePicker::make('scheduled_at')
                                    ->label('Hora de Inicio')
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
                                    ->label('Hora de Fin')
                                    ->required()
                                    ->seconds(false),
                            ]),

                        Forms\Components\Select::make('status')
                            ->label('Estado')
                            ->options([
                                'pending' => '⏳ Pendiente',
                                'confirmed' => '✅ Confirmada',
                                'completed' => '🎉 Completada',
                                'cancelled' => '❌ Cancelada',
                            ])
                            ->default('confirmed')
                            ->required()
                            ->native(false),

                        Forms\Components\Textarea::make('staff_notes')
                            ->label('Notas Internas')
                            ->rows(2)
                            ->columnSpanFull(),
                    ])->columns(2),
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
                    ->label('Staff')
                    ->relationship('provider', 'name'),
            ])
            ->actions([
                Tables\Actions\Action::make('confirm')
                    ->label('Confirmar')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn ($record) => $record->status === 'pending')
                    ->action(fn ($record) => $record->confirm()),

                Tables\Actions\Action::make('complete')
                    ->label('Completar')
                    ->icon('heroicon-o-check-circle')
                    ->color('info')
                    ->visible(fn ($record) => $record->status === 'confirmed')
                    ->action(fn ($record) => $record->complete()),

                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
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
