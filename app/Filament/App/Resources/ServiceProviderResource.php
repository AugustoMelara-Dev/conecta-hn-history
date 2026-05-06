<?php

namespace App\Filament\App\Resources;

use App\Filament\App\Resources\ServiceProviderResource\Pages;
use App\Models\ServiceProvider;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ServiceProviderResource extends Resource
{
    protected static ?string $model = ServiceProvider::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = 'Servicios';

    protected static ?string $navigationLabel = 'Personal / Staff';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Información del Personal')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nombre Completo')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('email')
                            ->label('Correo Electrónico')
                            ->email()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('phone')
                            ->label('Teléfono')
                            ->tel(),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Disponible para Reservas')
                            ->default(true),
                    ])->columns(2),

                // ═══════════════════════════════════════════════════════════════
                // SCHEDULE OVERRIDE - Provider-specific availability
                // ═══════════════════════════════════════════════════════════════
                Forms\Components\Section::make('Horario Personalizado')
                    ->description('Opcional: Define un horario específico para este miembro. Si está vacío, se usará el horario general del negocio.')
                    ->icon('heroicon-o-clock')
                    ->schema([
                        Forms\Components\KeyValue::make('schedule_config')
                            ->label('')
                            ->keyLabel('Día (en inglés)')
                            ->valueLabel('Horario (ej: 09:00-17:00)')
                            ->keyPlaceholder('monday')
                            ->valuePlaceholder('09:00-17:00')
                            ->reorderable()
                            ->addActionLabel('Agregar Día')
                            ->helperText('Días válidos: monday, tuesday, wednesday, thursday, friday, saturday, sunday. Formato de hora: HH:MM-HH:MM'),
                    ])
                    ->collapsed()
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->icon('heroicon-o-envelope'),

                Tables\Columns\TextColumn::make('phone')
                    ->label('Teléfono')
                    ->icon('heroicon-o-phone'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Disponible')
                    ->boolean(),

                Tables\Columns\TextColumn::make('appointments_count')
                    ->label('Citas')
                    ->counts('appointments')
                    ->badge(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Disponibilidad'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
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
            'index' => Pages\ListServiceProviders::route('/'),
            'create' => Pages\CreateServiceProvider::route('/create'),
            'edit' => Pages\EditServiceProvider::route('/{record}/edit'),
        ];
    }
}
