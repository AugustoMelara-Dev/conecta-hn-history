<?php

declare(strict_types=1);

namespace App\Filament\App\Resources;

use App\Domain\TenantGovernance\Enums\Capability;
use App\Filament\App\Resources\ProductResource\Pages;
use App\Filament\App\Resources\ProductResource\RelationManagers\OptionGroupsRelationManager;
use App\Models\Product;
use App\Models\Tenant;
use App\Settings\GeneralSettings;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ReplicateAction;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Actions as SchemaActions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * ProductResource - Herramienta de Merchandising Visual
 *
 * Optimizado para uso móvil con una sola mano.
 * Implementa el "Muro de Contención" para límites de suscripción.
 *
 * Características:
 * - Instagram-style image upload con editor 1:1
 * - Toggle columns para gestión rápida
 * - Límite de productos según plan (canCreate)
 */
class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?int $navigationSort = 1;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shopping-bag';

    public static function getNavigationLabel(): string
    {
        return 'Mis Productos';
    }

    public static function getModelLabel(): string
    {
        return 'Producto';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Productos';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Catálogo';
    }

    // ═══════════════════════════════════════════════════════════════════════
    // MURO DE CONTENCIÓN: Límite de productos según plan
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Verifica si el usuario puede crear más productos
     */
    public static function canCreate(): bool
    {
        if (! Gate::allows('create', Product::class)) {
            return false;
        }

        $tenant = static::getCurrentTenant();

        if (! $tenant) {
            return false;
        }

        // PRO tiene productos ilimitados
        if ($tenant->is_pro) {
            return true;
        }

        // Obtener límite desde configuración
        $settings = app(GeneralSettings::class);
        $limit = $settings->free_plan_product_limit ?? 10;

        // Contar productos existentes (usando DB Facade para evitar violación de arquitectura)
        $currentCount = DB::table('products')
            ->where('tenant_id', $tenant->id)
            ->whereNull('deleted_at')
            ->count();

        return $currentCount < $limit;
    }

    /**
     * Obtiene información del límite para mostrar en UI
     */
    public static function getProductLimitInfo(): array
    {
        $tenant = static::getCurrentTenant();

        if (! $tenant) {
            return ['count' => 0, 'limit' => 0, 'percentage' => 0, 'canCreate' => false, 'isPro' => false];
        }

        $currentCount = DB::table('products')
            ->where('tenant_id', $tenant->id)
            ->whereNull('deleted_at')
            ->count();

        if ($tenant->is_pro) {
            return ['count' => $currentCount, 'limit' => null, 'percentage' => 0, 'canCreate' => true, 'isPro' => true];
        }

        $settings = app(GeneralSettings::class);
        $limit = (int) ($settings->free_plan_product_limit ?? 10);
        $percentage = $limit > 0 ? min(100, (int) round(($currentCount / $limit) * 100)) : 100;

        return [
            'count' => $currentCount,
            'limit' => $limit,
            'percentage' => $percentage,
            'canCreate' => static::canCreate(),
            'isPro' => false,
        ];
    }

    // ═══════════════════════════════════════════════════════════════════════
    // FORMULARIO VISUAL OPTIMIZADO PARA MÓVIL
    // ═══════════════════════════════════════════════════════════════════════

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                // ═══════════════════════════════════════════════════════════
                // SECCIÓN 1: IMAGEN INSTAGRAM-STYLE
                // ═══════════════════════════════════════════════════════════
                Section::make('📷 Imagen del Producto')
                    ->description(fn (): string => static::currentTenantIsPro()
                        ? 'Sube hasta 5 imágenes. Arrastra para reordenar.'
                        : '⚠️ Plan Gratuito: Solo 1 imagen. Actualiza a PRO para más.')
                    ->schema([
                        Forms\Components\FileUpload::make('image_path')
                            ->label('')
                            ->image()
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp']) // Security Hardening
                            ->maxSize(2048) // 2MB Limit
                            ->directory('products')
                            ->visibility('public')
                            // Instagram-style: Editor con aspect ratio 1:1
                            ->imageEditor()
                            ->imageEditorMode(2)
                            ->imageEditorAspectRatios(['1:1'])
                            ->imageCropAspectRatio('1:1')
                            ->imageResizeTargetWidth('800')
                            ->imageResizeTargetHeight('800')
                            // PRO: múltiples imágenes
                            ->maxFiles(fn (): int => static::currentTenantIsPro() ? 5 : 1)
                            ->multiple(fn (): bool => static::currentTenantIsPro())
                            ->reorderable(fn (): bool => static::currentTenantIsPro())
                            ->panelLayout('grid')
                            ->columnSpanFull(), /*
                            ->hintAction(
                                Action::make('generate_ai_profile')
                                    ->label('Generar con IA (PRO)')
                                    ->icon('heroicon-m-sparkles')
                                    ->color('gray')
                                    ->visible(fn (): bool => static::currentTenantIsPro())
                                    ->requiresConfirmation()
                                    ->modalHeading('GeneraciÃ³n Inteligente')
                                    ->modalDescription('Usaremos tu descripciÃ³n actual para generar un texto persuasivo y optimizado. Esto consumirÃ¡ crÃ©ditos de tu presupuesto de IA.')
                                    ->modalSubmitActionLabel('Generar Perfil')
                                    ->action(function (Set $set, Get $get): void {
                                        $tenant = static::getCurrentTenant();
                                        $user = auth()->user();
                                        $productName = trim((string) ($get('name') ?? ''));
                                        $currentDescription = trim((string) ($get('description') ?? ''));

                                        if (! $tenant || ! $user) {
                                            Notification::make()
                                                ->title('SesiÃ³n no disponible')
                                                ->body('No pudimos identificar tu sesiÃ³n o negocio actual.')
                                                ->danger()
                                                ->send();

                                            return;
                                        }

                                        if ($productName === '' || $currentDescription === '') {
                                            Notification::make()
                                                ->title('Faltan datos')
                                                ->body('Ingresa un nombre y una descripciÃ³n bÃ¡sica primero.')
                                                ->warning()
                                                ->send();

                                            return;
                                        }

                                        $userId = (string) $user->getAuthIdentifier();

                                        try {
                                            $aiService = app(\App\Services\AI\OpenAIService::class);

                                            $result = $aiService->generateStructuredProfile(
                                                businessName: $productName,
                                                description: $currentDescription,
                                                userId: $userId,
                                                tenantId: (string) $tenant->id
                                            );

                                            $set('description', strip_tags((string) $result['description']));

                                            Notification::make()
                                                ->title('Magia aplicada')
                                                ->body('La descripciÃ³n de tu producto ha sido optimizada.')
                                                ->success()
                                                ->send();
                                        } catch (\App\Exceptions\InsufficientAiCreditsException $e) {
                                            Notification::make()
                                                ->title('Presupuesto agotado')
                                                ->body('Has alcanzado tu lÃ­mite de IA este mes.')
                                                ->danger()
                                                ->send();
                                        } catch (\Throwable $e) {
                                            \Illuminate\Support\Facades\Log::error('[ProductResource] AI generation failed', [
                                                'tenant_id' => $tenant->id,
                                                'user_id' => $userId,
                                                'error' => $e->getMessage(),
                                            ]);

                                            Notification::make()
                                                ->title('Error del motor')
                                                ->body('La IA estÃ¡ descansando. Intenta de nuevo.')
                                                ->danger()
                                                ->send();
                                        }
                                    })
                            ),
                            */
                    ])
                    ->collapsible(),

                // ═══════════════════════════════════════════════════════════
                // SECCIÓN 2: DATOS ESENCIALES
                // ═══════════════════════════════════════════════════════════
                Section::make('🏷️ Información Básica')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nombre del Producto')
                            ->required()
                            ->maxLength(100)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn ($state, $set) => $set('slug', Str::slug($state)))
                            ->placeholder('Ej: Baleada Sencilla'),

                        Forms\Components\Hidden::make('slug'),

                        Forms\Components\TextInput::make('price')
                            ->label('Precio')
                            ->numeric()
                            ->prefix('L.')
                            ->required()
                            ->minValue(0)
                            ->step(0.01)
                            ->placeholder('0.00'),

                        Forms\Components\Textarea::make('description')
                            ->label('Descripción')
                            ->rows(3)
                            ->maxLength(500)
                            ->placeholder('Describe tu producto brevemente...')
                            ->columnSpanFull()
                            ->hintAction(
                                Action::make('generate_ai_profile')
                                    ->label('Generar con IA (PRO)')
                                    ->icon('heroicon-m-sparkles')
                                    ->color('gray')
                                    ->visible(fn (): bool => static::currentTenantIsPro())
                                    ->requiresConfirmation()
                                    ->modalHeading('Generacion inteligente')
                                    ->modalDescription('Usaremos tu descripcion actual para generar un texto persuasivo y optimizado. Esto consumira creditos de tu presupuesto de IA.')
                                    ->modalSubmitActionLabel('Generar perfil')
                                    ->action(function (Set $set, Get $get): void {
                                        $tenant = static::getCurrentTenant();
                                        $user = auth()->user();
                                        $productName = trim((string) ($get('name') ?? ''));
                                        $currentDescription = trim((string) ($get('description') ?? ''));

                                        if (! $tenant || ! $user) {
                                            Notification::make()
                                                ->title('Sesion no disponible')
                                                ->body('No pudimos identificar tu sesion o negocio actual.')
                                                ->danger()
                                                ->send();

                                            return;
                                        }

                                        if ($productName === '' || $currentDescription === '') {
                                            Notification::make()
                                                ->title('Faltan datos')
                                                ->body('Ingresa un nombre y una descripcion basica primero.')
                                                ->warning()
                                                ->send();

                                            return;
                                        }

                                        $userId = (string) $user->getAuthIdentifier();

                                        try {
                                            $aiService = app(\App\Services\AI\OpenAIService::class);

                                            $result = $aiService->generateStructuredProfile(
                                                businessName: $productName,
                                                description: $currentDescription,
                                                userId: $userId,
                                                tenantId: (string) $tenant->id
                                            );

                                            $set('description', strip_tags((string) $result['description']));

                                            Notification::make()
                                                ->title('Magia aplicada')
                                                ->body('La descripcion de tu producto ha sido optimizada.')
                                                ->success()
                                                ->send();
                                        } catch (\App\Exceptions\InsufficientAiCreditsException $e) {
                                            Notification::make()
                                                ->title('Presupuesto agotado')
                                                ->body('Has alcanzado tu limite de IA este mes.')
                                                ->danger()
                                                ->send();
                                        } catch (\Throwable $e) {
                                            \Illuminate\Support\Facades\Log::error('[ProductResource] AI generation failed', [
                                                'tenant_id' => $tenant->id,
                                                'user_id' => $userId,
                                                'error' => $e->getMessage(),
                                            ]);

                                            Notification::make()
                                                ->title('Error del motor')
                                                ->body('La IA esta descansando. Intenta de nuevo.')
                                                ->danger()
                                                ->send();
                                        }
                                    })
                            ),
                    ])
                    ->columns(2),

                // ═══════════════════════════════════════════════════════════
                // SECCIÓN 3: ORGANIZACIÓN
                // ═══════════════════════════════════════════════════════════
                Section::make('📂 Organización')
                    ->schema([
                        Forms\Components\Select::make('category_id')
                            ->relationship('category', 'name')
                            ->label('Categoría')
                            ->searchable()
                            ->preload()
                            ->required(),

                        // ═══════════════════════════════════════════════════
                        // VELVET ROPE: Selector de Sección (PRO-ONLY)
                        // ═══════════════════════════════════════════════════
                        // VERSIÓN A: Campo funcional para usuarios PRO
                        // - Relación con tenant scoping
                        // - Creación en línea con modal
                        // ═══════════════════════════════════════════════════
                        Forms\Components\Select::make('menu_section_id')
                            // Solo visible para tenants con organizacion de menu
                            ->visible(fn (): bool => static::currentTenantHasCapability(Capability::MENU_MODIFIERS))

                            // SEGURIDAD: Filtro estricto por tenant_id
                            ->relationship(
                                'menuSection',
                                'name',
                                fn ($query) => $query
                                    ->where('tenant_id', static::currentTenantId())
                                    ->where('is_active', true)
                                    ->orderBy('sort_order')
                            )
                            ->label('Sección del Menú')
                            ->placeholder('Selecciona o crea una sección...')
                            ->searchable()
                            ->preload()

                            // CREACIÓN EN LÍNEA: Modal de alta velocidad
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')
                                    ->label('Nombre de la Sección')
                                    ->placeholder('Ej: Vinos Importados, Postres Especiales')
                                    ->required()
                                    ->maxLength(100)
                                    ->autofocus(),

                                // Inyección automática del tenant_id (seguridad)
                                Forms\Components\Hidden::make('tenant_id')
                                    ->default(fn (): int|string|null => static::currentTenantId()),

                                // Sección activa por defecto
                                Forms\Components\Hidden::make('is_active')
                                    ->default(true),

                                // Sort order auto-calculado al final
                                Forms\Components\Hidden::make('sort_order')
                                    ->default(fn (): int => ((int) \App\Models\MenuSection::where('tenant_id', static::currentTenantId())->max('sort_order')) + 1),
                            ])
                            ->createOptionModalHeading('Crear Nueva Sección')
                            ->helperText('✨ Organiza tu catálogo en secciones arrastrables'),

                        // ═══════════════════════════════════════════════════
                        // VELVET ROPE: Campo Señuelo (FREE - UPSELL)
                        // ═══════════════════════════════════════════════════
                        // VERSIÓN B: Campo bloqueado que genera FOMO
                        // - Visible pero disabled para usuarios FREE
                        // - Link persuasivo hacia upgrade
                        // ═══════════════════════════════════════════════════
                        Forms\Components\Select::make('menu_section_id_locked')
                            // Mostrar version bloqueada cuando el tenant no tenga esta capacidad
                            ->visible(fn (): bool => ! static::currentTenantHasCapability(Capability::MENU_MODIFIERS))

                            // Estado bloqueado
                            ->disabled()
                            ->dehydrated(false) // No se envía al backend

                            ->label('🔒 Sección del Menú')
                            ->placeholder('Disponible solo en Plan PRO')
                            ->options([])

                            // Mensaje persuasivo con link de upsell
                            ->helperText(new \Illuminate\Support\HtmlString(
                                '<span class="text-gray-600">Organiza tus productos en secciones personalizadas.</span> '.
                                '<a href="'.route('filament.app.pages.manage-billing', ['tenant' => static::currentTenantSlug()]).'" '.
                                'class="text-primary-600 font-bold hover:underline">→ Desbloquear con PRO</a>'
                            )),
                    ])
                    ->columns(2)
                    ->collapsible(),

                // ═══════════════════════════════════════════════════════════
                // SECCIÓN 4: CONTROL DE ESTADO
                // ═══════════════════════════════════════════════════════════
                Section::make('⚙️ Control')
                    ->schema([
                        Forms\Components\Toggle::make('is_visible')
                            ->label('Visible en Catálogo')
                            ->default(true)
                            ->helperText('Desactiva para ocultar temporalmente'),

                        Forms\Components\Toggle::make('is_featured')
                            ->label('Producto Destacado')
                            ->default(false)
                            ->visible(fn (): bool => static::currentTenantIsPro())
                            ->helperText('Aparecerá primero en tu catálogo'),

                        // ═══════════════════════════════════════════════════
                        // CROSS-SELLING: Producto de Impulso (PRO Feature)
                        // ═══════════════════════════════════════════════════
                        Forms\Components\Toggle::make('is_impulse_buy')
                            ->label('Producto de Impulso (Cross-Selling)')
                            ->default(false)
                            ->onColor('success')
                            ->visible(fn (): bool => static::currentTenantIsPro())
                            ->helperText('Activar esta opción sugerirá este producto en el checkout para aumentar el ticket promedio.'),
                    ])
                    ->columns(2)
                    ->collapsed(),

                // ═══════════════════════════════════════════════════════════
                // SECCIÓN: UPSELL MARKETING (Solo para Free)
                // ═══════════════════════════════════════════════════════════
                Section::make('✨ Desbloquea Funciones PRO')
                    ->description('Mejora tu presencia y convierte más clientes.')
                    ->visible(fn (): bool => ! static::currentTenantIsPro())
                    ->schema([
                        Forms\Components\Placeholder::make('upsell_features')
                            ->label('')
                            ->content(new \Illuminate\Support\HtmlString('
                                <div class="space-y-3">
                                    <div class="flex items-center gap-3 p-3 bg-gradient-to-r from-amber-50 to-amber-100 dark:from-amber-900/20 dark:to-amber-800/20 rounded-lg border border-amber-200 dark:border-amber-700">
                                        <span class="text-2xl">📸</span>
                                        <div>
                                            <p class="font-semibold text-amber-800 dark:text-amber-200">Galería de Fotos</p>
                                            <p class="text-sm text-amber-600 dark:text-amber-400">Sube hasta 5 imágenes por producto</p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-3 p-3 bg-gradient-to-r from-blue-50 to-blue-100 dark:from-blue-900/20 dark:to-blue-800/20 rounded-lg border border-blue-200 dark:border-blue-700">
                                        <span class="text-2xl">📂</span>
                                        <div>
                                            <p class="font-semibold text-blue-800 dark:text-blue-200">Secciones de Menú</p>
                                            <p class="text-sm text-blue-600 dark:text-blue-400">Organiza tus productos por categorías</p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-3 p-3 bg-gradient-to-r from-purple-50 to-purple-100 dark:from-purple-900/20 dark:to-purple-800/20 rounded-lg border border-purple-200 dark:border-purple-700">
                                        <span class="text-2xl">⭐</span>
                                        <div>
                                            <p class="font-semibold text-purple-800 dark:text-purple-200">Productos Destacados</p>
                                            <p class="text-sm text-purple-600 dark:text-purple-400">Muestra tus mejores productos primero</p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-3 p-3 bg-gradient-to-r from-green-50 to-green-100 dark:from-green-900/20 dark:to-green-800/20 rounded-lg border border-green-200 dark:border-green-700">
                                        <span class="text-2xl">🎨</span>
                                        <div>
                                            <p class="font-semibold text-green-800 dark:text-green-200">Variantes de Producto</p>
                                            <p class="text-sm text-green-600 dark:text-green-400">Tallas, colores, sabores con precios extras</p>
                                        </div>
                                    </div>
                                </div>
                            ')),

                        SchemaActions::make([
                            Action::make('upgrade_to_pro')
                                ->label('🚀 Mejorar a Plan PRO')
                                ->color('warning')
                                ->size('lg')
                                ->extraAttributes(['class' => 'w-full justify-center'])
                                ->url(fn (): string => route('filament.app.pages.manage-billing', ['tenant' => static::currentTenantSlug()])),
                        ])->fullWidth(),
                    ])
                    ->collapsible(),

                // ═══════════════════════════════════════════════════════════
                // SECCIÓN 5: VARIANTES (Solo PRO)
                // ═══════════════════════════════════════════════════════════
                Section::make('🎨 Variantes del Producto')
                    ->description('Define opciones como talla, color, tamaño.')
                    ->visible(fn (): bool => static::currentTenantHasCapability(Capability::PRODUCT_VARIANTS))
                    ->schema([
                        Forms\Components\Repeater::make('variants')
                            ->label('')
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label('Opción')
                                    ->placeholder('Ej: Tamaño')
                                    ->required(),

                                Forms\Components\TextInput::make('value')
                                    ->label('Valor')
                                    ->placeholder('Ej: Grande')
                                    ->required(),

                                Forms\Components\TextInput::make('price_modifier')
                                    ->label('Precio Extra')
                                    ->numeric()
                                    ->prefix('+L.')
                                    ->default(0),
                            ])
                            ->columns(3)
                            ->defaultItems(0)
                            ->addActionLabel('+ Agregar Variante')
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => isset($state['name'], $state['value'])
                                    ? "{$state['name']}: {$state['value']}"
                                    : null
                            ),
                    ])
                    ->collapsed(),
            ]);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // TABLA OPTIMIZADA PARA MÓVIL CON TOGGLE COLUMNS
    // ═══════════════════════════════════════════════════════════════════════

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                // Imagen cuadrada/circular
                Tables\Columns\ImageColumn::make('image_path')
                    ->label('')
                    ->square()
                    ->size(50),

                // Nombre con búsqueda
                Tables\Columns\TextColumn::make('name')
                    ->label('Producto')
                    ->searchable()
                    ->sortable()
                    ->limit(30)
                    ->weight('bold'),

                // ═══════════════════════════════════════════════════════════
                // EDICIÓN EN LÍNEA: Modo "Hoja de Cálculo"
                // ═══════════════════════════════════════════════════════════
                // Click → Editar → Blur = Guardar automático vía AJAX
                // Reduce tiempo de actualización de precios en 90%
                // ═══════════════════════════════════════════════════════════
                Tables\Columns\TextInputColumn::make('price')
                    ->label('Precio')
                    ->type('number')
                    ->rules(['required', 'numeric', 'min:0'])
                    ->sortable()
                    ->extraAttributes(['class' => 'text-success-600 font-bold'])
                    ->tooltip('Clic para editar precio'),

                // Sección del menú
                Tables\Columns\TextColumn::make('menuSection.name')
                    ->label('Sección')
                    ->badge()
                    ->color('gray')
                    ->visible(fn (): bool => static::currentTenantHasCapability(Capability::MENU_MODIFIERS))
                    ->toggleable(isToggledHiddenByDefault: true),

                // ═══════════════════════════════════════════════════════════
                // TOGGLE COLUMNS: Gestión rápida con un toque
                // ═══════════════════════════════════════════════════════════
                Tables\Columns\ToggleColumn::make('is_visible')
                    ->label('Visible')
                    ->onColor('success')
                    ->offColor('danger')
                    ->tooltip('Clic para cambiar visibilidad'),

                Tables\Columns\ToggleColumn::make('is_featured')
                    ->label('⭐')
                    ->onColor('warning')
                    ->visible(fn (): bool => static::currentTenantIsPro())
                    ->tooltip('Producto destacado'),

                // Fecha de creación
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            // ═══════════════════════════════════════════════════════════════
            // AGRUPACIÓN VISUAL: Vista estructurada por secciones
            // ═══════════════════════════════════════════════════════════════
            // Refleja el modelo mental del restaurantero (Desayunos, Almuerzos, Cenas)
            // ═══════════════════════════════════════════════════════════════
            ->groups([
                Tables\Grouping\Group::make('menuSection.name')
                    ->label('Sección del Menú')
                    ->collapsible()
                    ->titlePrefixedWithLabel(false),
            ])
            ->defaultGroup(
                static::currentTenantHasCapability(Capability::MENU_MODIFIERS) ? 'menuSection.name' : null
            )
            ->defaultSort('name', 'asc')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_visible')
                    ->label('Visibilidad')
                    ->placeholder('Todos')
                    ->trueLabel('Visibles')
                    ->falseLabel('Ocultos'),

                Tables\Filters\SelectFilter::make('menu_section_id')
                    ->relationship('menuSection', 'name')
                    ->visible(fn (): bool => static::currentTenantHasCapability(Capability::MENU_MODIFIERS))
                    ->label('Sección'),
            ])
            ->actions([
                EditAction::make()
                    ->iconButton(),

                // ═══════════════════════════════════════════════════════════
                // CLONACIÓN INTELIGENTE: Crear variaciones rápidamente
                // ═══════════════════════════════════════════════════════════
                // Hamburguesa Sencilla → Hamburguesa Doble en 1 clic
                // ═══════════════════════════════════════════════════════════
                ReplicateAction::make()
                    ->iconButton()
                    ->icon('heroicon-o-document-duplicate')
                    ->label('Duplicar')
                    // Excluir slug para evitar error de unicidad
                    ->excludeAttributes(['slug', 'created_at', 'updated_at'])
                    // Modificar datos antes de guardar
                    ->mutateRecordDataUsing(function (array $data): array {
                        // Añadir sufijo al nombre
                        $data['name'] = $data['name'].' (Copia)';
                        // Generar nuevo slug único
                        $data['slug'] = Str::slug($data['name']).'-'.uniqid();
                        // Nacer inactivo para evitar publicación accidental
                        $data['is_visible'] = false;
                        $data['is_featured'] = false;

                        return $data;
                    })
                    ->successNotificationTitle('Producto duplicado')
                    ->modalHeading('Duplicar producto')
                    ->modalDescription('Se creará una copia inactiva que podrás editar.')
                    ->modalSubmitActionLabel('Duplicar'),

                DeleteAction::make()
                    ->iconButton(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),

                    // ═══════════════════════════════════════════════════════════
                    // BULK ACTION PRO: Asignación Masiva de Sección
                    // ═══════════════════════════════════════════════════════════
                    // Resuelve el pain point de migrar 50+ productos al plan PRO
                    // Permite mover múltiples productos a una sección en 1 clic
                    // ═══════════════════════════════════════════════════════════
                    BulkAction::make('assign_section')
                        ->label('Mover a Sección')
                        ->icon('heroicon-o-folder-arrow-down')
                        ->color('primary')

                        // Solo visible cuando el tenant tiene organización de menú
                        ->visible(fn (): bool => static::currentTenantHasCapability(Capability::MENU_MODIFIERS))

                        // Modal con selector de sección
                        ->form([
                            Forms\Components\Select::make('menu_section_id')
                                ->label('Selecciona la sección destino')
                                ->placeholder('¿A qué sección mover estos productos?')
                                ->required()
                                // SEGURIDAD: Scoped al tenant actual
                                ->options(function () {
                                    return \App\Models\MenuSection::query()
                                        ->where('tenant_id', static::currentTenantId())
                                        ->where('is_active', true)
                                        ->orderBy('sort_order')
                                        ->pluck('name', 'id');
                                })
                                ->searchable()
                                ->preload()
                                ->helperText('Solo se muestran tus secciones activas'),
                        ])

                        // Motor de procesamiento
                        ->action(function (Collection $records, array $data): void {
                            $sectionId = $data['menu_section_id'];
                            $count = $records->count();

                            // Actualización masiva eficiente
                            $records->each(function ($record) use ($sectionId) {
                                $record->update(['menu_section_id' => $sectionId]);
                            });

                            // Obtener nombre de la sección para el mensaje
                            $section = \App\Models\MenuSection::find($sectionId);
                            $sectionName = $section instanceof \App\Models\MenuSection ? $section->name : 'la sección';

                            // Notificación de éxito
                            Notification::make()
                                ->success()
                                ->title('¡Productos movidos!')
                                ->body("Se han movido {$count} productos a \"{$sectionName}\".")
                                ->icon('heroicon-o-check-circle')
                                ->send();
                        })

                        // Limpiar selección después de completar
                        ->deselectRecordsAfterCompletion()

                        // Confirmación antes de ejecutar
                        ->requiresConfirmation()
                        ->modalHeading('Mover productos a sección')
                        ->modalDescription('Selecciona la sección destino para los productos seleccionados.')
                        ->modalSubmitActionLabel('Mover productos'),
                ]),
            ])
            ->emptyStateHeading('No tienes productos')
            ->emptyStateDescription('Crea tu primer producto para empezar a vender.')
            ->emptyStateIcon('heroicon-o-shopping-bag')
            ->poll('30s'); // Actualizar cada 30s
    }

    public static function getRelations(): array
    {
        return [
            OptionGroupsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }

    protected static function getCurrentTenant(): ?Tenant
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Tenant ? $tenant : null;
    }

    protected static function currentTenantId(): int|string|null
    {
        return static::getCurrentTenant()?->getKey();
    }

    protected static function currentTenantSlug(): string
    {
        $tenant = static::getCurrentTenant();

        return $tenant instanceof Tenant ? $tenant->slug : '';
    }

    protected static function currentTenantIsPro(): bool
    {
        $tenant = static::getCurrentTenant();

        return $tenant instanceof Tenant ? (bool) $tenant->is_pro : false;
    }

    protected static function currentTenantHasCapability(Capability $capability): bool
    {
        $tenant = static::getCurrentTenant();

        return $tenant?->hasCapability($capability) ?? false;
    }
}
