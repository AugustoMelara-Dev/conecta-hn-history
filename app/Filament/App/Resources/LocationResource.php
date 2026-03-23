<?php

declare(strict_types=1);

namespace App\Filament\App\Resources;

use App\Filament\App\Resources\LocationResource\Pages;
use App\Models\Location;
use App\Models\Tenant;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Unique;

class LocationResource extends Resource
{
    protected static ?string $model = Location::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-map-pin';

    public static function canViewAny(): bool
    {
        return Gate::allows('viewAny', Location::class);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function getNavigationLabel(): string
    {
        return 'Sucursales';
    }

    public static function getModelLabel(): string
    {
        return 'Sucursal';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Sucursales';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Configuracion';
    }

    public static function getNavigationSort(): ?int
    {
        return 45;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Informacion de la sucursal')
                    ->icon('heroicon-o-map')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (?string $state, callable $set) => $set('slug', Str::slug((string) $state))),

                        TextInput::make('slug')
                            ->label('Slug')
                            ->required()
                            ->maxLength(255)
                            ->alphaDash()
                            ->unique(
                                table: 'locations',
                                column: 'slug',
                                ignoreRecord: true,
                                modifyRuleUsing: function (Unique $rule): Unique {
                                    $tenantId = self::getTenantRecord()?->getKey();

                                    return $tenantId
                                        ? $rule->where('tenant_id', $tenantId)
                                        : $rule;
                                }
                            )
                            ->helperText('Se usa para URLs y resolucion amigable de la sucursal.'),

                        TextInput::make('contact_name')
                            ->label('Contacto')
                            ->maxLength(255),

                        TextInput::make('phone')
                            ->label('Telefono')
                            ->tel()
                            ->maxLength(50),

                        Textarea::make('address')
                            ->label('Direccion')
                            ->rows(3)
                            ->columnSpanFull(),

                        Toggle::make('is_active')
                            ->label('Activa')
                            ->default(true)
                            ->disabled(fn (Get $get): bool => (bool) $get('is_primary'))
                            ->helperText('La sucursal primaria debe permanecer activa.'),

                        Toggle::make('is_primary')
                            ->label('Sucursal primaria')
                            ->helperText('Al guardar, esta sucursal se convertira en la primaria y las demas se desmarcaran.'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query): Builder {
                $tenant = self::getTenantRecord();

                if (! $tenant instanceof Tenant) {
                    return $query->whereRaw('1 = 0');
                }

                return $query
                    ->where('tenant_id', (string) $tenant->getKey())
                    ->withCount('orders')
                    ->orderByDesc('is_primary')
                    ->orderBy('name');
            })
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Sucursal')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (Location $record): ?string => $record->address),

                Tables\Columns\TextColumn::make('slug')
                    ->label('Slug')
                    ->badge()
                    ->color('gray')
                    ->copyable(),

                Tables\Columns\TextColumn::make('phone')
                    ->label('Telefono')
                    ->toggleable(),

                Tables\Columns\IconColumn::make('is_primary')
                    ->label('Principal')
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Activa')
                    ->boolean(),

                Tables\Columns\TextColumn::make('orders_count')
                    ->label('Pedidos')
                    ->badge()
                    ->color('info'),
            ])
            ->filters([
                Filter::make('is_active')
                    ->label('Activas')
                    ->query(fn (Builder $query): Builder => $query->where('is_active', true)),
                Filter::make('is_primary')
                    ->label('Solo primaria')
                    ->query(fn (Builder $query): Builder => $query->where('is_primary', true)),
            ])
            ->actions([
                EditAction::make()
                    ->iconButton(),
                DeleteAction::make()
                    ->iconButton()
                    ->visible(fn (Location $record): bool => ! $record->is_primary),
            ])
            ->emptyState(fn () => view('filament.app.components.table-empty-state', [
                'heading' => 'Todavia no has creado sucursales',
                'description' => 'Crea y organiza las sedes desde donde operas pedidos, pickup y atencion.',
                'buttonLabel' => 'Crear sucursal',
                'buttonUrl' => static::getUrl('create'),
            ]));
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLocations::route('/'),
            'create' => Pages\CreateLocation::route('/create'),
            'edit' => Pages\EditLocation::route('/{record}/edit'),
        ];
    }

    private static function getTenantRecord(): ?Tenant
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Tenant ? $tenant : null;
    }
}
