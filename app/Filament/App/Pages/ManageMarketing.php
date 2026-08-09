<?php

declare(strict_types=1);

namespace App\Filament\App\Pages;

use App\Domain\TenantGovernance\Services\SubscriptionPricingService;
use App\Models\MenuSection;
use App\Services\QrCodeService;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ManageMarketing extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected string $view = 'filament.app.pages.manage-marketing';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-megaphone';

    public bool $isPro = false;

    public string $formattedProPrice = '';

    public ?array $data = [];

    public string $messagePreview = '';

    public string $qrCodeSvg = '';

    public string $businessUrl = '';

    public static function getNavigationLabel(): string
    {
        return 'Marketing';
    }

    public function getTitle(): string
    {
        return 'Marketing';
    }

    public static function getNavigationSort(): ?int
    {
        return 3;
    }

    public function mount(): void
    {
        $this->formattedProPrice = 'L. '.number_format(
            SubscriptionPricingService::canonicalProPriceInMajorUnits(),
            2
        );
        $tenant = Filament::getTenant();

        if (! $tenant) {
            return;
        }

        $this->isPro = (bool) $tenant->is_pro;
        $this->businessUrl = url('/'.$tenant->slug);

        if (! $this->isPro) {
            return;
        }

        $this->form->fill([
            'whatsapp_number' => $tenant->whatsapp_number,
            'whatsapp_message' => $tenant->whatsapp_message ?? 'Hola, me interesa el producto {producto} que vi en su pagina.',
        ]);

        $this->generateQrCode();
        $this->updateMessagePreview();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Section::make('WhatsApp del negocio')
                    ->description('Define el numero y el mensaje que se abre cuando un cliente quiere consultar un producto.')
                    ->schema([
                        Forms\Components\TextInput::make('whatsapp_number')
                            ->label('Numero de WhatsApp')
                            ->tel()
                            ->placeholder('9876-5432')
                            ->prefix('+504')
                            ->prefixIcon('heroicon-o-device-phone-mobile')
                            ->helperText('Escribe los 8 digitos del numero. Ejemplo: 9876-5432.')
                            ->mask('9999-9999')
                            ->live(onBlur: true)
                            ->required(),

                        Forms\Components\Textarea::make('whatsapp_message')
                            ->label('Mensaje predeterminado')
                            ->rows(4)
                            ->placeholder('Hola, me interesa el producto {producto} que vi en su pagina.')
                            ->helperText('Puedes usar {producto} y {precio} para incluir datos del producto automaticamente.')
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
        return $table
            ->query(fn (): Builder => MenuSection::query()
                ->where('tenant_id', Filament::getTenant()?->id)
                ->withCount('products')
                ->orderBy('sort_order')
            )
            ->columns([
                Tables\Columns\TextInputColumn::make('name')
                    ->label('Seccion')
                    ->searchable()
                    ->sortable()
                    ->rules(['required', 'max:50', 'min:2'])
                    ->placeholder('Nombre de la seccion')
                    ->extraAttributes(['class' => 'font-semibold']),

                Tables\Columns\TextColumn::make('products_count')
                    ->label('Productos')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'success' : 'warning')
                    ->icon(fn (int $state): string => $state > 0
                        ? 'heroicon-o-check-circle'
                        : 'heroicon-o-exclamation-triangle')
                    ->tooltip(fn (int $state): string => $state > 0
                        ? "Esta seccion tiene {$state} producto(s)."
                        : 'Esta seccion esta vacia. Agrega productos o mantenla oculta.'),

                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Visible')
                    ->onColor('success')
                    ->offColor('gray')
                    ->tooltip('Controla si la seccion aparece en el menu publico.'),

                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Orden')
                    ->badge()
                    ->color('gray')
                    ->alignCenter(),
            ])
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Nueva seccion')
                    ->icon('heroicon-o-plus')
                    ->modalHeading('Crear seccion del menu')
                    ->modalDescription('Usa secciones para organizar tus productos, por ejemplo: Entradas, Platos fuertes o Bebidas.')
                    ->form($this->sectionForm())
                    ->mutateFormDataUsing(function (array $data): array {
                        $tenantId = Filament::getTenant()?->id;

                        $data['tenant_id'] = $tenantId;
                        $data['sort_order'] = (int) MenuSection::where('tenant_id', $tenantId)->max('sort_order') + 1;

                        return $data;
                    })
                    ->successNotificationTitle('Seccion creada'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->iconButton()
                    ->modalHeading('Editar seccion')
                    ->form($this->sectionForm(includeHelperText: false)),

                Tables\Actions\DeleteAction::make()
                    ->iconButton()
                    ->modalHeading('Eliminar seccion')
                    ->modalDescription(function (MenuSection $record): string {
                        $count = $record->products()->count();

                        if ($count > 0) {
                            return "Esta seccion contiene {$count} producto(s). Si la eliminas, los productos no se borran, pero quedaran sin seccion y pueden dejar de verse en el menu publico hasta que los reasignes.";
                        }

                        return 'Esta seccion esta vacia. Esta accion no se puede deshacer.';
                    })
                    ->modalSubmitActionLabel('Eliminar seccion')
                    ->successNotificationTitle('Seccion eliminada'),
            ])
            ->emptyStateHeading('Sin secciones de menu')
            ->emptyStateDescription('Crea una seccion para ordenar tu catalogo antes de publicar mas productos.')
            ->emptyStateIcon('heroicon-o-folder-open')
            ->emptyStateActions([
                Tables\Actions\CreateAction::make()
                    ->label('Crear primera seccion')
                    ->icon('heroicon-o-plus')
                    ->form($this->sectionForm())
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['tenant_id'] = Filament::getTenant()?->id;
                        $data['sort_order'] = 0;

                        return $data;
                    }),
            ]);
    }

    public function updateMessagePreview(): void
    {
        $template = $this->data['whatsapp_message'] ?? 'Hola, me interesa el producto {producto}.';

        $this->messagePreview = str_replace(
            ['{producto}', '{precio}'],
            ['Baleada especial', 'L. 45.00'],
            $template
        );
    }

    public function saveWhatsAppConfig(): void
    {
        if (! $this->isPro) {
            Notification::make()
                ->danger()
                ->title('Accion no autorizada')
                ->body('Necesitas un plan PRO para usar esta herramienta.')
                ->send();

            return;
        }

        $data = $this->form->getState();
        $tenant = Filament::getTenant();

        if (! $tenant) {
            Notification::make()
                ->danger()
                ->title('No se pudo guardar')
                ->body('Actualiza la pagina e intenta nuevamente.')
                ->send();

            return;
        }

        $tenant->update([
            'whatsapp_number' => preg_replace('/[^0-9]/', '', $data['whatsapp_number']),
            'whatsapp_message' => $data['whatsapp_message'],
        ]);

        Notification::make()
            ->success()
            ->title('WhatsApp actualizado')
            ->body('El mensaje del negocio quedo listo para tus clientes.')
            ->send();
    }

    public function downloadPoster()
    {
        if (! $this->isPro) {
            Notification::make()
                ->danger()
                ->title('Accion no autorizada')
                ->body('Necesitas un plan PRO para descargar carteles.')
                ->send();

            return;
        }

        $tenant = Filament::getTenant();

        if (! $tenant) {
            Notification::make()
                ->danger()
                ->title('No se pudo generar el cartel')
                ->body('Actualiza la pagina e intenta nuevamente.')
                ->send();

            return;
        }

        try {
            $qrCode = app(QrCodeService::class)->getQrCodeForPoster($tenant);
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
        }, $tenant->slug.'-cartel-qr.pdf', [
            'Content-Type' => 'application/pdf',
        ]);
    }

    protected function getHeaderActions(): array
    {
        if (! $this->isPro) {
            return [];
        }

        return [
            Action::make('save_whatsapp')
                ->label('Guardar WhatsApp')
                ->icon('heroicon-o-check')
                ->color('primary')
                ->action('saveWhatsAppConfig'),

            Action::make('download_poster')
                ->label('Descargar cartel QR')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action('downloadPoster'),
        ];
    }

    public static function shouldRegisterNavigation(): bool
    {
        return true;
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    private function sectionForm(bool $includeHelperText = true): array
    {
        return [
            Forms\Components\TextInput::make('name')
                ->label('Nombre de la seccion')
                ->placeholder('Ej: Platos fuertes')
                ->required()
                ->maxLength(50)
                ->minLength(2)
                ->autofocus(),

            Forms\Components\Toggle::make('is_active')
                ->label('Visible en el menu publico')
                ->default(true)
                ->helperText($includeHelperText ? 'Puedes ocultarla mientras terminas de organizar productos.' : null),
        ];
    }

    protected function generateQrCode(): void
    {
        try {
            $tenant = Filament::getTenant();

            if (! $tenant) {
                $this->qrCodeSvg = '';

                return;
            }

            $this->qrCodeSvg = app(QrCodeService::class)->getQrCode($tenant, 250);
        } catch (\Exception) {
            $this->qrCodeSvg = '';
        }
    }
}
