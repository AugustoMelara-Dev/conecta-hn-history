<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\TenantResource\Pages;
// MIGRATION: Updated to Domain namespace (2026-01-05)
use App\Domain\TenantGovernance\Models\Tenant;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class TenantResource extends Resource
{
    protected static ?string $model = Tenant::class;

    protected static ?string $modelPolicy = \App\Policies\TenantPolicy::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    public static function getNavigationLabel(): string
    {
        return 'Negocios';
    }

    public static function getModelLabel(): string
    {
        return 'Negocio';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Negocios';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informacion Principal')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nombre del Negocio')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (string $operation, $state, Forms\Set $set) => $operation === 'create' ? $set('slug', Str::slug($state)) : null),

                        Forms\Components\TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(Tenant::class, 'slug', ignoreRecord: true),

                        Forms\Components\Select::make('user_id')
                            ->label('Propietario')
                            ->relationship('user', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),

                        Forms\Components\Select::make('category_id')
                            ->label('Categoria')
                            ->relationship('category', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),

                        Forms\Components\Select::make('city_id')
                            ->label('Ciudad')
                            ->relationship('city', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),

                        Forms\Components\TextInput::make('address')
                            ->label('Direccion')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('phone')
                            ->label('Telefono')
                            ->tel(),

                        Forms\Components\TextInput::make('website')
                            ->label('Sitio Web')
                            ->url(),
                    ])->columns(2),

                Forms\Components\Section::make('Configuracion de Plan')
                    ->schema([
                        Forms\Components\Toggle::make('is_pro')
                            ->label('Plan PRO Activo')
                            ->default(false)
                            ->live(),

                        Forms\Components\TextInput::make('product_limit')
                            ->label('Limite de Productos')
                            ->numeric()
                            ->default(10)
                            ->minValue(0)
                            ->required()
                            ->helperText('Cantidad maxima de productos visibles. Default: 10.'),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Negocio Activo')
                            ->default(true)
                            ->helperText('Si se desactiva, el negocio no podra operar.'),
                    ])->columns(3),

                Forms\Components\Section::make('Personalizacion (PRO)')
                    ->description('Personaliza la apariencia del micrositio. Exclusivo para planes PRO.')
                    ->schema([
                        Forms\Components\FileUpload::make('logo_path')
                            ->label('Logo')
                            ->image()
                            ->directory('tenants/logos')
                            ->imageEditor(),

                        Forms\Components\FileUpload::make('banner_path')
                            ->label('Banner de Portada')
                            ->image()
                            ->directory('tenants/banners')
                            ->disabled(fn (Forms\Get $get) => ! $get('is_pro'))
                            ->helperText(fn (Forms\Get $get) => ! $get('is_pro') ? 'Activa el Plan PRO para habilitar.' : null),

                        Forms\Components\ColorPicker::make('primary_color')
                            ->label('Color de Marca')
                            ->default('#000000')
                            ->disabled(fn (Forms\Get $get) => ! $get('is_pro'))
                            ->helperText(fn (Forms\Get $get) => ! $get('is_pro') ? 'Activa el Plan PRO para habilitar.' : null),

                        Forms\Components\TextInput::make('whatsapp_number')
                            ->label('WhatsApp Business')
                            ->tel()
                            ->disabled(fn (Forms\Get $get) => ! $get('is_pro'))
                            ->helperText(fn (Forms\Get $get) => ! $get('is_pro') ? 'Activa el Plan PRO para habilitar el Boton Magico.' : null),

                        Forms\Components\KeyValue::make('social_links')
                            ->label('Redes Sociales')
                            ->keyLabel('Plataforma (facebook, instagram, etc)')
                            ->valueLabel('URL Completa')
                            ->disabled(fn (Forms\Get $get) => ! $get('is_pro'))
                            ->helperText(fn (Forms\Get $get) => ! $get('is_pro') ? 'Activa el Plan PRO para habilitar.' : null),
                    ])->columns(2),

                // ═══════════════════════════════════════════════════════════════
                // BRAND IDENTITY SECTION - White-Label Customization
                // These fields control CSS variable injection on the frontend
                // ═══════════════════════════════════════════════════════════════
                Forms\Components\Section::make('Identidad de Marca')
                    ->description('Personaliza colores y portada del micrositio. Se aplica automáticamente a botones y elementos interactivos.')
                    ->icon('heroicon-o-paint-brush')
                    ->schema([
                        Forms\Components\ColorPicker::make('brand_color')
                            ->label('Color Principal de Marca')
                            ->default('#2563EB')
                            ->required()
                            ->regex('/^#[0-9A-Fa-f]{6}$/')
                            ->helperText('Formato hexadecimal (#RRGGBB). Se aplica a botones y elementos destacados.'),

                        Forms\Components\FileUpload::make('hero_image')
                            ->label('Imagen de Portada (Hero)')
                            ->image()
                            ->directory('tenant-heroes')
                            ->maxSize(2048) // 2MB max for LCP performance
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->imagePreviewHeight('200')
                            ->helperText('Máximo 2MB. Formatos: JPG, PNG, WebP. Recomendado: 1920x600px.'),

                        Forms\Components\TextInput::make('slogan')
                            ->label('Eslogan / Frase de Marca')
                            ->maxLength(100)
                            ->placeholder('Tu frase comercial aquí...')
                            ->helperText('Máximo 100 caracteres. Se muestra debajo del nombre en la portada.'),
                    ])->columns(3),

                // ═══════════════════════════════════════════════════════════════
                // CHECKOUT CONFIGURATION - Operational Settings
                // These settings control payment and delivery options
                // ═══════════════════════════════════════════════════════════════
                Forms\Components\Section::make('Configuración de Checkout')
                    ->description('Controla las opciones de pago y entrega disponibles para tus clientes.')
                    ->icon('heroicon-o-shopping-cart')
                    ->schema([
                        Forms\Components\CheckboxList::make('payment_methods')
                            ->label('Métodos de Pago Habilitados')
                            ->options([
                                'cash' => '💵 Efectivo (Contra entrega)',
                                'transfer' => '🏦 Transferencia Bancaria',
                                'card' => '💳 Tarjeta de Crédito/Débito',
                            ])
                            ->columns(3)
                            ->helperText('Selecciona los métodos que aceptas. Si no seleccionas ninguno, solo se mostrará efectivo.'),

                        Forms\Components\Toggle::make('delivery_enabled')
                            ->label('Habilitar Entrega a Domicilio')
                            ->default(true)
                            ->helperText('Permite a los clientes solicitar delivery.'),

                        Forms\Components\Toggle::make('pickup_enabled')
                            ->label('Habilitar Recoger en Tienda')
                            ->default(true)
                            ->helperText('Permite a los clientes recoger su pedido.'),

                        Forms\Components\TextInput::make('minimum_order')
                            ->label('Pedido Mínimo')
                            ->numeric()
                            ->default(0)
                            ->prefix('L.')
                            ->minValue(0)
                            ->helperText('Valor mínimo para realizar un pedido. 0 = sin mínimo.'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('logo_path')
                    ->label('')
                    ->circular()
                    ->size(45)
                    ->defaultImageUrl(fn ($record) => 'https://ui-avatars.com/api/?name='.urlencode($record->name).'&background=random'),

                Tables\Columns\TextColumn::make('name')
                    ->label('Negocio')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (Tenant $record): string => $record->slug),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Propietario')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Tenant $record): string => $record->user->email ?? ''),

                Tables\Columns\TextColumn::make('city.name')
                    ->label('Ubicacion')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Tenant $record): string => $record->category->name ?? ''),

                Tables\Columns\TextColumn::make('is_pro')
                    ->label('Plan')
                    ->badge()
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray')
                    ->icon(fn (bool $state): string => $state ? 'heroicon-o-star' : 'heroicon-o-user')
                    ->formatStateUsing(fn (bool $state): string => $state ? 'PRO' : 'FREE'),

                Tables\Columns\ColorColumn::make('primary_color')
                    ->label('Marca')
                    ->copyable()
                    ->copyMessage('Color copiado')
                    ->tooltip(fn ($state) => $state ?? 'Sin color definido'),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Salud')
                    ->badge()
                    ->state(function (Tenant $record): string {
                        $lastActivity = $record->updated_at;
                        $hoursSince = $lastActivity->diffInHours(now());

                        if ($hoursSince <= 48) {
                            return 'Activo';
                        } elseif ($hoursSince <= 168) { // 7 dias
                            return 'Normal';
                        } elseif ($hoursSince <= 720) { // 30 dias
                            return 'Inactivo';
                        } else {
                            return 'En Riesgo';
                        }
                    })
                    ->color(function (Tenant $record): string {
                        $hoursSince = $record->updated_at->diffInHours(now());

                        if ($hoursSince <= 48) {
                            return 'success';
                        } elseif ($hoursSince <= 168) {
                            return 'info';
                        } elseif ($hoursSince <= 720) {
                            return 'warning';
                        } else {
                            return 'danger';
                        }
                    })
                    ->icon(function (Tenant $record): string {
                        $hoursSince = $record->updated_at->diffInHours(now());

                        if ($hoursSince <= 48) {
                            return 'heroicon-o-heart';
                        } elseif ($hoursSince <= 168) {
                            return 'heroicon-o-check-circle';
                        } elseif ($hoursSince <= 720) {
                            return 'heroicon-o-clock';
                        } else {
                            return 'heroicon-o-exclamation-triangle';
                        }
                    })
                    ->tooltip(fn (Tenant $record): string => 'Ultima actividad: '.$record->updated_at->diffForHumans()),

                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'approved' => 'success',
                        'pending' => 'warning',
                        'rejected' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'approved' => 'Aprobado',
                        'pending' => 'Pendiente',
                        'rejected' => 'Rechazado',
                        default => ucfirst($state),
                    }),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Operativo')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-no-symbol')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->tooltip(fn ($record): string => $record->is_active ? 'Negocio operando normalmente' : 'SUSPENDIDO - Kill Switch activado'),

                Tables\Columns\TextColumn::make('products_count')
                    ->label('Productos')
                    ->counts('products')
                    ->sortable()
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Registro')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('city_id')
                    ->label('Ciudad')
                    ->relationship('city', 'name')
                    ->searchable()
                    ->preload()
                    ->multiple(),

                Tables\Filters\SelectFilter::make('category_id')
                    ->label('Categoria')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload()
                    ->multiple(),

                Tables\Filters\TernaryFilter::make('is_pro')
                    ->label('Plan')
                    ->placeholder('Todos')
                    ->trueLabel('Solo PRO')
                    ->falseLabel('Solo FREE'),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Estado Operativo')
                    ->placeholder('Todos')
                    ->trueLabel('Activos')
                    ->falseLabel('Suspendidos'),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado de Aprobacion')
                    ->options([
                        'approved' => 'Aprobados',
                        'pending' => 'Pendientes',
                        'rejected' => 'Rechazados',
                    ]),

                Tables\Filters\Filter::make('en_riesgo')
                    ->label('Negocios en Riesgo')
                    ->query(fn ($query) => $query->where('updated_at', '<', now()->subDays(30)))
                    ->toggle(),
            ])
            ->filtersFormColumns(3)
            ->actions([
                Tables\Actions\Action::make('approve')
                    ->label('Aprobar')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn ($record) => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->modalHeading('Aprobar Negocio')
                    ->modalDescription('El negocio sera visible publicamente y el dueno podra operar.')
                    ->action(function ($record) {
                        $record->update([
                            'status' => 'approved',
                            'is_active' => true,
                        ]);

                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title('Negocio Aprobado')
                            ->send();
                    }),

                Tables\Actions\Action::make('reject')
                    ->label('Rechazar')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn ($record) => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->form([
                        \Filament\Forms\Components\Textarea::make('rejection_reason')
                            ->label('Motivo del rechazo')
                            ->required(),
                    ])
                    ->action(function ($record, array $data) {
                        $record->update([
                            'status' => 'rejected',
                            'is_active' => false,
                        ]);

                        \Filament\Notifications\Notification::make()
                            ->danger()
                            ->title('Negocio Rechazado')
                            ->send();
                    }),

                Tables\Actions\Action::make('impersonate')
                    ->label('Acceder como Cliente')
                    ->icon('heroicon-o-user-circle')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Shadow Login - Soporte Tecnico')
                    ->modalDescription(fn ($record) => "Seras autenticado como '{$record->user->name}' ({$record->user->email}). Podras ver su panel exactamente como el lo ve. Usa el banner superior para regresar.")
                    ->modalSubmitActionLabel('Iniciar Sesion como Cliente')
                    ->action(function ($record) {
                        session(['impersonator_id' => auth()->id()]);
                        session(['impersonator_name' => auth()->user()->name]);
                        \Illuminate\Support\Facades\Auth::login($record->user);
                        return redirect('/app');
                    }),

                Tables\Actions\Action::make('killSwitch')
                    ->label(fn ($record) => $record->is_active ? 'SUSPENDER' : 'REACTIVAR')
                    ->icon(fn ($record) => $record->is_active ? 'heroicon-o-no-symbol' : 'heroicon-o-bolt')
                    ->color(fn ($record) => $record->is_active ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->modalHeading(fn ($record) => $record->is_active ? 'KILL SWITCH - Suspension Inmediata' : 'Reactivar Negocio')
                    ->modalDescription(fn ($record) => $record->is_active
                        ? 'ADVERTENCIA: Esta accion congelara TODA la operacion del negocio inmediatamente. El micrositio mostrara error 403 y el panel de control sera inaccesible.'
                        : 'El negocio sera reactivado y podra funcionar normalmente.')
                    ->modalSubmitActionLabel(fn ($record) => $record->is_active ? 'Confirmar Suspension' : 'Confirmar Reactivacion')
                    ->action(function ($record) {
                        $wasActive = $record->is_active;
                        $record->update(['is_active' => ! $record->is_active]);

                        \Filament\Notifications\Notification::make()
                            ->title($wasActive ? 'Kill Switch Activado' : 'Negocio Reactivado')
                            ->body($wasActive
                                ? "El negocio '{$record->name}' ha sido suspendido."
                                : "El negocio '{$record->name}' ahora puede operar.")
                            ->color($wasActive ? 'danger' : 'success')
                            ->send();
                    }),

                Tables\Actions\Action::make('forceUpgrade')
                    ->label('Forzar PRO')
                    ->icon('heroicon-o-arrow-up-circle')
                    ->color('info')
                    ->visible(fn ($record) => ! $record->is_pro)
                    ->requiresConfirmation()
                    ->modalHeading('Activar Plan PRO Manualmente')
                    ->modalDescription('Esto activara el Plan PRO sin registro de pago. Usar solo para regalos o pruebas.')
                    ->form([
                        \Filament\Forms\Components\Select::make('duration')
                            ->label('Duracion')
                            ->options([
                                '1' => '1 Mes',
                                '3' => '3 Meses',
                                '6' => '6 Meses',
                                '12' => '1 Ano',
                                '999' => 'Permanente',
                            ])
                            ->default('1')
                            ->required(),
                    ])
                    ->action(function ($record, array $data) {
                        $months = $data['duration'] == '999' ? 1200 : (int) $data['duration'];

                        $record->update([
                            'is_pro' => true,
                            'plan_expires_at' => now()->addMonths($months),
                        ]);

                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title('Plan PRO Activado')
                            ->body("Vigencia: {$months} meses")
                            ->send();
                    }),

                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTenants::route('/'),
            'create' => Pages\CreateTenant::route('/create'),
            'edit' => Pages\EditTenant::route('/{record}/edit'),
        ];
    }
}
