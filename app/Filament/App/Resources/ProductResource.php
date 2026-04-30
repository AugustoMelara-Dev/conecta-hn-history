<?php

declare(strict_types=1);

namespace App\Filament\App\Resources;

use App\Domain\TenantGovernance\Enums\Capability;
use App\Domain\TenantGovernance\Enums\TenantFeature;
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
 * Implementa el "muro de contención" para límites de suscripción.
 *
 * Características:
 * - Instagram-style image upload con editor 1:1
 * - Toggle columns para gestión rápida
 * - Límite de productos según plan (`canCreate`)
 */
class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?int $navigationSort = 1;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shopping-bag';

    /**
     * Small request-local cache to avoid recomputing the same plan counts
     * several times while Filament builds headings, actions and widgets.
     *
     * @var array<string, array{count:int, limit:int|null, percentage:int, canCreate:bool, isPro:bool}>
     */
    protected static array $productLimitInfoCache = [];

    public static function getNavigationLabel(): string
    {
        return 'Productos';
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

    // ---
    // Muro de contención: límite de productos según plan.
    // ---

    /**
     * Verifica si el usuario puede crear más productos
     */
    public static function canCreate(): bool
    {
        return (bool) (static::getProductLimitInfo()['canCreate'] ?? false);
    }

    /**
     * Obtiene información del límite para mostrar en UI.
     */
    public static function getProductLimitInfo(): array
    {
        $tenant = static::getCurrentTenant();

        if (! $tenant) {
            return ['count' => 0, 'limit' => 0, 'percentage' => 0, 'canCreate' => false, 'isPro' => false];
        }

        $cacheKey = (string) $tenant->getKey();

        if (isset(static::$productLimitInfoCache[$cacheKey])) {
            return static::$productLimitInfoCache[$cacheKey];
        }

        $policyAllowsCreate = Gate::allows('create', Product::class);
        $currentCount = DB::table('products')
            ->where('tenant_id', $tenant->id)
            ->whereNull('deleted_at')
            ->count();

        if ($tenant->hasFeature(TenantFeature::ADVANCED_CATALOG)) {
            return static::$productLimitInfoCache[$cacheKey] = [
                'count' => $currentCount,
                'limit' => null,
                'percentage' => 0,
                'canCreate' => $policyAllowsCreate,
                'isPro' => true,
            ];
        }

        $settings = app(GeneralSettings::class);
        $limit = (int) ($settings->free_plan_product_limit ?? 10);
        $percentage = $limit > 0 ? min(100, (int) round(($currentCount / $limit) * 100)) : 100;

        return static::$productLimitInfoCache[$cacheKey] = [
            'count' => $currentCount,
            'limit' => $limit,
            'percentage' => $percentage,
            'canCreate' => $policyAllowsCreate && $currentCount < $limit,
            'isPro' => false,
        ];
    }

    // ---
    // Formulario visual optimizado para móvil.
    // ---

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                // ---
                // Sección 1: imagen principal.
                // ---
                Section::make('Imagen del producto')
                    ->description(fn (): string => static::currentTenantIsPro()
                        ? 'Sube hasta 5 imágenes. Arrastra para reordenar.'
                        : 'Plan gratuito: solo 1 imagen. Actualiza a PRO para publicar más.')
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
                            // PRO: multiples imagenes
                            ->maxFiles(fn (): int => static::currentTenantIsPro() ? 5 : 1)
                            ->multiple(fn (): bool => static::currentTenantIsPro())
                            ->reorderable(fn (): bool => static::currentTenantIsPro())
                            ->panelLayout('grid')
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),

                // ---
                // Sección 2: datos esenciales.
                // ---
                Section::make('Información básica')
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
                                    ->modalHeading('Generación inteligente')
                                    ->modalDescription('Usaremos tu descripción actual para generar un texto persuasivo y optimizado. Esto consumirá créditos de tu presupuesto de IA.')
                                    ->modalSubmitActionLabel('Generar perfil')
                                    ->action(function (Set $set, Get $get): void {
                                        $tenant = static::getCurrentTenant();
                                        $user = auth()->user();
                                        $productName = trim((string) ($get('name') ?? ''));
                                        $currentDescription = trim((string) ($get('description') ?? ''));

                                        if (! $tenant || ! $user) {
                                            Notification::make()
                                                ->title('Sesión no disponible')
                                                ->body('No pudimos identificar tu sesión o negocio actual.')
                                                ->danger()
                                                ->send();

                                            return;
                                        }

                                        if ($productName === '' || $currentDescription === '') {
                                            Notification::make()
                                                ->title('Faltan datos')
                                                ->body('Ingresa un nombre y una descripción básica primero.')
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
                                                ->body('La descripción de tu producto ha sido optimizada.')
                                                ->success()
                                                ->send();
                                        } catch (\App\Exceptions\InsufficientAiCreditsException $e) {
                                            Notification::make()
                                                ->title('Presupuesto agotado')
                                                ->body('Has alcanzado tu límite de IA este mes.')
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
                                                ->body('La IA está descansando. Intenta de nuevo.')
                                                ->danger()
                                                ->send();
                                        }
                                    })
                            ),
                    ])
                    ->columns(2),

                // ---
                // Sección 3: organización.
                // ---
                Section::make('Organización')
                    ->schema([
                        Forms\Components\Select::make('category_id')
                            ->relationship('category', 'name')
                            ->label('Categoría')
                            ->searchable()
                            ->preload()
                            ->required(),

                        // Selector de seccion para negocios PRO.
                        // Incluye scoping por tenant y creación rápida.
                        Forms\Components\Select::make('menu_section_id')
                            // Solo visible para negocios con organizacion de menu.
                            ->visible(fn (): bool => static::currentTenantHasCapability(Capability::MENU_MODIFIERS))

                            // Seguridad: filtro estricto por tenant_id.
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

                            // Creacion en linea.
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')
                                    ->label('Nombre de la Sección')
                                    ->placeholder('Ej: Vinos Importados, Postres Especiales')
                                    ->required()
                                    ->maxLength(100)
                                    ->autofocus(),

                                // Seccion activa por defecto.
                                Forms\Components\Hidden::make('is_active')
                                    ->default(true),

                                // Sort order al final.
                                Forms\Components\Hidden::make('sort_order')
                                    ->default(fn (): int => ((int) \App\Models\MenuSection::where('tenant_id', static::currentTenantId())->max('sort_order')) + 1),
                            ])
                            ->createOptionModalHeading('Crear Nueva Sección')
                            ->helperText('Organiza tu catálogo en secciones arrastrables.'),

                        // Campo senuelo para plan Base con upsell a PRO.
                        Forms\Components\Select::make('menu_section_id_locked')
                            // Mostrar versión bloqueada cuando el tenant no tenga esta capacidad.
                            ->visible(fn (): bool => ! static::currentTenantHasCapability(Capability::MENU_MODIFIERS))

                            // Estado bloqueado.
                            ->disabled()
                            ->dehydrated(false) // No se envia al backend.

                            ->label('Sección del menú')
                            ->placeholder('Disponible solo en Plan PRO')
                            ->options([])

                            // Mensaje persuasivo con link de upsell.
                            ->helperText(new \Illuminate\Support\HtmlString(
                                '<span class="text-gray-600">Organiza tus productos en secciones personalizadas.</span> '.
                                '<a href="'.route('filament.app.pages.manage-billing', ['tenant' => static::currentTenantSlug()]).'" '.
                                'class="text-primary-600 font-bold hover:underline">Desbloquear con PRO</a>'
                            )),
                    ])
                    ->columns(2)
                    ->collapsible(),

                // Sección 4: control de estado.
                Section::make('Control')
                    ->schema([
                        Forms\Components\Toggle::make('is_visible')
                            ->label('Visible en Catálogo')
                            ->default(true)
                            ->helperText('Desactiva para ocultar temporalmente'),

                        Forms\Components\Toggle::make('is_featured')
                            ->label('Producto destacado')
                            ->default(false)
                            ->visible(fn (): bool => static::currentTenantIsPro())
                            ->helperText('Aparecerá primero en tu catálogo'),

                        // Cross-selling para negocios PRO.
                        Forms\Components\Toggle::make('is_impulse_buy')
                            ->label('Producto de Impulso (Cross-Selling)')
                            ->default(false)
                            ->onColor('success')
                            ->visible(fn (): bool => static::currentTenantIsPro())
                            ->helperText('Activar esta opción sugerirá este producto en el checkout para aumentar el ticket promedio.'),
                    ])
                    ->columns(2)
                    ->collapsed(),

                // Upsell marketing para negocios Base.
                Section::make('Desbloquea funciones PRO')
                    ->description('Mejora tu presencia y convierte más clientes.')
                    ->visible(fn (): bool => ! static::currentTenantIsPro())
                    ->schema([
                        Forms\Components\Placeholder::make('upsell_features')
                            ->label('')
                            ->content(new \Illuminate\Support\HtmlString('
                                <div class="space-y-3">
                                    <div class="flex items-center gap-3 p-3 bg-gradient-to-r from-amber-50 to-amber-100 dark:from-amber-900/20 dark:to-amber-800/20 rounded-lg border border-amber-200 dark:border-amber-700">
                                        <span class="text-sm font-semibold text-amber-700 dark:text-amber-300">PRO</span>
                                        <div>
                                            <p class="font-semibold text-amber-800 dark:text-amber-200">Galería de Fotos</p>
                                            <p class="text-sm text-amber-600 dark:text-amber-400">Sube hasta 5 imágenes por producto</p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-3 p-3 bg-gradient-to-r from-blue-50 to-blue-100 dark:from-blue-900/20 dark:to-blue-800/20 rounded-lg border border-blue-200 dark:border-blue-700">
                                        <span class="text-sm font-semibold text-blue-700 dark:text-blue-300">MENÚ</span>
                                        <div>
                                            <p class="font-semibold text-blue-800 dark:text-blue-200">Secciones de Menú</p>
                                            <p class="text-sm text-blue-600 dark:text-blue-400">Organiza tus productos por categorías</p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-3 p-3 bg-gradient-to-r from-purple-50 to-purple-100 dark:from-purple-900/20 dark:to-purple-800/20 rounded-lg border border-purple-200 dark:border-purple-700">
                                        <span class="text-sm font-semibold text-purple-700 dark:text-purple-300">TOP</span>
                                        <div>
                                            <p class="font-semibold text-purple-800 dark:text-purple-200">Productos destacados</p>
                                            <p class="text-sm text-purple-600 dark:text-purple-400">Muestra tus mejores productos primero</p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-3 p-3 bg-gradient-to-r from-green-50 to-green-100 dark:from-green-900/20 dark:to-green-800/20 rounded-lg border border-green-200 dark:border-green-700">
                                        <span class="text-sm font-semibold text-green-700 dark:text-green-300">PLUS</span>
                                        <div>
                                            <p class="font-semibold text-green-800 dark:text-green-200">Variantes de Producto</p>
                                            <p class="text-sm text-green-600 dark:text-green-400">Tallas, colores, sabores con precios extras</p>
                                        </div>
                                    </div>
                                </div>
                            ')),

                        SchemaActions::make([
                            Action::make('upgrade_to_pro')
                                ->label('Mejorar a plan PRO')
                                ->color('warning')
                                ->size('lg')
                                ->extraAttributes(['class' => 'w-full justify-center'])
                                ->url(fn (): string => route('filament.app.pages.manage-billing', ['tenant' => static::currentTenantSlug()])),
                        ])->fullWidth(),
                    ])
                    ->collapsible(),

                // Seccion 5: variantes para negocios PRO.
                Section::make('Variantes del producto')
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

    // ---
    // Tabla optimizada para móvil con columnas activables.
    // ---

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

                // ---
                // Edición en línea para ajustar precios con pocos toques.
                // ---
                Tables\Columns\TextInputColumn::make('price')
                    ->label('Precio')
                    ->type('number')
                    ->rules(['required', 'numeric', 'min:0'])
                    ->sortable()
                    ->extraAttributes(['class' => 'text-success-600 font-bold'])
                    ->tooltip('Clic para editar precio'),

                // Seccion del menu.
                Tables\Columns\TextColumn::make('menuSection.name')
                    ->label('Sección')
                    ->badge()
                    ->color('gray')
                    ->visible(fn (): bool => static::currentTenantHasCapability(Capability::MENU_MODIFIERS))
                    ->toggleable(isToggledHiddenByDefault: true),

                // Toggles rápidos para visibilidad y destacado.
                Tables\Columns\ToggleColumn::make('is_visible')
                    ->label('Visible')
                    ->onColor('success')
                    ->offColor('danger')
                    ->tooltip('Clic para cambiar visibilidad'),

                Tables\Columns\ToggleColumn::make('is_featured')
                    ->label('Destacado')
                    ->onColor('warning')
                    ->visible(fn (): bool => static::currentTenantIsPro())
                    ->tooltip('Producto destacado'),

                // Fecha de creacion.
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            // Agrupacion visual por secciones del menu.
            // Refleja el modelo mental del restaurantero.
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

                // Duplicado rapido para crear variaciones.
                ReplicateAction::make()
                    ->iconButton()
                    ->icon('heroicon-o-document-duplicate')
                    ->label('Duplicar')
                    // Excluir slug para evitar error de unicidad.
                    ->excludeAttributes(['slug', 'created_at', 'updated_at'])
                    // Ajustar datos antes de guardar.
                    ->mutateRecordDataUsing(function (array $data): array {
                        // Anadir sufijo al nombre.
                        $data['name'] = $data['name'].' (Copia)';
                        // Generar un nuevo slug unico.
                        $data['slug'] = Str::slug($data['name']).'-'.uniqid();
                        // Nacer inactivo para evitar publicacion accidental.
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

                    // Acción masiva para mover productos entre secciones.
                    BulkAction::make('assign_section')
                        ->label('Mover a Sección')
                        ->icon('heroicon-o-folder-arrow-down')
                        ->color('primary')

                        // Solo visible cuando el tenant tiene organizacion de menu.
                        ->visible(fn (): bool => static::currentTenantHasCapability(Capability::MENU_MODIFIERS))

                        // Modal con selector de seccion.
                        ->form([
                            Forms\Components\Select::make('menu_section_id')
                                ->label('Selecciona la sección destino')
                                ->placeholder('¿A qué sección quieres mover estos productos?')
                                ->required()
                                // Seguridad: scoped al tenant actual.
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

                        // Motor de procesamiento.
                        ->action(function (Collection $records, array $data): void {
                            $sectionId = $data['menu_section_id'];
                            $count = $records->count();

                            // Actualización masiva eficiente.
                            $records->each(function ($record) use ($sectionId) {
                                $record->update(['menu_section_id' => $sectionId]);
                            });

                            // Obtener nombre de la seccion para el mensaje.
                            $section = \App\Models\MenuSection::find($sectionId);
                            $sectionName = $section instanceof \App\Models\MenuSection ? $section->name : 'la sección';

                            // Notificacion de exito.
                            Notification::make()
                                ->success()
                                ->title('¡Productos movidos!')
                                ->body("Se han movido {$count} productos a \"{$sectionName}\".")
                                ->icon('heroicon-o-check-circle')
                                ->send();
                        })

                        // Limpiar seleccion despues de completar.
                        ->deselectRecordsAfterCompletion()

                        // Confirmacion antes de ejecutar.
                        ->requiresConfirmation()
                        ->modalHeading('Mover productos a sección')
                        ->modalDescription('Selecciona la sección destino para los productos seleccionados.')
                        ->modalSubmitActionLabel('Mover productos'),
                ]),
            ])
            ->emptyState(fn () => view('filament.app.components.table-empty-state', [
                'heading' => 'Todavía no tienes productos',
                'description' => 'Crea tu primer producto para empezar a vender y llenar tu catálogo público.',
                'buttonLabel' => 'Crear producto',
                'buttonUrl' => static::getUrl('create'),
            ]))
            ->poll(null);
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

    public static function currentTenantSlug(): string
    {
        $tenant = static::getCurrentTenant();

        return $tenant instanceof Tenant ? $tenant->slug : '';
    }

    protected static function currentTenantIsPro(): bool
    {
        $tenant = static::getCurrentTenant();

        return $tenant instanceof Tenant ? $tenant->hasFeature(TenantFeature::ADVANCED_CATALOG) : false;
    }

    protected static function currentTenantHasCapability(Capability $capability): bool
    {
        $tenant = static::getCurrentTenant();

        return $tenant?->hasCapability($capability) ?? false;
    }
}
