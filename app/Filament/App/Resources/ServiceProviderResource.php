<?php

declare(strict_types=1);

namespace App\Filament\App\Resources;

use App\Domain\TenantGovernance\Enums\Capability;
use App\Filament\App\Resources\ServiceProviderResource\Pages;
use App\Models\ServiceProvider;
use App\Models\Tenant;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class ServiceProviderResource extends Resource
{
    protected static ?string $model = ServiceProvider::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static string|\UnitEnum|null $navigationGroup = 'Servicios';

    protected static ?string $navigationLabel = 'Personal / Staff';

    protected static ?int $navigationSort = 2;

    public static function canViewAny(): bool
    {
        $tenant = static::getTenantRecord();

        return $tenant?->hasCapability(Capability::STAFF_CALENDAR) ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Informacion del Personal')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nombre Completo')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->label('Correo Electronico')
                            ->email()
                            ->maxLength(255),
                        TextInput::make('phone')
                            ->label('Telefono')
                            ->tel(),
                        Toggle::make('is_active')
                            ->label('Disponible para Reservas')
                            ->default(true),
                    ])->columns(2),
                Section::make('Horario Personalizado')
                    ->description('Opcional: define un horario especifico para este miembro.')
                    ->icon('heroicon-o-clock')
                    ->schema([
                        KeyValue::make('schedule_config')
                            ->label('')
                            ->keyLabel('Dia (en ingles)')
                            ->valueLabel('Horario (ej: 09:00-17:00)')
                            ->keyPlaceholder('monday')
                            ->valuePlaceholder('09:00-17:00')
                            ->reorderable()
                            ->addActionLabel('Agregar Dia')
                            ->helperText('Dias validos: monday, tuesday, wednesday, thursday, friday, saturday, sunday.'),
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
                    ->label('Telefono')
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
                EditAction::make(),
                DeleteAction::make(),
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
            'index' => Pages\ListServiceProviders::route('/'),
            'create' => Pages\CreateServiceProvider::route('/create'),
            'edit' => Pages\EditServiceProvider::route('/{record}/edit'),
        ];
    }

    protected static function getTenantRecord(): ?Tenant
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Tenant ? $tenant : null;
    }
}
