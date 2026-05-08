<?php

declare(strict_types=1);

namespace App\Filament\App\Resources;

use App\Filament\App\Resources\TenantProfileResource\Pages;
use App\Filament\App\Resources\TenantProfileResource\RelationManagers;
use App\Models\Tenant;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TenantProfileResource extends Resource
{
    protected static ?string $model = Tenant::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationGroup = 'Configuracion';

    protected static ?int $navigationSort = 1;

    protected static bool $isScopedToTenant = false;

    public static function getNavigationLabel(): string
    {
        return 'Mi negocio';
    }

    public static function getModelLabel(): string
    {
        return 'perfil del negocio';
    }

    public static function getPluralModelLabel(): string
    {
        return 'perfil del negocio';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('id', Filament::getTenant()?->id);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Identidad visual')
                    ->description('Elementos publicos que ayudan a reconocer tu negocio.')
                    ->schema([
                        Forms\Components\FileUpload::make('logo_path')
                            ->label('Logo')
                            ->avatar()
                            ->image()
                            ->imageEditor()
                            ->directory('tenants/logos')
                            ->helperText('Formato recomendado: PNG o JPG cuadrado.'),

                        Forms\Components\FileUpload::make('cover_path')
                            ->label('Imagen de portada')
                            ->image()
                            ->imageEditor()
                            ->directory('tenants/covers')
                            ->columnSpanFull()
                            ->helperText('Resolucion recomendada: 1200 x 400 px.'),

                        Forms\Components\ColorPicker::make('primary_color')
                            ->label('Color principal')
                            ->helperText('Se aplica a botones y enlaces del sitio publico.')
                            ->default('#000000'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Informacion publica')
                    ->description('Datos que los clientes usan para entender y contactar tu negocio.')
                    ->schema([
                        Forms\Components\Textarea::make('description')
                            ->label('Descripcion comercial')
                            ->rows(3)
                            ->columnSpanFull()
                            ->maxLength(500),

                        Forms\Components\TextInput::make('whatsapp_number')
                            ->label('WhatsApp')
                            ->tel()
                            ->telRegex('/^[+]*[(]{0,1}[0-9]{1,4}[)]{0,1}[-\s\.\/0-9]*$/')
                            ->helperText('Incluye codigo de pais cuando aplique. Ejemplo: 50499999999.'),

                        Forms\Components\TextInput::make('address')
                            ->label('Direccion')
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Plan y cuenta')
                    ->description('Informacion de lectura. Los cambios de plan se gestionan desde billing.')
                    ->icon('heroicon-o-shield-check')
                    ->schema([
                        Forms\Components\TextInput::make('id')
                            ->label('ID de cuenta')
                            ->disabled()
                            ->dehydrated(false),

                        Forms\Components\TextInput::make('slug')
                            ->label('URL del micrositio')
                            ->prefix(rtrim((string) config('app.url'), '/').'/')
                            ->disabled()
                            ->dehydrated(false),

                        Forms\Components\TextInput::make('plan_type')
                            ->label('Plan actual')
                            ->formatStateUsing(fn (?string $state): string => $state ? ucfirst($state) : 'Sin plan')
                            ->disabled()
                            ->dehydrated(false),

                        Forms\Components\TextInput::make('subscription_status')
                            ->label('Estado de suscripcion')
                            ->disabled()
                            ->dehydrated(false)
                            ->formatStateUsing(fn (?string $state): string => match ($state) {
                                'active' => 'Activo',
                                'trial' => 'Periodo de prueba',
                                'past_due' => 'Pago pendiente',
                                'canceled' => 'Cancelado',
                                default => $state ? ucfirst($state) : 'Sin estado',
                            }),
                    ])
                    ->columns(2)
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->emptyStateHeading('No hay perfil disponible')
            ->emptyStateDescription('El panel solo muestra el negocio activo de la sesion.')
            ->columns([
                Tables\Columns\ImageColumn::make('logo_path')
                    ->label('Logo')
                    ->circular(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre comercial')
                    ->weight('bold')
                    ->searchable(),

                Tables\Columns\TextColumn::make('plan_type')
                    ->label('Plan')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'enterprise' => 'purple',
                        'pro' => 'success',
                        'semilla' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (?string $state): string => $state ? ucfirst($state) : 'Sin plan'),

                Tables\Columns\TextColumn::make('subscription_status')
                    ->label('Suscripcion')
                    ->badge()
                    ->color(fn (?string $state): string => $state === 'active' ? 'success' : 'warning')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'active' => 'Activa',
                        'trial' => 'Prueba',
                        'past_due' => 'Pago pendiente',
                        'canceled' => 'Cancelada',
                        default => $state ? ucfirst($state) : 'Sin estado',
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Editar perfil')
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
}
