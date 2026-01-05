<?php

namespace App\Filament\App\Resources;

use App\Filament\App\Resources\TenantProfileResource\Pages;
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
    
    // NAVIGATION SETTINGS: High Priority
    protected static ?string $navigationGroup = 'Configuración';
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

    /**
     * SCOPE PROTECTION LAYER
     * Returns ONLY the currently authenticated tenant.
     * Functions as a redundant firewall even if global scopes fail.
     */
    public static function getEloquentQuery(): Builder
    {
        // 1. Get Base Query
        $query = parent::getEloquentQuery();
        
        // 2. Apply Strict Firewall Rule
        // Only allow access to the tenant ID that matches the current session context
        return $query->where('id', Filament::getTenant()->id);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // ═══════════════════════════════════════════════════════════════
                // ZONE A: VISUAL IDENTITY (Write Enabled)
                // ═══════════════════════════════════════════════════════════════
                Forms\Components\Section::make('Identidad Visual')
                    ->description('Personaliza cómo se ve tu marca ante el mundo.')
                    ->schema([
                        Forms\Components\SpatieMediaLibraryFileUpload::make('logo')
                            ->label('Logo del Negocio')
                            ->collection('logo') // Defined in Model
                            ->avatar()
                            ->image()
                            ->imageEditor()
                            ->helperText('Formato recomendado: PNG o JPG. Se recortará en círculo par el perfil.'),

                        Forms\Components\SpatieMediaLibraryFileUpload::make('cover')
                            ->label('Imagen de Portada')
                            ->collection('cover') // Defined in Model
                            ->image()
                            ->imageEditor()
                            ->columnSpanFull()
                            ->helperText('Aparece en la parte superior de su micrositio. Resolución ideal: 1200x400px.'),

                        Forms\Components\ColorPicker::make('primary_color')
                            ->label('Color de Marca')
                            ->helperText('Define el color principal para botones y enlaces de su sitio.')
                            ->default('#000000'),
                    ])->columns(2),

                // ═══════════════════════════════════════════════════════════════
                // ZONE B: CONTACT INFORMATION (Write Enabled)
                // ═══════════════════════════════════════════════════════════════
                Forms\Components\Section::make('Información Publica')
                    ->description('Datos de contacto visibles para sus clientes.')
                    ->schema([
                        Forms\Components\Textarea::make('description')
                            ->label('Descripción Comercial')
                            ->rows(3)
                            ->columnSpanFull()
                            ->maxLength(500),

                        Forms\Components\TextInput::make('whatsapp_number')
                            ->label('WhatsApp Business')
                            ->tel()
                            ->telRegex('/^[+]*[(]{0,1}[0-9]{1,4}[)]{0,1}[-\s\.\/0-9]*$/')
                            ->helperText('Número para recibir pedidos. Incluya código de área (ej: 504...).'),

                        Forms\Components\TextInput::make('address')
                            ->label('Dirección Física')
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ])->columns(2),

                // ═══════════════════════════════════════════════════════════════
                // ZONE C: SUBSCRIPTION DATA (READ ONLY - HARDENED)
                // ═══════════════════════════════════════════════════════════════
                // CRITICAL SECURITY: Protected against HTML manipulation via dehydrated(false).
                Forms\Components\Section::make('Estado de Suscripción')
                    ->description('Información contractual de su plan. Contacte a soporte para cambios.')
                    ->icon('heroicon-o-shield-check')
                    ->schema([
                        Forms\Components\TextInput::make('id') // UUID/ID
                            ->label('ID de Cuenta')
                            ->disabled()
                            ->dehydrated(false), // Security: Ignored on save

                        Forms\Components\TextInput::make('slug')
                            ->label('URL del Micrositio')
                            ->prefix(env('APP_URL') . '/negocio/')
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
                            ->dehydrated(false) // Absolute Protection
                            ->formatStateUsing(fn (string $state): string => match ($state) {
                                'active' => 'ACTIVO',
                                'trial' => 'PERIODO DE PRUEBA',
                                'past_due' => 'PAGO PENDIENTE',
                                'canceled' => 'CANCELADO',
                                default => strtoupper($state),
                            }),
                    ])->columns(2)
                    ->collapsed(), 
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
                Tables\Actions\EditAction::make()
                    ->label('Editar Perfil')
                    ->modalWidth('4xl'),
            ])
            // INTEGRITY: No creation or deletion allowed from here
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTenants::route('/'),
            'edit' => Pages\EditTenant::route('/{record}/edit'),
        ];
    }
}
