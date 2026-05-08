<?php

declare(strict_types=1);

namespace App\Filament\App\Resources;

use App\Filament\App\Resources\ProductResource\Pages;
use App\Filament\App\Resources\ProductResource\RelationManagers\OptionGroupsRelationManager;
use App\Models\Product;
use App\Settings\GeneralSettings;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
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

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

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

    // ═══════════════════════════════════════════════════════════════════════
    // MURO DE CONTENCIÓN: Límite de productos según plan
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Verifica si el usuario puede crear más productos
     */
    public static function canCreate(): bool
    {
        $tenant = Filament::getTenant();

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
        $tenant = Filament::getTenant();

        if (! $tenant) {
            return ['count' => 0, 'limit' => 0, 'percentage' => 0, 'canCreate' => false];
        }

        if ($tenant->is_pro) {
            return ['count' => 0, 'limit' => PHP_INT_MAX, 'percentage' => 0, 'canCreate' => true, 'isPro' => true];
        }

        $settings = app(GeneralSettings::class);
        $limit = ($tenant->is_pro) ? '∞' : ($settings->free_plan_product_limit ?? 10);

        $currentCount = DB::table('products')
            ->where('tenant_id', $tenant->id)
            ->whereNull('deleted_at')
            ->count();

        $percentage = ($limit === '∞') ? 0 : min(100, round(($currentCount / $limit) * 100));

        return [
            'count' => $currentCount,
            'limit' => $limit,
            'percentage' => $percentage,
            'canCreate' => static::canCreate(),
        ];
    }

    // ═══════════════════════════════════════════════════════════════════════
    // FORMULARIO VISUAL OPTIMIZADO PARA MÓVIL
    // ═══════════════════════════════════════════════════════════════════════

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // ═══════════════════════════════════════════════════════════
                // SECCIÓN 1: IMAGEN INSTAGRAM-STYLE
                // ═══════════════════════════════════════════════════════════
                Forms\Components\Section::make('Imagen del producto')
                    ->description(fn () => Filament::getTenant()?->is_pro
                        ? 'Sube hasta 5 imagenes. Arrastra para reordenar.'
                        : 'Plan Semilla: 1 imagen. Actualiza a PRO para subir mas.')
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
                            ->imageResizeTargetWidth(800)
                            ->imageResizeTargetHeight(800)
                            // PRO: múltiples imágenes
                            ->maxFiles(fn () => Filament::getTenant()?->is_pro ? 5 : 1)
                            ->multiple(fn () => Filament::getTenant()?->is_pro)
                            ->reorderable(fn () => Filament::getTenant()?->is_pro)
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
                            ->prefix('L.')
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
                            ->visible(fn () => Filament::getTenant()?->is_pro ?? false)

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

                                // Inyección automática del tenant_id (seguridad)
                                Forms\Components\Hidden::make('tenant_id')
                                    ->default(fn () => Filament::getTenant()?->id),

                                // Sección activa por defecto
                                Forms\Components\Hidden::make('is_active')
                                    ->default(true),

                                // Sort order auto-calculado al final
                                Forms\Components\Hidden::make('sort_order')
                                    ->default(fn () => \App\Models\MenuSection::where('tenant_id', Filament::getTenant()?->id)->max('sort_order') + 1),
                            ])
                            ->createOptionModalHeading('Crear nueva seccion')
                            ->helperText('Organiza tu catalogo en secciones arrastrables.'),

                        // ═══════════════════════════════════════════════════
                        // Campo bloqueado para planes Free, con CTA nativo de Filament.
                        Forms\Components\Select::make('menu_section_id_locked')
                            // Solo visible para usuarios FREE
                            ->visible(fn () => ! (Filament::getTenant()?->is_pro ?? false))

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
                                    ->url(fn () => route('filament.app.pages.manage-billing', ['tenant' => Filament::getTenant()?->slug ?? '']))
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
                            ->visible(fn () => Filament::getTenant()?->is_pro)
                            ->helperText('Aparecera primero en tu catalogo.'),

                        // ═══════════════════════════════════════════════════
                        // Producto de impulso para sugerencias en checkout.
                        // ═══════════════════════════════════════════════════
                        Forms\Components\Toggle::make('is_impulse_buy')
                            ->label('Producto de impulso')
                            ->default(false)
                            ->onColor('success')
                            ->visible(fn () => Filament::getTenant()?->is_pro)
                            ->helperText('Activa esta opcion para sugerir el producto en el checkout y aumentar el ticket promedio.'),
                    ])
                    ->columns(2)
                    ->collapsed(),

                // ═══════════════════════════════════════════════════════════
                // SECCIÓN: UPSELL MARKETING (Solo para Free)
                // ═══════════════════════════════════════════════════════════
                Forms\Components\Section::make('Funciones PRO')
                    ->description('Mejora tu presencia y convierte mas clientes.')
                    ->visible(fn () => ! Filament::getTenant()?->is_pro)
                    ->schema([
                        Forms\Components\Placeholder::make('upsell_features')
                            ->label('')
                            ->content(new \Illuminate\Support\HtmlString('
                                <div class="biz-list">
                                    <div class="biz-split-item">
                                        <strong>Galeria de fotos</strong>
                                        <span class="biz-muted">Sube hasta 5 imagenes por producto.</span>
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
                                ->url(fn () => route('filament.app.pages.manage-billing', ['tenant' => Filament::getTenant()?->slug])),
                        ])->fullWidth(),
                    ])
                    ->collapsible(),

                // ═══════════════════════════════════════════════════════════
                // SECCIÓN 5: VARIANTES (Solo PRO)
                // ═══════════════════════════════════════════════════════════
                Forms\Components\Section::make('Variantes del producto')
                    ->description('Define opciones como talla, color o tamano.')
                    ->visible(fn () => Filament::getTenant()?->is_pro)
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
                    ->tooltip('Clic para cambiar visibilidad'),

                Tables\Columns\ToggleColumn::make('is_featured')
                    ->label('Destacado')
                    ->onColor('warning')
                    ->visible(fn () => Filament::getTenant()?->is_pro)
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
                Tables\Actions\ReplicateAction::make()
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
                    ->modalDescription('Se creara una copia inactiva que podras editar.')
                    ->modalSubmitActionLabel('Duplicar'),

                Tables\Actions\DeleteAction::make()
                    ->iconButton(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),

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
                        ->visible(fn () => Filament::getTenant()?->is_pro ?? false)

                        // Modal con selector de sección
                        ->form([
                            Forms\Components\Select::make('menu_section_id')
                                ->label('Selecciona la seccion destino')
                                ->placeholder('A que seccion mover estos productos?')
                                ->required()
                                // SEGURIDAD: Scoped al tenant actual
                                ->options(function () {
                                    return \App\Models\MenuSection::query()
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

                            // Actualización masiva eficiente
                            $records->each(function ($record) use ($sectionId) {
                                $record->update(['menu_section_id' => $sectionId]);
                            });

                            // Obtener nombre de la sección para el mensaje
                            $sectionName = \App\Models\MenuSection::find($sectionId)?->name ?? 'la seccion';

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
