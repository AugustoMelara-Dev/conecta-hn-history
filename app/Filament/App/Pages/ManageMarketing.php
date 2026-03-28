<?php

declare(strict_types=1);

namespace App\Filament\App\Pages;

use App\Application\Catalog\Commands\CreateMenuSectionCommand;
use App\Application\Catalog\Commands\DeleteMenuSectionCommand;
use App\Application\Catalog\Commands\ReorderMenuSectionsCommand;
use App\Application\Catalog\Commands\UpdateMenuSectionCommand;
use App\Application\Catalog\Handlers\CreateMenuSectionHandler;
use App\Application\Catalog\Handlers\DeleteMenuSectionHandler;
use App\Application\Catalog\Handlers\ReorderMenuSectionsHandler;
use App\Application\Catalog\Handlers\UpdateMenuSectionHandler;
use App\Application\Tenant\Commands\UpdateTenantMarketingWhatsAppCommand;
use App\Application\Tenant\Handlers\UpdateTenantMarketingWhatsAppHandler;
use App\Domain\TenantGovernance\Enums\TenantFeature;
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
 * @property-read Schema $form
 */
class ManageMarketing extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected string $view = 'filament.app.pages.manage-marketing';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-megaphone';

    public bool $isPro = false;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public string $messagePreview = '';

    public string $qrCodeSvg = '';

    public string $qrCodeDataUri = '';

    public string $businessUrl = '';

    public static function getNavigationLabel(): string
    {
        return 'Marketing';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Marketing';
    }

    public function getTitle(): string
    {
        return 'Marketing y difusión';
    }

    public function getSubheading(): ?string
    {
        return 'Configura WhatsApp, comparte tu QR y ordena el catálogo público desde un solo flujo.';
    }

    public static function getNavigationSort(): ?int
    {
        return 1;
    }

    public function mount(): void
    {
        $tenant = $this->getTenantRecord();

        if (! $tenant instanceof Tenant) {
            return;
        }

        $this->isPro = $tenant->hasFeature(TenantFeature::MARKETING_SUITE);
        $this->businessUrl = route('tenant.microsite.show', ['tenant' => $tenant->slug]);

        if (! $this->isPro) {
            return;
        }

        $this->form->fill([
            'whatsapp_number' => $tenant->whatsapp_number,
            'whatsapp_message' => $tenant->whatsapp_message ?? 'Hola, me interesa el producto {producto} que vi en su página.',
        ]);

        $this->generateQrCode();
        $this->updateMessagePreview();
    }

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
                            ->helperText('Escribe solo los 8 dígitos de tu número. Ejemplo: 9876-5432.')
                            ->mask('9999-9999')
                            ->live(onBlur: true)
                            ->required(),

                        Forms\Components\Textarea::make('whatsapp_message')
                            ->label('Mensaje predeterminado')
                            ->rows(4)
                            ->placeholder('Hola, me interesa el producto {producto} que vi en su página.')
                            ->helperText('Usa {producto} para incluir el nombre del producto y {precio} para el precio.')
                            ->live(debounce: 500)
                            ->afterStateUpdated(fn () => $this->updateMessagePreview())
                            ->required()
                            ->columnSpanFull(),
                    ])
                    ->columns(1),
            ])
            ->statePath('data');
    }

    public function table(Table $table): Table
    {
        $tenant = $this->getTenantRecord();

        return $table
            ->query(fn (): Builder => MenuSection::query()
                ->where('tenant_id', $tenant?->getKey())
                ->withCount('products')
                ->orderBy('sort_order'))
            ->columns([
                Tables\Columns\TextInputColumn::make('name')
                    ->label('Nombre de la sección')
                    ->searchable()
                    ->sortable()
                    ->rules(['required', 'max:50', 'min:2'])
                    ->placeholder('Escribe el nombre...')
                    ->updateStateUsing(function (MenuSection $record, mixed $state): string {
                        $updatedRecord = $this->updateMenuSection($record, [
                            'name' => (string) $state,
                        ]);

                        return (string) $updatedRecord->name;
                    })
                    ->extraAttributes(['class' => 'font-semibold']),

                Tables\Columns\TextColumn::make('products_count')
                    ->label('Productos')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'success' : 'danger')
                    ->icon(fn (int $state): string => $state > 0
                        ? 'heroicon-o-check-circle'
                        : 'heroicon-o-exclamation-triangle')
                    ->tooltip(fn (int $state): string => $state > 0
                        ? "Esta sección tiene {$state} producto(s)."
                        : 'Sección vacía: agrega productos para mostrarla en tu vitrina.'),

                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Activa')
                    ->onColor('success')
                    ->offColor('danger')
                    ->updateStateUsing(function (MenuSection $record, mixed $state): bool {
                        $updatedRecord = $this->updateMenuSection($record, [
                            'is_active' => (bool) $state,
                        ]);

                        return (bool) $updatedRecord->is_active;
                    })
                    ->tooltip('Activa o desactiva la visibilidad en el menú público.'),

                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Orden')
                    ->badge()
                    ->color('gray')
                    ->alignCenter(),
            ])
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->headerActions([
                CreateAction::make()
                    ->label('Nueva sección')
                    ->icon('heroicon-o-plus')
                    ->modalHeading('Crear sección del catálogo')
                    ->modalDescription('Las secciones ayudan a organizar tu oferta. Por ejemplo: "Entradas", "Servicios" o "Promociones".')
                    ->form([
                        Forms\Components\TextInput::make('name')
                            ->label('Nombre de la sección')
                            ->placeholder('Ej: Promociones')
                            ->required()
                            ->maxLength(50)
                            ->minLength(2)
                            ->autofocus(),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Activa')
                            ->default(true)
                            ->helperText('Las secciones inactivas no se muestran en el catálogo público.'),
                    ])
                    ->using(fn (array $data): MenuSection => $this->createMenuSection($data))
                    ->successNotificationTitle('Sección creada correctamente'),
            ])
            ->actions([
                EditAction::make()
                    ->iconButton()
                    ->modalHeading('Editar sección')
                    ->form([
                        Forms\Components\TextInput::make('name')
                            ->label('Nombre de la sección')
                            ->required()
                            ->maxLength(50)
                            ->minLength(2),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Activa'),
                    ])
                    ->using(fn (MenuSection $record, array $data): MenuSection => $this->updateMenuSection($record, $data)),
                DeleteAction::make()
                    ->iconButton()
                    ->modalHeading('¿Eliminar esta sección?')
                    ->modalDescription(function (MenuSection $record): string {
                        $count = $record->products()->count();

                        if ($count > 0) {
                            return "**Atención:** esta sección contiene **{$count} producto(s)** asociados.\n\n"
                                . "Si la eliminas:\n"
                                . "- Los productos **no se borrarán** de la base de datos.\n"
                                . "- Quedarán en **Sin categoría**.\n"
                                . "- **Dejarán de ser visibles** en el catálogo público hasta que los reasignes.\n\n"
                                . '¿Seguro que quieres continuar?';
                        }

                        return "Esta sección está vacía (0 productos). ¿Seguro que quieres eliminarla?\n\nEsta acción no se puede deshacer.";
                    })
                    ->action(function (MenuSection $record): void {
                        $this->deleteMenuSection($record);
                    })
                    ->modalSubmitActionLabel('Sí, eliminar sección')
                    ->successNotificationTitle('Sección eliminada'),
            ])
            ->emptyState(fn () => view('filament.app.components.table-empty-state', [
                'heading' => 'Aún no has creado secciones del catálogo',
                'description' => 'Usa "Nueva sección" para ordenar tu oferta en bloques como bebidas, servicios o promociones.',
            ]));
    }

    /**
     * @param  array<int|string>  $order
     */
    public function reorderTable(array $order, int|string|null $draggedRecordKey = null): void
    {
        if (! $this->getTable()->isReorderable()) {
            return;
        }

        app(ReorderMenuSectionsHandler::class)->handle(
            new ReorderMenuSectionsCommand(
                tenantId: $this->resolveTenantIdOrFail(),
                orderedMenuSectionIds: $this->normalizeMenuSectionIds($order),
            )
        );
    }

    public function updateMessagePreview(): void
    {
        $template = $this->data['whatsapp_message'] ?? 'Hola, me interesa...';

        $this->messagePreview = str_replace(
            ['{producto}', '{precio}'],
            ['Baleada especial', 'L. 45.00'],
            $template
        );
    }

    protected function generateQrCode(): void
    {
        try {
            $tenant = $this->getTenantRecord();

            if (! $tenant instanceof Tenant) {
                $this->qrCodeSvg = '';
                $this->qrCodeDataUri = '';

                return;
            }

            $qrService = app(QrCodeService::class);
            $this->qrCodeSvg = $qrService->getQrCode($tenant, 250);
            $this->qrCodeDataUri = $qrService->getQrCodeAsDataUri($tenant, 250);
        } catch (\Exception) {
            $this->qrCodeSvg = '';
            $this->qrCodeDataUri = '';
        }
    }

    public function saveWhatsAppConfig(): void
    {
        if (! $this->isPro) {
            Notification::make()
                ->danger()
                ->title('Acción no autorizada')
                ->body('Debes activar PRO para usar esta funcionalidad.')
                ->send();

            return;
        }

        $data = $this->form->getState();
        $tenant = $this->getTenantRecord();

        if (! $tenant instanceof Tenant) {
            Notification::make()
                ->danger()
                ->title('No se pudo guardar la configuración')
                ->send();

            return;
        }

        app(UpdateTenantMarketingWhatsAppHandler::class)->handle(
            new UpdateTenantMarketingWhatsAppCommand(
                tenantId: (string) $tenant->getKey(),
                whatsappNumber: (string) ($data['whatsapp_number'] ?? ''),
                whatsappMessage: (string) ($data['whatsapp_message'] ?? ''),
            )
        );

        Notification::make()
            ->success()
            ->title('Configuración guardada')
            ->body('Tu mensaje de WhatsApp fue actualizado correctamente.')
            ->send();
    }

    public function downloadPoster()
    {
        if (! $this->isPro) {
            Notification::make()
                ->danger()
                ->title('Acción no autorizada')
                ->body('Debes activar PRO para descargar carteles.')
                ->send();

            return;
        }

        $tenant = $this->getTenantRecord();

        if (! $tenant instanceof Tenant) {
            Notification::make()
                ->danger()
                ->title('No se pudo generar el cartel')
                ->body('Vuelve a intentarlo en unos segundos.')
                ->send();

            return;
        }

        try {
            $qrService = app(QrCodeService::class);
            $qrCode = $qrService->getQrCodeForPoster($tenant);
        } catch (\Exception) {
            $qrCode = '';
        }

        $pdf = Pdf::loadView('pdf.qr-poster', [
            'tenant' => $tenant,
            'qrCode' => $qrCode,
            'url' => $this->businessUrl,
        ]);

        $pdf->setPaper('letter', 'portrait');

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, $tenant->slug . '-cartel-qr.pdf', [
            'Content-Type' => 'application/pdf',
        ]);
    }

    protected function getHeaderActions(): array
    {
        $tenant = $this->getTenantRecord();

        if (! $tenant instanceof Tenant) {
            return [];
        }

        if (! $this->isPro) {
            return [
                Action::make('upgrade')
                    ->label('Activar plan PRO')
                    ->icon('heroicon-o-arrow-up-right')
                    ->color('success')
                    ->url(route('filament.app.pages.manage-billing', ['tenant' => $tenant->slug], false)),
            ];
        }

        return [
            Action::make('save_whatsapp')
                ->label('Guardar WhatsApp')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->keyBindings(['mod+s'])
                ->action('saveWhatsAppConfig'),
            Action::make('public_page')
                ->label('Ver página pública')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('gray')
                ->url($this->businessUrl, shouldOpenInNewTab: true),
            Action::make('billing')
                ->label('Ver suscripción')
                ->icon('heroicon-o-credit-card')
                ->color('gray')
                ->url(route('filament.app.pages.manage-billing', ['tenant' => $tenant->slug], false)),
        ];
    }

    public static function shouldRegisterNavigation(): bool
    {
        return true;
    }

    protected function getTenantRecord(): ?Tenant
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Tenant ? $tenant : null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function createMenuSection(array $data): MenuSection
    {
        return app(CreateMenuSectionHandler::class)->handle(
            new CreateMenuSectionCommand(
                tenantId: $this->resolveTenantIdOrFail(),
                name: (string) ($data['name'] ?? ''),
                isActive: (bool) ($data['is_active'] ?? true),
            )
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function updateMenuSection(MenuSection $record, array $data): MenuSection
    {
        return app(UpdateMenuSectionHandler::class)->handle(
            new UpdateMenuSectionCommand(
                tenantId: $this->resolveTenantIdOrFail(),
                menuSectionId: (int) $record->getKey(),
                name: array_key_exists('name', $data) ? (string) $data['name'] : null,
                isActive: array_key_exists('is_active', $data) ? (bool) $data['is_active'] : null,
            )
        );
    }

    private function deleteMenuSection(MenuSection $record): void
    {
        app(DeleteMenuSectionHandler::class)->handle(
            new DeleteMenuSectionCommand(
                tenantId: $this->resolveTenantIdOrFail(),
                menuSectionId: (int) $record->getKey(),
            )
        );
    }

    private function resolveTenantIdOrFail(): string
    {
        $tenant = $this->getTenantRecord();

        if (! $tenant instanceof Tenant) {
            throw new \RuntimeException('Unable to resolve tenant context for menu section mutation.');
        }

        return (string) $tenant->getKey();
    }

    /**
     * @param  array<int|string>  $order
     * @return array<int, int>
     */
    private function normalizeMenuSectionIds(array $order): array
    {
        $normalized = [];

        foreach ($order as $menuSectionId) {
            $menuSectionId = (int) $menuSectionId;

            if ($menuSectionId <= 0 || in_array($menuSectionId, $normalized, true)) {
                continue;
            }

            $normalized[] = $menuSectionId;
        }

        return $normalized;
    }
}
