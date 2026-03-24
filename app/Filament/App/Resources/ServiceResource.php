<?php

declare(strict_types=1);

namespace App\Filament\App\Resources;

use App\Domain\TenantGovernance\Enums\Capability;
use App\Filament\App\Resources\ServiceResource\Pages;
use App\Models\Service;
use App\Models\Tenant;
use Filament\Facades\Filament;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

class ServiceResource extends Resource
{
    protected static ?string $model = Service::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-scissors';

    protected static string|\UnitEnum|null $navigationGroup = 'Servicios';

    protected static ?string $navigationLabel = 'Catálogo de servicios';

    protected static ?int $navigationSort = 1;

    public static function canViewAny(): bool
    {
        $tenant = static::getTenantRecord();

        return $tenant instanceof Tenant
            && $tenant->hasCapability(Capability::APPOINTMENT_BOOKING)
            && Gate::allows('viewAny', Service::class);
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
                Section::make('Detalles del Servicio')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nombre del Servicio')
                            ->required()
                            ->maxLength(255),
                        Textarea::make('description')
                            ->label('Descripcion')
                            ->rows(3)
                            ->columnSpanFull(),
                        Grid::make(2)
                            ->schema([
                                TextInput::make('price')
                                    ->label('Precio')
                                    ->numeric()
                                    ->prefix('L.')
                                    ->required(),
                                TextInput::make('duration_minutes')
                                    ->label('Duracion')
                                    ->numeric()
                                    ->suffix('minutos')
                                    ->required()
                                    ->step(5)
                                    ->minValue(5)
                                    ->default(30),
                                TextInput::make('buffer_before')
                                    ->label('Margen Antes (Preparacion)')
                                    ->numeric()
                                    ->suffix('minutos')
                                    ->required()
                                    ->step(5)
                                    ->minValue(0)
                                    ->default(0),
                                TextInput::make('buffer_after')
                                    ->label('Margen Despues (Limpieza)')
                                    ->numeric()
                                    ->suffix('minutos')
                                    ->required()
                                    ->step(5)
                                    ->minValue(0),
                            ]),
                        Toggle::make('is_active')
                            ->label('Servicio Activo')
                            ->default(true),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Servicio')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('formatted_price')
                    ->label('Precio'),
                Tables\Columns\TextColumn::make('formatted_duration')
                    ->label('Duracion')
                    ->badge()
                    ->color('info'),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Estado'),
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
            'index' => Pages\ListServices::route('/'),
            'create' => Pages\CreateService::route('/create'),
            'edit' => Pages\EditService::route('/{record}/edit'),
        ];
    }

    private static function getTenantRecord(): ?Tenant
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Tenant ? $tenant : null;
    }
}
