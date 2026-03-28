<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\TenantResource\Pages;
use App\Filament\Resources\TenantResource\RelationManagers\ActivityLogsRelationManager;
use App\Infrastructure\TenantGovernance\SuperAdmin\SuperAdminTenantPlanReader;
// MIGRATION: Updated to Domain namespace (2026-01-05)
use App\Models\Tenant;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class TenantResource extends Resource
{
    protected static bool $isScopedToTenant = false;

    protected static ?string $model = Tenant::class;

    protected static ?string $modelPolicy = \App\Policies\TenantPolicy::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-storefront';

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

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Información principal')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nombre del Negocio')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (string $operation, $state, Set $set) => $operation === 'create' ? $set('slug', Str::slug($state)) : null),

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
                            ->label('Categoría')
                            ->relationship('category', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),

                        Forms\Components\Select::make('business_type')
                            ->label('Modelo de Negocio')
                            ->options(\App\Domain\TenantGovernance\Enums\BusinessType::class)
                            ->required()
                            ->default(\App\Domain\TenantGovernance\Enums\BusinessType::RETAIL)
                            ->native(false),

                        Forms\Components\Select::make('city_id')
                            ->label('Ciudad')
                            ->relationship('city', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),

                        Forms\Components\TextInput::make('address')
                            ->label('Dirección')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('phone')
                            ->label('Teléfono')
                            ->tel(),

                        Forms\Components\TextInput::make('website_url')
                            ->label('Sitio Web')
                            ->url(),
                    ])->columns(2),

                Section::make('Configuración de plan')
                    ->schema([
                        Forms\Components\Select::make('plan_type')
                            ->label('Plan comercial')
                            ->options(static::planTypeOptions())
                            ->default(\App\Domain\TenantGovernance\Enums\TenantPlan::SEMILLA->value)
                            ->required()
                            ->live()
                            ->native(false),

                        Forms\Components\TextInput::make('product_limit')
                            ->label('Límite de productos')
                            ->numeric()
                            ->default(10)
                            ->minValue(0)
                            ->required()
                            ->helperText('Cantidad máxima de productos visibles. Default: 10.'),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Negocio Activo')
                            ->default(true)
                            ->helperText('Si se desactiva, el negocio no podrá operar.'),
                    ])->columns(3),

                Section::make('Personalización de plan pagado')
                    ->description('Personaliza la apariencia del micrositio. Disponible para planes comerciales pagados.')
                    ->schema([
                        Forms\Components\FileUpload::make('logo_path')
                            ->label('Logo')
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml'])
                            ->maxSize(2048)
                            ->image()
                            ->directory('tenants/logos')
                            ->imageEditor(),

                        Forms\Components\FileUpload::make('banner_path')
                            ->label('Banner de Portada')
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->maxSize(2048)
                            ->image()
                            ->directory('tenants/banners')
                            ->disabled(fn (Get $get) => ! static::stateHasPaidPlan($get))
                            ->helperText(fn (Get $get) => ! static::stateHasPaidPlan($get) ? 'Activa un plan pagado para habilitar.' : null),

                        Forms\Components\ColorPicker::make('primary_color')
                            ->label('Color de Marca')
                            ->default('#000000')
                            ->disabled(fn (Get $get) => ! static::stateHasPaidPlan($get))
                            ->helperText(fn (Get $get) => ! static::stateHasPaidPlan($get) ? 'Activa un plan pagado para habilitar.' : null),

                        Forms\Components\TextInput::make('whatsapp_number')
                            ->label('WhatsApp Business')
                            ->tel()
                            ->disabled(fn (Get $get) => ! static::stateHasPaidPlan($get))
                            ->helperText(fn (Get $get) => ! static::stateHasPaidPlan($get) ? 'Activa un plan pagado para habilitar el Botón Mágico.' : null),

                        Forms\Components\KeyValue::make('social_links')
                            ->label('Redes Sociales')
                            ->keyLabel('Plataforma (facebook, instagram, etc)')
                            ->valueLabel('URL Completa')
                            ->disabled(fn (Get $get) => ! static::stateHasPaidPlan($get))
                            ->helperText(fn (Get $get) => ! static::stateHasPaidPlan($get) ? 'Activa un plan pagado para habilitar.' : null),
                    ])->columns(2),

                // BRAND IDENTITY SECTION - White-Label Customization
                // These fields control CSS variable injection on the frontend
                Section::make('Identidad de Marca')
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
                            ->helperText('Máximo 2MB. Formatos: JPG, PNG y WebP. Recomendado: 1920x600px.'),

                        Forms\Components\TextInput::make('slogan')
                            ->label('Eslogan o frase de marca')
                            ->maxLength(100)
                            ->placeholder('Tu frase comercial aquí...')
                            ->helperText('Máximo 100 caracteres. Se muestra debajo del nombre en la portada.'),
                    ])->columns(3),

                // CHECKOUT CONFIGURATION - Operational Settings
                // These settings control payment and delivery options
                Section::make('Configuración de checkout')
                    ->description('Controla las opciones de pago y entrega disponibles para tus clientes.')
                    ->icon('heroicon-o-shopping-cart')
                    ->schema([
                        Forms\Components\CheckboxList::make('payment_methods')
                            ->label('Métodos de pago habilitados')
                            ->options([
                                'cash' => 'Efectivo (contra entrega)',
                                'transfer' => 'Transferencia bancaria',
                                'card' => 'Tarjeta de crédito o débito',
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
                            ->label('Pedido mínimo')
                            ->numeric()
                            ->default(0)
                            ->prefix('L.')
                            ->minValue(0)
                            ->helperText('Valor mínimo para realizar un pedido. 0 = sin mínimo.'),
                    ])->columns(2),

                Section::make('CRM interno')
                    ->description('Memoria comercial persistente para operar activacion, seguimiento, churn y renovacion del negocio.')
                    ->icon('heroicon-o-briefcase')
                    ->schema([
                        Forms\Components\Select::make('crm_owner_id')
                            ->label('Owner interno')
                            ->options(static::internalOwnerOptions())
                            ->searchable()
                            ->preload()
                            ->placeholder('Sin owner interno'),

                        Forms\Components\Select::make('crm_team')
                            ->label('Equipo')
                            ->options(static::crmTeamOptions())
                            ->default(Tenant::CRM_TEAM_ACTIVATION)
                            ->required(),

                        Forms\Components\Select::make('crm_status')
                            ->label('Estado comercial')
                            ->options(static::crmStatusOptions())
                            ->default(Tenant::CRM_STATUS_NEW)
                            ->required(),

                        Forms\Components\Select::make('crm_priority')
                            ->label('Prioridad')
                            ->options(static::crmPriorityOptions())
                            ->default(Tenant::CRM_PRIORITY_NORMAL)
                            ->required(),

                        Forms\Components\TextInput::make('crm_sla_hours')
                            ->label('SLA interno (horas)')
                            ->numeric()
                            ->default(Tenant::DEFAULT_CRM_SLA_HOURS)
                            ->minValue(1)
                            ->maxValue(240)
                            ->required(),

                        Forms\Components\DateTimePicker::make('crm_next_follow_up_at')
                            ->label('Siguiente toque')
                            ->seconds(false),

                        Forms\Components\Textarea::make('crm_notes')
                            ->label('Notas CRM')
                            ->rows(5)
                            ->columnSpanFull()
                            ->placeholder('Resume el contexto comercial, el riesgo o el siguiente paso acordado con el negocio.'),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with([
                'user:id,name,email',
                'crmOwner:id,name,email',
                'city:id,name',
                'category:id,name',
            ])->withCount([
                'products as products_total' => fn (Builder $productQuery): Builder => $productQuery->withoutGlobalScopes(),
            ]))
            ->columns([
                Tables\Columns\ImageColumn::make('logo_path')
                    ->label('')
                    ->circular()
                    ->size(45)
                    ->defaultImageUrl(fn (Tenant $record): string => \App\Support\Ui\AvatarPlaceholder::forName((string) $record->name)),

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
                    ->description(fn (Tenant $record): string => $record->user instanceof User ? $record->user->email : ''),

                Tables\Columns\TextColumn::make('crm_status_label')
                    ->label('Estado comercial')
                    ->badge()
                    ->color(fn (Tenant $record): string => static::crmStatusColor((string) ($record->getAttributes()['crm_status'] ?? Tenant::CRM_STATUS_NEW)))
                    ->description(fn (Tenant $record): string => 'Equipo: '.$record->crm_team_label),

                Tables\Columns\TextColumn::make('crmOwner.name')
                    ->label('Owner interno')
                    ->default('Sin owner interno')
                    ->description(fn (Tenant $record): string => $record->crm_next_follow_up_at instanceof \Illuminate\Support\Carbon
                        ? 'Siguiente toque '.$record->crm_next_follow_up_at->diffForHumans()
                        : 'Sin toque agendado'),

                Tables\Columns\TextColumn::make('city.name')
                    ->label('Ubicación')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Tenant $record): string => $record->category instanceof \App\Models\Category ? $record->category->name : ''),

                Tables\Columns\TextColumn::make('plan_type')
                    ->label('Plan')
                    ->badge()
                    ->color(fn (Tenant $record): string => static::tenantHasPaidPlan($record) ? 'success' : 'gray')
                    ->icon(fn (Tenant $record): string => static::tenantHasPaidPlan($record) ? 'heroicon-o-star' : 'heroicon-o-user')
                    ->formatStateUsing(fn ($state, Tenant $record): string => static::planReader()->planLabel((string) ($state instanceof \BackedEnum ? $state->value : ($state ?: static::planReader()->planKey($record))))),

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
                    ->tooltip(fn (Tenant $record): string => 'Última actividad: '.$record->updated_at->diffForHumans()),

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

                Tables\Columns\TextColumn::make('crm_priority_label')
                    ->label('Prioridad')
                    ->badge()
                    ->color(fn (Tenant $record): string => static::crmPriorityColor((string) ($record->getAttributes()['crm_priority'] ?? Tenant::CRM_PRIORITY_NORMAL)))
                    ->toggleable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Operativo')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-no-symbol')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->tooltip(fn (Tenant $record): string => $record->is_active ? 'Negocio operando normalmente' : 'SUSPENDIDO - Kill Switch activado'),

                Tables\Columns\TextColumn::make('products_total')
                    ->label('Productos')
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
                    ->label('Categoría')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload()
                    ->multiple(),

                Tables\Filters\SelectFilter::make('plan_type')
                    ->label('Plan')
                    ->options(static::planTypeOptions()),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Estado Operativo')
                    ->placeholder('Todos')
                    ->trueLabel('Activos')
                    ->falseLabel('Suspendidos'),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado de aprobación')
                    ->options([
                        'approved' => 'Aprobados',
                        'pending' => 'Pendientes',
                        'rejected' => 'Rechazados',
                    ]),

                Tables\Filters\SelectFilter::make('crm_status')
                    ->label('Estado comercial')
                    ->options(static::crmStatusOptions()),

                Tables\Filters\SelectFilter::make('crm_team')
                    ->label('Equipo CRM')
                    ->options(static::crmTeamOptions()),

                Tables\Filters\SelectFilter::make('crm_owner_id')
                    ->label('Owner interno')
                    ->options(static::internalOwnerOptions())
                    ->searchable(),

                Tables\Filters\Filter::make('en_riesgo')
                    ->label('Negocios en Riesgo')
                    ->query(fn ($query) => $query->where('updated_at', '<', now()->subDays(30)))
                    ->toggle(),
            ])
            ->filtersFormColumns(3)
            ->actions([
                Action::make('approve')
                    ->label('Aprobar')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn (Tenant $record): bool => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->modalHeading('Aprobar Negocio')
                    ->modalDescription('El negocio será visible públicamente y el dueño podrá operar.')
                    ->action(function (Tenant $record): void {
                        $record->approveForOperations();
                        static::logTenantEvent($record, 'Negocio aprobado desde el superpanel', [
                            'status' => 'approved',
                        ]);

                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title('Negocio Aprobado')
                            ->send();
                    }),

                Action::make('reject')
                    ->label('Rechazar')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Tenant $record): bool => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->form([
                        \Filament\Forms\Components\Textarea::make('rejection_reason')
                            ->label('Motivo del rechazo')
                            ->required(),
                    ])
                    ->action(function (Tenant $record, array $data): void {
                        $record->rejectForOperations();
                        static::logTenantEvent($record, 'Negocio rechazado desde el superpanel', [
                            'status' => 'rejected',
                            'reason' => (string) ($data['rejection_reason'] ?? ''),
                        ]);

                        \Filament\Notifications\Notification::make()
                            ->danger()
                            ->title('Negocio Rechazado')
                            ->body((string) ($data['rejection_reason'] ?? ''))
                            ->send();
                    }),

                Action::make('crmFollowUp')
                    ->label('CRM interno')
                    ->icon('heroicon-o-briefcase')
                    ->color('info')
                    ->modalHeading('Gestion CRM interna')
                    ->modalDescription('Asigna owner, equipo, estado y siguiente toque sin salir de la cartera de negocios.')
                    ->fillForm(fn (Tenant $record): array => [
                        'crm_owner_id' => $record->crm_owner_id,
                        'crm_team' => (string) ($record->getAttributes()['crm_team'] ?? Tenant::CRM_TEAM_ACTIVATION),
                        'crm_status' => (string) ($record->getAttributes()['crm_status'] ?? Tenant::CRM_STATUS_NEW),
                        'crm_priority' => (string) ($record->getAttributes()['crm_priority'] ?? Tenant::CRM_PRIORITY_NORMAL),
                        'crm_sla_hours' => (int) ($record->getAttributes()['crm_sla_hours'] ?? Tenant::DEFAULT_CRM_SLA_HOURS),
                        'crm_next_follow_up_at' => optional($record->crm_next_follow_up_at)?->format('Y-m-d\TH:i'),
                        'crm_note' => '',
                    ])
                    ->form([
                        Forms\Components\Select::make('crm_owner_id')
                            ->label('Owner interno')
                            ->options(static::internalOwnerOptions())
                            ->searchable()
                            ->preload()
                            ->placeholder('Sin owner interno'),
                        Forms\Components\Select::make('crm_team')
                            ->label('Equipo')
                            ->options(static::crmTeamOptions())
                            ->required(),
                        Forms\Components\Select::make('crm_status')
                            ->label('Estado comercial')
                            ->options(static::crmStatusOptions())
                            ->required(),
                        Forms\Components\Select::make('crm_priority')
                            ->label('Prioridad')
                            ->options(static::crmPriorityOptions())
                            ->required(),
                        Forms\Components\TextInput::make('crm_sla_hours')
                            ->label('SLA interno (horas)')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(240)
                            ->required(),
                        Forms\Components\DateTimePicker::make('crm_next_follow_up_at')
                            ->label('Siguiente toque')
                            ->seconds(false),
                        Forms\Components\Textarea::make('crm_note')
                            ->label('Nota interna')
                            ->rows(5)
                            ->placeholder('Ej: negocio listo para upsell despues de activar catalogo y WhatsApp.'),
                    ])
                    ->action(function (Tenant $record, array $data): void {
                        $actor = auth()->user();
                        $selectedOwner = filled($data['crm_owner_id'] ?? null)
                            ? User::query()->find((string) $data['crm_owner_id'])
                            : null;

                        if (! $selectedOwner instanceof User && $actor instanceof User && ($data['crm_status'] ?? '') !== Tenant::CRM_STATUS_NEW) {
                            $selectedOwner = $actor;
                        }

                        $record->forceFill([
                            'crm_owner_id' => $selectedOwner instanceof User ? (string) $selectedOwner->getKey() : null,
                            'crm_team' => (string) ($data['crm_team'] ?? Tenant::CRM_TEAM_ACTIVATION),
                            'crm_status' => (string) ($data['crm_status'] ?? Tenant::CRM_STATUS_NEW),
                            'crm_priority' => (string) ($data['crm_priority'] ?? Tenant::CRM_PRIORITY_NORMAL),
                            'crm_sla_hours' => (int) ($data['crm_sla_hours'] ?? Tenant::DEFAULT_CRM_SLA_HOURS),
                            'crm_next_follow_up_at' => $data['crm_next_follow_up_at'] ?? null,
                            'crm_last_contacted_at' => now(),
                            'crm_notes' => static::appendCrmNote(
                                (string) ($record->crm_notes ?? ''),
                                (string) ($data['crm_note'] ?? ''),
                                $actor instanceof User ? $actor : null,
                            ),
                        ])->save();

                        static::logTenantEvent($record, 'Ficha CRM interna actualizada', [
                            'crm_owner_id' => $selectedOwner instanceof User ? (string) $selectedOwner->getKey() : null,
                            'crm_team' => (string) ($record->getAttributes()['crm_team'] ?? Tenant::CRM_TEAM_ACTIVATION),
                            'crm_status' => (string) ($record->getAttributes()['crm_status'] ?? Tenant::CRM_STATUS_NEW),
                            'crm_priority' => (string) ($record->getAttributes()['crm_priority'] ?? Tenant::CRM_PRIORITY_NORMAL),
                            'crm_sla_hours' => (int) ($record->getAttributes()['crm_sla_hours'] ?? Tenant::DEFAULT_CRM_SLA_HOURS),
                            'crm_next_follow_up_at' => optional($record->crm_next_follow_up_at)?->toIso8601String(),
                            'note' => trim((string) ($data['crm_note'] ?? '')),
                        ], $actor instanceof User ? $actor : null);

                        Notification::make()
                            ->success()
                            ->title('CRM actualizado')
                            ->body('El negocio ya tiene owner, estado comercial y siguiente toque persistentes.')
                            ->send();
                    }),

                Action::make('impersonate')
                    ->label('Acceder como Cliente')
                    ->icon('heroicon-o-user-circle')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Shadow Login - Soporte técnico')
                    ->modalDescription(function (Tenant $record): string {
                        $user = $record->user;

                        if (! $user instanceof User) {
                            return 'Este negocio no tiene un propietario asignado para suplantación.';
                        }

                        return "Serás autenticado como '{$user->name}' ({$user->email}). Podrás ver su panel exactamente como él lo ve. Usa el banner superior para regresar.";
                    })
                    ->modalSubmitActionLabel('Iniciar sesión como cliente')
                    ->action(function (Tenant $record) {
                        $user = $record->user;

                        if (! $user instanceof User) {
                            \Filament\Notifications\Notification::make()
                                ->title('Propietario no disponible')
                                ->body('El negocio no tiene un usuario propietario asignado.')
                                ->danger()
                                ->send();

                            return null;
                        }

                        // VC-AUDIT-107: Rate limit impersonation (max 5/hour per admin)
                        $rateLimitKey = 'impersonate:'.auth()->id();
                        if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($rateLimitKey, 5)) {
                            \Filament\Notifications\Notification::make()
                                ->title('Límite de seguridad alcanzado')
                                ->body('Demasiados intentos de suplantación. Espere 1 hora.')
                                ->danger()
                                ->send();

                            return;
                        }
                        \Illuminate\Support\Facades\RateLimiter::hit($rateLimitKey, 3600);

                        // Audit Log: Non-repudiation for administrative actions
                        $admin = auth()->user();
                        activity('security')
                            ->causedBy($admin)
                            ->performedOn($user)
                            ->withProperties(['tenant_id' => $record->id])
                            ->log('admin_impersonation_started');

                        static::logTenantEvent(
                            $record,
                            'Soporte ingresó como propietario del negocio',
                            [
                                'target_user_id' => (string) $user->getKey(),
                                'target_user_email' => (string) $user->email,
                            ],
                            $admin instanceof User ? $admin : null,
                        );

                        $impersonatedUser = User::query()->find($user->getKey());

                        if (! $impersonatedUser instanceof User) {
                            \Filament\Notifications\Notification::make()
                                ->title('Propietario no disponible')
                                ->body('No se pudo cargar la cuenta completa del propietario para soporte.')
                                ->danger()
                                ->send();

                            return null;
                        }

                        session(['impersonator_id' => auth()->id()]);
                        session(['impersonator_name' => auth()->user()->name]);
                        \Illuminate\Support\Facades\Auth::login($impersonatedUser);
                        session()->regenerate(); // Session Fixation Prevention

                        return redirect(route('filament.app.pages.dashboard', ['tenant' => $record->slug], false));
                    }),

                Action::make('killSwitch')
                    ->label(fn (Tenant $record): string => $record->is_active ? 'SUSPENDER' : 'REACTIVAR')
                    ->icon(fn (Tenant $record): string => $record->is_active ? 'heroicon-o-no-symbol' : 'heroicon-o-bolt')
                    ->color(fn (Tenant $record): string => $record->is_active ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->modalHeading(fn (Tenant $record): string => $record->is_active ? 'KILL SWITCH - Suspensión inmediata' : 'Reactivar negocio')
                    ->modalDescription(fn (Tenant $record): string => $record->is_active
                        ? 'ADVERTENCIA: Esta acción congelará TODA la operación del negocio inmediatamente. El micrositio mostrará error 403 y el panel de control será inaccesible.'
                        : 'El negocio será reactivado y podrá funcionar normalmente.')
                    ->modalSubmitActionLabel(fn (Tenant $record): string => $record->is_active ? 'Confirmar suspensión' : 'Confirmar reactivación')
                    ->action(function (Tenant $record): void {
                        $wasActive = $record->is_active;
                        $record->toggleOperationalStatus();
                        $record->refresh();
                        static::logTenantEvent(
                            $record,
                            $wasActive
                                ? 'Negocio suspendido con kill switch'
                                : 'Negocio reactivado desde el superpanel',
                            [
                                'is_active' => (bool) $record->is_active,
                            ],
                        );

                        \Filament\Notifications\Notification::make()
                            ->title($wasActive ? 'Kill Switch Activado' : 'Negocio Reactivado')
                            ->body($wasActive
                                ? "El negocio '{$record->name}' ha sido suspendido."
                                : "El negocio '{$record->name}' ahora puede operar.")
                            ->color($wasActive ? 'danger' : 'success')
                            ->send();
                    }),

                Action::make('forceUpgrade')
                    ->label('Forzar PRO')
                    ->icon('heroicon-o-arrow-up-circle')
                    ->color('info')
                    ->visible(fn (Tenant $record): bool => ! static::tenantHasPaidPlan($record))
                    ->requiresConfirmation()
                    ->modalHeading('Activar Plan PRO Manualmente')
                    ->modalDescription('Esto activará el Plan PRO sin registro de pago. Úsalo solo para regalos o pruebas.')
                    ->form([
                        \Filament\Forms\Components\Select::make('duration')
                            ->label('Duración')
                            ->options([
                                '1' => '1 Mes',
                                '3' => '3 Meses',
                                '6' => '6 Meses',
                                '12' => '1 Año',
                                '999' => 'Permanente',
                            ])
                            ->default('1')
                            ->required(),
                    ])
                    ->action(function (Tenant $record, array $data): void {
                        $months = $data['duration'] == '999' ? 1200 : (int) $data['duration'];

                        $record->activateProManually($months);
                        $record->refresh();
                        static::logTenantEvent($record, 'Plan PRO activado manualmente', [
                            'duration_months' => $months,
                            'duration_label' => $months >= 1200 ? 'Permanente' : "{$months} meses",
                            'plan' => 'pro',
                        ]);

                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title('Plan PRO activado')
                            ->body("Vigencia: {$months} meses")
                            ->send();
                    }),

                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            ActivityLogsRelationManager::class,
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

    /**
     * @return array<string, string>
     */
    private static function internalOwnerOptions(): array
    {
        $superAdmins = collect(config('auth.super_admins', []))
            ->filter(static fn (mixed $email): bool => is_string($email) && $email !== '')
            ->values()
            ->all();

        return User::query()
            ->where(function (Builder $query) use ($superAdmins): void {
                $query->where('role', \App\Domain\Identity\Enums\UserRole::SUPER_ADMIN->value);

                if ($superAdmins !== []) {
                    $query->orWhereIn('email', $superAdmins);
                }
            })
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private static function crmStatusOptions(): array
    {
        return [
            Tenant::CRM_STATUS_NEW => 'Nuevo',
            Tenant::CRM_STATUS_ONBOARDING => 'En onboarding',
            Tenant::CRM_STATUS_ACTIVE => 'Operando',
            Tenant::CRM_STATUS_UPSELL => 'Upsell',
            Tenant::CRM_STATUS_RENEWAL => 'Renovacion',
            Tenant::CRM_STATUS_AT_RISK => 'En riesgo',
            Tenant::CRM_STATUS_PAUSED => 'Pausado',
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function crmTeamOptions(): array
    {
        return [
            Tenant::CRM_TEAM_ACTIVATION => 'Activacion',
            Tenant::CRM_TEAM_GROWTH => 'Growth',
            Tenant::CRM_TEAM_RETENTION => 'Retencion',
            Tenant::CRM_TEAM_BILLING => 'Cobros',
            Tenant::CRM_TEAM_SUPPORT => 'Soporte',
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function crmPriorityOptions(): array
    {
        return [
            Tenant::CRM_PRIORITY_LOW => 'Baja',
            Tenant::CRM_PRIORITY_NORMAL => 'Normal',
            Tenant::CRM_PRIORITY_HIGH => 'Alta',
            Tenant::CRM_PRIORITY_CRITICAL => 'Critica',
        ];
    }

    private static function crmStatusColor(string $status): string
    {
        return match ($status) {
            Tenant::CRM_STATUS_ACTIVE, Tenant::CRM_STATUS_UPSELL => 'success',
            Tenant::CRM_STATUS_ONBOARDING => 'info',
            Tenant::CRM_STATUS_RENEWAL => 'warning',
            Tenant::CRM_STATUS_AT_RISK => 'danger',
            Tenant::CRM_STATUS_PAUSED => 'gray',
            default => 'warning',
        };
    }

    private static function crmPriorityColor(string $priority): string
    {
        return match ($priority) {
            Tenant::CRM_PRIORITY_CRITICAL => 'danger',
            Tenant::CRM_PRIORITY_HIGH => 'warning',
            Tenant::CRM_PRIORITY_LOW => 'gray',
            default => 'info',
        };
    }

    /**
     * @return array<string, string>
     */
    private static function planTypeOptions(): array
    {
        return [
            \App\Domain\TenantGovernance\Enums\TenantPlan::SEMILLA->value => 'Semilla',
            \App\Domain\TenantGovernance\Enums\TenantPlan::PRO->value => 'PRO',
            Tenant::PLAN_CRECIMIENTO => 'Crecimiento',
            Tenant::PLAN_COSECHA => 'Cosecha',
        ];
    }

    private static function stateHasPaidPlan(Get $get): bool
    {
        return in_array((string) ($get('plan_type') ?? ''), ['pro', 'crecimiento', 'cosecha'], true);
    }

    private static function tenantHasPaidPlan(Tenant $tenant): bool
    {
        return static::planReader()->isPaidPlan($tenant);
    }

    private static function planReader(): SuperAdminTenantPlanReader
    {
        return app(SuperAdminTenantPlanReader::class);
    }

    private static function appendCrmNote(string $existing, string $incoming, ?User $actor = null): string
    {
        $incoming = trim($incoming);

        if ($incoming === '') {
            return $existing;
        }

        $prefix = now()->format('d/m/Y H:i').' - '.($actor?->name ?? 'Sistema');
        $entry = $prefix.': '.$incoming;
        $existing = trim($existing);

        return $existing === '' ? $entry : $entry."\n\n".$existing;
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private static function logTenantEvent(Tenant $tenant, string $description, array $properties = [], ?User $actor = null): void
    {
        $resolvedActor = $actor;

        if (! $resolvedActor) {
            $user = auth()->user();
            $resolvedActor = $user instanceof User ? $user : null;
        }

        $tenant->logOperationalEvent($description, $resolvedActor, $properties);
    }
}



