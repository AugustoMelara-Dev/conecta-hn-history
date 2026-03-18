<?php

declare(strict_types=1);

namespace App\Filament\App\Resources;

use App\Filament\App\Resources\TenantProfileResource\Pages;
use App\Filament\App\Resources\TenantProfileResource\RelationManagers;
use App\Models\Tenant;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TenantProfileResource extends Resource
{
    protected static ?string $model = Tenant::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-storefront';

    protected static string|\UnitEnum|null $navigationGroup = 'Configuracion';

    protected static ?int $navigationSort = 1;

    public static function getNavigationLabel(): string
    {
        return 'Mi Negocio';
    }

    public static function getModelLabel(): string
    {
        return 'Perfil';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Mi Negocio';
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $tenant = static::getTenantRecord();

        if (! $tenant instanceof Tenant) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereKey($tenant->getKey());
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Identidad Visual')
                    ->description('Personaliza como se ve tu marca ante el mundo.')
                    ->schema([
                        Forms\Components\SpatieMediaLibraryFileUpload::make('logo')
                            ->label('Logo del Negocio')
                            ->collection('logo')
                            ->avatar()
                            ->image()
                            ->imageEditor()
                            ->helperText('Formato recomendado: PNG o JPG.'),
                        Forms\Components\SpatieMediaLibraryFileUpload::make('cover')
                            ->label('Imagen de Portada')
                            ->collection('cover')
                            ->image()
                            ->imageEditor()
                            ->columnSpanFull()
                            ->helperText('Resolucion ideal: 1200x400px.'),
                        Forms\Components\ColorPicker::make('primary_color')
                            ->label('Color de Marca')
                            ->helperText('Define el color principal de botones y enlaces.')
                            ->default('#000000'),
                    ])->columns(2),
                Section::make('Informacion Publica')
                    ->description('Datos de contacto visibles para tus clientes.')
                    ->schema([
                        Forms\Components\Textarea::make('description')
                            ->label('Descripcion Comercial')
                            ->rows(3)
                            ->columnSpanFull()
                            ->maxLength(500),
                        Forms\Components\TextInput::make('whatsapp_number')
                            ->label('WhatsApp Business')
                            ->tel()
                            ->telRegex('/^[+]*[(]{0,1}[0-9]{1,4}[)]{0,1}[-\\s\\.\\/0-9]*$/')
                            ->helperText('Incluye codigo de area (ej: 504...).'),
                        Forms\Components\TextInput::make('address')
                            ->label('Direccion Fisica')
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ])->columns(2),
                Section::make('Estado de Suscripcion')
                    ->description('Informacion contractual de tu plan.')
                    ->icon('heroicon-o-shield-check')
                    ->schema([
                        Forms\Components\TextInput::make('id')
                            ->label('ID de Cuenta')
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\TextInput::make('slug')
                            ->label('URL del Micrositio')
                            ->prefix(rtrim((string) config('app.url'), '/').'/negocio/')
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\TextInput::make('plan_type')
                            ->label('Plan Actual')
                            ->formatStateUsing(fn (string $state): string => strtoupper($state))
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\TextInput::make('subscription_status')
                            ->label('Estado')
                            ->disabled()
                            ->dehydrated(false)
                            ->formatStateUsing(fn (string $state): string => match ($state) {
                                'active' => 'ACTIVO',
                                'trial' => 'PERIODO DE PRUEBA',
                                'past_due' => 'PAGO PENDIENTE',
                                'canceled' => 'CANCELADO',
                                default => strtoupper($state),
                            }),
                    ])->columns(2)->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\SpatieMediaLibraryImageColumn::make('logo')
                    ->collection('logo')
                    ->label('')
                    ->circular(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre Comercial')
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('plan_type')
                    ->label('Plan')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'enterprise' => 'purple',
                        'pro' => 'success',
                        'semilla' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),
                Tables\Columns\TextColumn::make('subscription_status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'active' ? 'success' : 'danger'),
            ])
            ->actions([
                EditAction::make()
                    ->label('Editar Perfil')
                    ->modalWidth('4xl'),
            ])
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\OpeningHoursRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTenants::route('/'),
            'edit' => Pages\EditTenant::route('/{record}/edit'),
        ];
    }

    protected static function getTenantRecord(): ?Tenant
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Tenant ? $tenant : null;
    }
}
