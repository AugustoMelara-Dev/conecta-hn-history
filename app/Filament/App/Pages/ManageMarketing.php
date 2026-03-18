<?php

declare(strict_types=1);

namespace App\Filament\App\Pages;

use App\Models\MenuSection;
use App\Models\Tenant;
use App\Services\QrCodeService;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * ManageMarketing - Pack de Herramientas de Venta PRO
 *
 * Dashboard de Growth Tools exclusivo para suscriptores PRO:
 * - Configurador de WhatsApp con Live Preview
 * - Generador de Cartelería QR (PDF Engine)
 * - Gestor de Secciones de Menú (Sortable)
 *
 * Velvet Rope: FREE users ven un paywall atractivo.
 */
/**
 * @property-read Schema $form
 */
class ManageMarketing extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected string $view = 'filament.app.pages.manage-marketing';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-megaphone';

    public static function getNavigationLabel(): string
    {
        return 'Marketing';
    }

    public function getTitle(): string
    {
        return 'Herramientas de Venta';
    }

    public static function getNavigationSort(): ?int
    {
        return 3;
    }

    // ═══════════════════════════════════════════════════════════════════════
    // ESTADO DEL COMPONENTE
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * ¿Es usuario PRO?
     */
    public bool $isPro = false;

    /**
     * Datos del formulario de WhatsApp
     *
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    /**
     * Preview del mensaje (reactivo)
     */
    public string $messagePreview = '';

    /**
     * QR Code SVG generado
     */
    public string $qrCodeSvg = '';

    /**
     * URL del negocio
     */
    public string $businessUrl = '';

    /**
     * Inicialización con verificación de plan
     */
    public function mount(): void
    {
        $tenant = $this->getTenantRecord();

        if (! $tenant instanceof Tenant) {
            return;
        }

        // Verificar si es PRO (Velvet Rope)
        $this->isPro = (bool) $tenant->is_pro;

        // URL del negocio
        $this->businessUrl = url('/'.$tenant->slug);

        // Solo cargar datos si es PRO
        if ($this->isPro) {
            // Llenar formulario con datos actuales
            $this->form->fill([
                'whatsapp_number' => $tenant->whatsapp_number,
                'whatsapp_message' => $tenant->whatsapp_message ?? 'Hola, me interesa el producto {producto} que vi en su página.',
            ]);

            // Generar QR Code
            $this->generateQrCode();

            // Generar preview inicial
            $this->updateMessagePreview();
        }
    }

    /**
     * Esquema del formulario de WhatsApp
     */
    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Configuración de WhatsApp')
                    ->description('Personaliza el mensaje que reciben tus clientes al contactarte.')
                    ->schema([
                        Forms\Components\TextInput::make('whatsapp_number')
                            ->label('Número de WhatsApp')
                            ->tel()
                            ->placeholder('+504 9999-9999')
                            ->prefix('+504')
                            ->prefixIcon('heroicon-o-device-phone-mobile')
                            ->helperText('Escribe solo los 8 dígitos de tu número. Ejemplo: 9876-5432')
                            ->mask('9999-9999')
                            ->live(onBlur: true)
                            ->required(),

                        Forms\Components\Textarea::make('whatsapp_message')
                            ->label('Mensaje Predeterminado')
                            ->rows(4)
                            ->placeholder('Hola, me interesa el producto {producto} que vi en su página.')
                            ->helperText('Escribe el mensaje que recibirás cuando un cliente te contacte. Usa {producto} para incluir el nombre del producto y {precio} para el precio.')
                            ->live(debounce: 500)
                            ->afterStateUpdated(fn () => $this->updateMessagePreview())
                            ->required()
                            ->columnSpanFull(),
                    ])
                    ->columns(1),
            ])
            ->statePath('data');
    }

    // ═══════════════════════════════════════════════════════════════════════
    // TABLA DE SECCIONES DE MENÚ (SORTABLE)
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Configuración de la tabla de secciones de menú
     *
     * MEJORAS UX:
     * 1. TextInputColumn: Edición en línea sin modales
     * 2. Semáforo Visual: Verde = sana, Rojo = vacía
     * 3. Orphan Guard: Modal dinámico según productos
     */
    public function table(Table $table): Table
    {
        $tenant = $this->getTenantRecord();

        return $table
            ->query(fn (): Builder => MenuSection::query()
                ->where('tenant_id', $tenant?->getKey())
                ->withCount('products')
                ->orderBy('sort_order')
            )
            ->columns([
                // ═══════════════════════════════════════════════════════
                // EDICIÓN EN LÍNEA: Nombre editable estilo Excel
                // ═══════════════════════════════════════════════════════
                Tables\Columns\TextInputColumn::make('name')
                    ->label('Nombre de la Sección')
                    ->searchable()
                    ->sortable()
                    ->rules(['required', 'max:50', 'min:2'])
                    ->placeholder('Escribe el nombre...')
                    ->extraAttributes(['class' => 'font-semibold']),

                // ═══════════════════════════════════════════════════════
                // SEMÁFORO VISUAL: Indicador de salud del inventario
                // ═══════════════════════════════════════════════════════
                // Verde (success) = Sección con productos
                // Rojo (danger) = Sección vacía (requiere acción)
                // ═══════════════════════════════════════════════════════
                Tables\Columns\TextColumn::make('products_count')
                    ->label('Productos')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'success' : 'danger')
                    ->icon(fn (int $state): string => $state > 0
                        ? 'heroicon-o-check-circle'
                        : 'heroicon-o-exclamation-triangle')
                    ->tooltip(fn (int $state): string => $state > 0
                        ? "Esta sección tiene {$state} producto(s)"
                        : '⚠️ Sección vacía - Agrega productos'),

                // Toggle rápido de visibilidad
                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Activa')
                    ->onColor('success')
                    ->offColor('danger')
                    ->tooltip('Activar/Desactivar visibilidad en menú público'),

                // Referencia de orden (discreta)
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Orden')
                    ->badge()
                    ->color('gray')
                    ->alignCenter(),
            ])
            // Motor de arrastre para reordenar
            ->reorderable('sort_order')
            ->defaultSort('sort_order')

            // Acciones de cabecera
            ->headerActions([
                CreateAction::make()
                    ->label('Nueva Sección')
                    ->icon('heroicon-o-plus')
                    ->modalHeading('Crear Sección de Menú')
                    ->modalDescription('Las secciones ayudan a organizar tus productos. Ejemplo: "Entradas", "Platos Fuertes", "Bebidas".')
                    ->form([
                        Forms\Components\TextInput::make('name')
                            ->label('Nombre de la Sección')
                            ->placeholder('Ej: Platos Fuertes')
                            ->required()
                            ->maxLength(50)
                            ->minLength(2)
                            ->autofocus(),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Activa')
                            ->default(true)
                            ->helperText('Las secciones inactivas no se muestran en el menú público.'),
                    ])
                    ->mutateFormDataUsing(function (array $data): array {
                        $tenant = $this->getTenantRecord();
                        $tenantId = $tenant?->getKey();

                        $data['tenant_id'] = $tenantId;
                        $data['sort_order'] = (int) MenuSection::where('tenant_id', $tenantId)->max('sort_order') + 1;

                        return $data;
                    })
                    ->successNotificationTitle('✅ Sección creada exitosamente'),
            ])

            // Acciones por fila
            ->actions([
                EditAction::make()
                    ->iconButton()
                    ->modalHeading('Editar Sección')
                    ->form([
                        Forms\Components\TextInput::make('name')
                            ->label('Nombre de la Sección')
                            ->required()
                            ->maxLength(50)
                            ->minLength(2),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Activa'),
                    ]),

                // ═══════════════════════════════════════════════════════
                // ORPHAN GUARD: Eliminación defensiva inteligente
                // ═══════════════════════════════════════════════════════
                // Modal dinámico que advierte según productos asociados
                // ═══════════════════════════════════════════════════════
                DeleteAction::make()
                    ->iconButton()
                    ->modalHeading('¿Eliminar esta sección?')
                    ->modalDescription(function (MenuSection $record): string {
                        // Obtener conteo actualizado
                        $count = $record->products()->count();

                        if ($count > 0) {
                            // ⚠️ ADVERTENCIA: Sección con productos
                            return "⚠️ **ADVERTENCIA:** Esta sección contiene **{$count} producto(s)** asociados.\n\n".
                                   "Si la eliminas:\n".
                                   "• Los productos **NO se borrarán** de la base de datos\n".
                                   "• Quedarán **'Sin Categoría'**\n".
                                   "• **Dejarán de ser visibles** en tu menú público hasta que los reasignes\n\n".
                                   '¿Estás seguro de continuar?';
                        }

                        // ✅ Sección vacía - eliminación segura
                        return "Esta sección está vacía (0 productos). ¿Estás seguro de eliminarla?\n\nEsta acción no se puede deshacer.";
                    })
                    ->modalSubmitActionLabel('Sí, eliminar sección')
                    ->successNotificationTitle('Sección eliminada'),
            ])

            // Estado vacío (Onboarding)
            ->emptyStateHeading('Sin secciones de menú')
            ->emptyStateDescription('Crea tu primera categoría (ej. Entradas, Bebidas) para comenzar a organizar tu catálogo.')
            ->emptyStateIcon('heroicon-o-folder-open')
            ->emptyStateActions([
                CreateAction::make()
                    ->label('Crear Primera Sección')
                    ->icon('heroicon-o-plus')
                    ->form([
                        Forms\Components\TextInput::make('name')
                            ->label('Nombre de la Sección')
                            ->placeholder('Ej: Platos Fuertes')
                            ->required()
                            ->maxLength(50)
                            ->minLength(2),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Activa')
                            ->default(true),
                    ])
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['tenant_id'] = $this->getTenantRecord()?->getKey();
                        $data['sort_order'] = 0;

                        return $data;
                    }),
            ]);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // MÉTODOS DE WHATSAPP
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Actualiza el preview del mensaje con datos de ejemplo
     */
    public function updateMessagePreview(): void
    {
        $template = $this->data['whatsapp_message'] ?? 'Hola, me interesa...';

        // Reemplazar variables con datos de ejemplo
        $this->messagePreview = str_replace(
            ['{producto}', '{precio}'],
            ['🍔 Baleada Especial', 'L. 45.00'],
            $template
        );
    }

    /**
     * Genera el código QR SVG usando el servicio
     */
    protected function generateQrCode(): void
    {
        try {
            $tenant = $this->getTenantRecord();
            if (! $tenant instanceof Tenant) {
                $this->qrCodeSvg = '';

                return;
            }

            $qrService = app(QrCodeService::class);
            $this->qrCodeSvg = $qrService->getQrCode($tenant, 250);
        } catch (\Exception $e) {
            $this->qrCodeSvg = '';
        }
    }

    /**
     * Guarda la configuración de WhatsApp
     *
     * GUARD: Solo usuarios PRO pueden ejecutar esta acción
     */
    public function saveWhatsAppConfig(): void
    {
        // ═══ GUARD: Verificación de seguridad PRO ═══
        if (! $this->isPro) {
            Notification::make()
                ->danger()
                ->title('Acción no autorizada')
                ->body('Debes tener un Plan PRO para usar esta funcionalidad.')
                ->send();

            return;
        }

        $data = $this->form->getState();

        $tenant = $this->getTenantRecord();

        // Formatear número con código de país
        if (! $tenant instanceof Tenant) {
            Notification::make()
                ->danger()
                ->title('No se pudo guardar la configuración')
                ->send();

            return;
        }

        $cleanNumber = preg_replace('/[^0-9]/', '', $data['whatsapp_number']);

        $tenant->update([
            'whatsapp_number' => $cleanNumber,
            'whatsapp_message' => $data['whatsapp_message'],
        ]);

        Notification::make()
            ->success()
            ->title('Configuración guardada')
            ->body('Tu mensaje de WhatsApp ha sido actualizado.')
            ->send();
    }

    // ═══════════════════════════════════════════════════════════════════════
    // GENERADOR DE PDF
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Descarga el cartel con código QR en PDF
     *
     * GUARD: Solo usuarios PRO pueden ejecutar esta acción
     */
    public function downloadPoster()
    {
        // ═══ GUARD: Verificación de seguridad PRO ═══
        if (! $this->isPro) {
            Notification::make()
                ->danger()
                ->title('Acción no autorizada')
                ->body('Debes tener un Plan PRO para descargar carteles.')
                ->send();

            return;
        }

        $tenant = $this->getTenantRecord();

        if (! $tenant instanceof Tenant) {
            Notification::make()
                ->danger()
                ->title('Error')
                ->body('No se pudo generar el cartel.')
                ->send();

            return;
        }

        // Generar QR usando el servicio
        try {
            $qrService = app(QrCodeService::class);
            $qrCode = $qrService->getQrCodeForPoster($tenant);
        } catch (\Exception $e) {
            $qrCode = '';
        }

        // Generar PDF
        $pdf = Pdf::loadView('pdf.qr-poster', [
            'tenant' => $tenant,
            'qrCode' => $qrCode,
            'url' => $this->businessUrl,
        ]);

        $pdf->setPaper('letter', 'portrait');

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, $tenant->slug.'-cartel-qr.pdf', [
            'Content-Type' => 'application/pdf',
        ]);
    }

    /**
     * Acciones del header
     */
    protected function getHeaderActions(): array
    {
        if (! $this->isPro) {
            return [];
        }

        return [
            Action::make('save_whatsapp')
                ->label('Guardar Configuración')
                ->icon('heroicon-o-check')
                ->color('primary')
                ->action('saveWhatsAppConfig'),

            Action::make('download_poster')
                ->label('Descargar Cartel QR')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action('downloadPoster'),
        ];
    }

    /**
     * Navegación solo para usuarios activos
     */
    public static function shouldRegisterNavigation(): bool
    {
        return true; // Todos ven el menú, pero FREE ven paywall
    }
    protected function getTenantRecord(): ?Tenant
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Tenant ? $tenant : null;
    }
}
