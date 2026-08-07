<?php

declare(strict_types=1);

namespace App\Filament\App\Resources;

use App\Application\Markets\TenantMarketResolver;
use App\Application\Products\MerchantProductCatalogAdapter;
use App\Domain\TenantGovernance\Services\PlanFeatureFlagService;
use App\Filament\App\Resources\ProductResource\Pages;
use App\Filament\App\Resources\ProductResource\RelationManagers\OptionGroupsRelationManager;
use App\Models\MenuSection;
use App\Models\Product;
use App\Models\Tenant;
use App\Support\Product\TenantAccessMatrix;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

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

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-shopping-bag';

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
        return 'Catalogo';
    }

    public static function canViewAny(): bool
    {
        return TenantAccessMatrix::currentTenantCanAccess(TenantAccessMatrix::MODULE_PRODUCTS);
    }

    /** The Filament tenant, rather than submitted state, owns catalog authority. */
    public static function trustedTenant(): Tenant
    {
        $tenant = Filament::getTenant();
        if (! $tenant instanceof Tenant) {
            throw new \LogicException('Merchant product writes require a trusted Filament tenant.');
        }

        return $tenant;
    }

    public static function currencyCode(): string
    {
        return app(TenantMarketResolver::class)->resolve(static::trustedTenant())->currencyCode;
    }

    /**
     * Merchant uploads are staged privately under the trusted tenant context,
     * normalized server-side, and published only after validation succeeds.
     */
    public static function merchantMediaWritesEnabled(): bool
    {
        return true;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    // ═══════════════════════════════════════════════════════════════════════
    // MURO DE CONTENCIÓN: Límite de productos según plan
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Verifica si el usuario puede crear más productos
     */
    public static function canCreate(): bool
    {
        $tenant = Filament::getTenant();

        if (! $tenant instanceof Tenant) {
            return false;
        }

        $limit = app(PlanFeatureFlagService::class)->getMaxProductLimit($tenant);

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
        $tenant = Filament::getTenant();

        if (! $tenant instanceof Tenant) {
            return ['count' => 0, 'limit' => 0, 'percentage' => 0, 'canCreate' => false, 'isPro' => false];
        }

        $limit = app(PlanFeatureFlagService::class)->getMaxProductLimit($tenant);

        $currentCount = DB::table('products')
            ->where('tenant_id', $tenant->id)
            ->whereNull('deleted_at')
            ->count();

        $percentage = $limit === PHP_INT_MAX || $limit <= 0
            ? ($limit <= 0 && $currentCount > 0 ? 100 : 0)
            : min(100, round(($currentCount / $limit) * 100));

        return [
            'count' => $currentCount,
            'limit' => $limit,
            'percentage' => $percentage,
            'canCreate' => static::canCreate(),
            'isPro' => (bool) $tenant->is_pro,
        ];
    }

    // ═══════════════════════════════════════════════════════════════════════
    // FORMULARIO VISUAL OPTIMIZADO PARA MÓVIL
    // ═══════════════════════════════════════════════════════════════════════

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                // ═══════════════════════════════════════════════════════════
                // SECCIÓN 1: IMAGEN INSTAGRAM-STYLE
                // ═══════════════════════════════════════════════════════════
                Forms\Components\Section::make('Imagen del producto')
                    ->description(fn () => static::trustedTenant()->is_pro
                        ? 'Sube una imagen principal segura para tu producto.'
                        : 'Sube una imagen principal segura para tu producto.')
                    ->schema([
                        Forms\Components\Placeholder::make('current_image_preview')
                            ->label('Imagen actual')
                            ->content(function (?Product $record): HtmlString|string {
                                if ($record === null || ! is_string($record->image_url) || $record->image_url === '') {
                                    return 'Este producto todavia no tiene imagen.';
                                }

                                $url = e($record->image_url);
                                $alt = e('Imagen actual de '.$record->name);

                                return new HtmlString(
                                    "<img src=\"{$url}\" alt=\"{$alt}\" class=\"h-40 w-40 rounded-xl object-cover\" loading=\"lazy\">"
                                );
                            })
                            ->dehydrated(false)
                            ->columnSpanFull(),

                        Forms\Components\Toggle::make('remove_image')
                            ->label('Eliminar la imagen actual')
                            ->helperText('La eliminacion se aplica de forma segura al guardar.')
                            ->visible(fn (?Product $record): bool => $record !== null && filled($record->image_path))
                            ->dehydrated()
                            ->columnSpanFull(),

                        Forms\Components\FileUpload::make('pending_image_path')
                            ->label('')
                            ->image()
                            ->disk('local')
                            ->directory(fn (): string => 'manual-product-staging/'.static::trustedTenant()->getKey())
                            ->visibility('private')
                            ->getUploadedFileNameForStorageUsing(
                                fn (TemporaryUploadedFile $file): string => Str::uuid().'.'.strtolower($file->getClientOriginalExtension()),
                            )
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->maxSize(10240)
                            ->maxFiles(1)
                            ->multiple(false)
                            ->dehydrated()
                            ->helperText('La imagen se guarda primero en privado, se normaliza y solo después se publica dentro de tu negocio.')
                            ->panelLayout('grid')
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),

                // ═══════════════════════════════════════════════════════════
                // SECCIÓN 2: DATOS ESENCIALES
                // ═══════════════════════════════════════════════════════════
                Forms\Components\Section::make('Informacion basica')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nombre del producto')
                            ->required()
                            ->maxLength(100)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn ($state, $set) => $set('slug', Str::slug($state)))
                            ->placeholder('Ej: Baleada Sencilla'),

                        Forms\Components\Hidden::make('slug'),

                        Forms\Components\TextInput::make('price')
                            ->label('Precio')
                            ->numeric()
                            ->prefix(fn (): string => static::currencyCode())
                            ->required()
                            ->minValue(0)
                            ->step(0.01)
                            ->placeholder('0.00'),

                        Forms\Components\Textarea::make('description')
                            ->label('Descripcion')
                            ->rows(3)
                            ->maxLength(500)
                            ->placeholder('Describe tu producto brevemente...')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                // ═══════════════════════════════════════════════════════════
                // SECCIÓN 3: ORGANIZACIÓN
                // ═══════════════════════════════════════════════════════════
                Forms\Components\Section::make('Organizacion')
                    ->schema([
                        Forms\Components\Select::make('category_id')
                            ->relationship('category', 'name')
                            ->label('Categoria')
                            ->searchable()
                            ->preload()
                            ->required(),

                        // ═══════════════════════════════════════════════════
                        // Selector de sección para negocios PRO.
                        // ═══════════════════════════════════════════════════
                        // VERSIÓN A: Campo funcional para usuarios PRO
                        // - Relación con tenant scoping
                        // - Creación en línea con modal
                        // ═══════════════════════════════════════════════════
                        Forms\Components\Select::make('menu_section_id')
                            // Solo visible para usuarios PRO
                            ->visible(fn () => static::trustedTenant()->is_pro)

                            // SEGURIDAD: Filtro estricto por tenant_id
                            ->relationship(
                                'menuSection',
                                'name',
                                fn ($query) => $query
                                    ->where('tenant_id', Filament::getTenant()?->id)
                                    ->where('is_active', true)
                                    ->orderBy('sort_order')
                            )
                            ->label('Seccion del menu')
                            ->placeholder('Selecciona o crea una seccion...')
                            ->searchable()
                            ->preload()

                            // Creación en línea del catálogo sin salir del producto.
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')
                                    ->label('Nombre de la seccion')
                                    ->placeholder('Ej: Vinos Importados, Postres Especiales')
                                    ->required()
                                    ->maxLength(100)
                                    ->autofocus(),

                            ])
                            ->createOptionUsing(function (array $data): int {
                                $section = app(MerchantProductCatalogAdapter::class)
                                    ->createSection(static::trustedTenant(), $data);

                                return (int) $section->getKey();
                            })
                            ->createOptionModalHeading('Crear nueva seccion')
                            ->helperText('Organiza tu catalogo en secciones arrastrables.'),

                        // ═══════════════════════════════════════════════════
                        // Campo bloqueado para planes Free, con CTA nativo de Filament.
                        Forms\Components\Select::make('menu_section_id_locked')
                            // Solo visible para usuarios FREE
                            ->visible(fn () => ! (static::trustedTenant()->is_pro))

                            // Estado bloqueado
                            ->disabled()
                            ->dehydrated(false) // No se envía al backend

                            ->label('Seccion del menu')
                            ->placeholder('Disponible solo en Plan PRO')
                            ->options([])

                            ->helperText('Organiza tus productos en secciones personalizadas.')
                            ->hintAction(
                                Forms\Components\Actions\Action::make('unlock_menu_sections')
                                    ->label('Desbloquear con PRO')
                                    ->icon('heroicon-o-arrow-up-right')
                                    ->url(fn () => route('filament.app.pages.manage-billing', ['tenant' => static::trustedTenant()->slug]))
                            ),
                    ])
                    ->columns(2)
                    ->collapsible(),

                // ═══════════════════════════════════════════════════════════
                // SECCIÓN 4: CONTROL DE ESTADO
                // ═══════════════════════════════════════════════════════════
                Forms\Components\Section::make('Control')
                    ->schema([
                        Forms\Components\Toggle::make('is_visible')
                            ->label('Visible en catalogo')
                            ->default(true)
                            ->helperText('Desactiva para ocultar temporalmente'),

                        Forms\Components\Toggle::make('is_featured')
                            ->label('Producto destacado')
                            ->default(false)
                            ->visible(fn () => static::trustedTenant()->is_pro)
                            ->helperText('Aparecera primero en tu catalogo.'),

                        // ═══════════════════════════════════════════════════
                        // Producto de impulso para sugerencias en checkout.
                        // ═══════════════════════════════════════════════════
                        Forms\Components\Toggle::make('is_impulse_buy')
                            ->label('Producto de impulso')
                            ->default(false)
                            ->onColor('success')
                            ->visible(fn () => static::trustedTenant()->is_pro)
                            ->helperText('Activa esta opcion para sugerir el producto en el checkout y aumentar el ticket promedio.'),
                    ])
                    ->columns(2)
                    ->collapsed(),

                // ═══════════════════════════════════════════════════════════
                // SECCIÓN: UPSELL MARKETING (Solo para Free)
                // ═══════════════════════════════════════════════════════════
                Forms\Components\Section::make('Funciones PRO')
                    ->description('Mejora tu presencia y convierte mas clientes.')
                    ->visible(fn () => ! static::trustedTenant()->is_pro)
                    ->schema([
                        Forms\Components\Placeholder::make('upsell_features')
                            ->label('')
                            ->content(new HtmlString('
                                <div class="biz-list">
                                    <div class="biz-split-item">
                                        <strong>Galeria de fotos</strong>
                                        <span class="biz-muted">Sube una imagen principal optimizada por producto.</span>
                                    </div>
                                    <div class="biz-split-item">
                                        <strong>Secciones de menu</strong>
                                        <span class="biz-muted">Organiza tus productos por categorias.</span>
                                    </div>
                                    <div class="biz-split-item">
                                        <strong>Productos destacados</strong>
                                        <span class="biz-muted">Muestra tus mejores productos primero.</span>
                                    </div>
                                    <div class="biz-split-item">
                                        <strong>Variantes de producto</strong>
                                        <span class="biz-muted">Configura tallas, colores o sabores con precio extra.</span>
                                    </div>
                                </div>
                            ')),

                        Forms\Components\Actions::make([
                            Forms\Components\Actions\Action::make('upgrade_to_pro')
                                ->label('Mejorar a Plan PRO')
                                ->color('warning')
                                ->size('lg')
                                ->extraAttributes(['class' => 'w-full justify-center'])
                                ->url(fn () => route('filament.app.pages.manage-billing', ['tenant' => static::trustedTenant()->slug])),
                        ])->fullWidth(),
                    ])
                    ->collapsible(),

                // ═══════════════════════════════════════════════════════════
                // SECCIÓN 5: VARIANTES (Solo PRO)
                // ═══════════════════════════════════════════════════════════
                Forms\Components\Section::make('Variantes del producto')
                    ->description('Define opciones como talla, color o tamano.')
                    ->visible(fn () => static::trustedTenant()->is_pro)
                    ->schema([
                        Forms\Components\Repeater::make('variants')
                            ->label('')
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label('Opcion')
                                    ->placeholder('Ej: Tamano')
                                    ->required(),

                                Forms\Components\TextInput::make('value')
                                    ->label('Valor')
                                    ->placeholder('Ej: Grande')
                                    ->required(),

                                Forms\Components\TextInput::make('price_modifier')
                                    ->label('Precio extra')
                                    ->numeric()
                                    ->prefix('+L.')
                                    ->default(0),
                            ])
                            ->columns(3)
                            ->defaultItems(0)
                            ->addActionLabel('Agregar variante')
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
                    ->updateStateUsing(function (Product $record, mixed $state): mixed {
                        app(MerchantProductCatalogAdapter::class)->setPrice(static::trustedTenant(), $record, $state);

                        return $state;
                    })
                    ->tooltip('Clic para editar precio'),

                // Sección del menú
                Tables\Columns\TextColumn::make('menuSection.name')
                    ->label('Seccion')
                    ->badge()
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),

                // ═══════════════════════════════════════════════════════════
                // TOGGLE COLUMNS: Gestión rápida con un toque
                // ═══════════════════════════════════════════════════════════
                Tables\Columns\ToggleColumn::make('is_visible')
                    ->label('Visible')
                    ->onColor('success')
                    ->offColor('danger')
                    ->updateStateUsing(function (Product $record, bool $state): bool {
                        app(MerchantProductCatalogAdapter::class)->setVisibility(static::trustedTenant(), $record, $state);

                        return $state;
                    })
                    ->tooltip('Clic para cambiar visibilidad'),

                Tables\Columns\ToggleColumn::make('is_featured')
                    ->label('Destacado')
                    ->onColor('warning')
                    ->visible(fn () => static::trustedTenant()->is_pro)
                    ->updateStateUsing(function (Product $record, bool $state): bool {
                        app(MerchantProductCatalogAdapter::class)->setFeatured(static::trustedTenant(), $record, $state);

                        return $state;
                    })
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
                    ->label('Seccion del menu')
                    ->collapsible()
                    ->titlePrefixedWithLabel(false),
            ])
            ->defaultGroup('menuSection.name')
            ->defaultSort('name', 'asc')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_visible')
                    ->label('Visibilidad')
                    ->placeholder('Todos')
                    ->trueLabel('Visibles')
                    ->falseLabel('Ocultos'),

                Tables\Filters\SelectFilter::make('menu_section_id')
                    ->relationship('menuSection', 'name')
                    ->label('Seccion'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->iconButton(),

                // ═══════════════════════════════════════════════════════════
                // CLONACIÓN INTELIGENTE: Crear variaciones rápidamente
                // ═══════════════════════════════════════════════════════════
                // Hamburguesa Sencilla → Hamburguesa Doble en 1 clic
                // ═══════════════════════════════════════════════════════════
                Tables\Actions\Action::make('clone')
                    ->iconButton()
                    ->icon('heroicon-o-document-duplicate')
                    ->label('Duplicar')
                    ->form([
                        Forms\Components\Hidden::make('attempt_id')
                            ->default(fn (): string => (string) Str::uuid()),
                    ])
                    ->action(function (Product $record, array $data, Tables\Actions\Action $action): void {
                        $attemptId = $data['attempt_id'] ?? null;
                        if (! is_string($attemptId) || ! Str::isUuid($attemptId)) {
                            throw new \InvalidArgumentException('Clone action requires a stable UUID attempt identity.');
                        }
                        app(MerchantProductCatalogAdapter::class)->clone(
                            static::trustedTenant(), $record, $attemptId,
                        );
                        $action->success();
                    })
                    ->successNotificationTitle('Producto duplicado')
                    ->modalHeading('Duplicar producto')
                    ->modalDescription('Se creara una copia inactiva que podras editar.')
                    ->modalSubmitActionLabel('Duplicar'),

                Tables\Actions\DeleteAction::make()
                    ->iconButton()
                    ->using(function (Product $record): bool {
                        app(MerchantProductCatalogAdapter::class)->delete(static::trustedTenant(), $record);

                        return true;
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('delete')
                        ->label('Eliminar')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            app(MerchantProductCatalogAdapter::class)->deleteMany(static::trustedTenant(), $records);
                        }),

                    // ═══════════════════════════════════════════════════════════
                    // BULK ACTION PRO: Asignación Masiva de Sección
                    // ═══════════════════════════════════════════════════════════
                    // Resuelve el pain point de migrar 50+ productos al plan PRO
                    // Permite mover múltiples productos a una sección en 1 clic
                    // ═══════════════════════════════════════════════════════════
                    Tables\Actions\BulkAction::make('assign_section')
                        ->label('Mover a seccion')
                        ->icon('heroicon-o-folder-arrow-down')
                        ->color('primary')

                        // Solo negocios PRO pueden usar esta herramienta.
                        ->visible(fn () => static::trustedTenant()->is_pro)

                        // Modal con selector de sección
                        ->form([
                            Forms\Components\Select::make('menu_section_id')
                                ->label('Selecciona la seccion destino')
                                ->placeholder('A que seccion mover estos productos?')
                                ->required()
                                // SEGURIDAD: Scoped al tenant actual
                                ->options(function () {
                                    return MenuSection::query()
                                        ->where('tenant_id', Filament::getTenant()?->id)
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

                            app(MerchantProductCatalogAdapter::class)->assignSection(
                                static::trustedTenant(),
                                $records,
                                (int) $sectionId,
                            );

                            // Obtener nombre de la sección para el mensaje
                            $sectionName = MenuSection::find($sectionId)?->name ?? 'la seccion';

                            // Notificación de éxito
                            Notification::make()
                                ->success()
                                ->title('Productos movidos')
                                ->body("Se han movido {$count} productos a \"{$sectionName}\".")
                                ->icon('heroicon-o-check-circle')
                                ->send();
                        })

                        // Limpiar selección después de completar
                        ->deselectRecordsAfterCompletion()

                        // Confirmación antes de ejecutar
                        ->requiresConfirmation()
                        ->modalHeading('Mover productos a seccion')
                        ->modalDescription('Selecciona la seccion destino para los productos seleccionados.')
                        ->modalSubmitActionLabel('Mover productos'),
                ]),
            ])
            ->emptyStateHeading('No tienes productos')
            ->emptyStateDescription('Crea tu primer producto para empezar a vender.')
            ->emptyStateIcon('heroicon-o-shopping-bag');
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
}

