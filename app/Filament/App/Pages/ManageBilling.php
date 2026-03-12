<?php

declare(strict_types=1);

namespace App\Filament\App\Pages;

use App\Models\Payment;
use App\Settings\GeneralSettings;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Schema;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

/**
 * ManageBilling - Centro de Suscripción y Pagos
 *
 * Máquina de Estados Financieros Polimórfica:
 * - FREE: Muestra wizard para enviar pago con instrucciones bancarias
 * - PENDING: Muestra pantalla de espera mientras se valida
 * - PRO: Muestra dashboard con historial y recibos descargables
 *
 * Crítico para la monetización del sistema.
 * Integrado con GeneralSettings para precios e instrucciones dinámicas.
 */
class ManageBilling extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected string $view = 'filament.app.pages.manage-billing';

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-credit-card';

    public static function getNavigationLabel(): string
    {
        return 'Suscripción';
    }

    public function getTitle(): string
    {
        return 'Suscripción y Pagos';
    }

    public static function getNavigationSort(): ?int
    {
        return 4;
    }

    // ═══════════════════════════════════════════════════════════════════════
    // ESTADOS FINANCIEROS
    // ═══════════════════════════════════════════════════════════════════════

    /** Estado 1: Hay pago pendiente de revisión */
    public bool $isPending = false;

    /** Estado 2: Es usuario PRO activo */
    public bool $isPro = false;

    /** Estado 3: Es usuario FREE (default) */
    public bool $isFree = true;

    /** Pago pendiente actual (si existe) */
    public ?Payment $pendingPayment = null;

    /** Días restantes de suscripción PRO */
    public int $daysRemaining = 0;

    /** Fecha de expiración formateada */
    public ?string $expiresAt = null;

    /** Datos del formulario de pago */
    public ?array $data = [];

    // ═══════════════════════════════════════════════════════════════════════
    // CONFIGURACIÓN DINÁMICA DESDE GeneralSettings
    // ═══════════════════════════════════════════════════════════════════════

    /** Monto de la suscripción mensual (de GeneralSettings) */
    public float $subscriptionAmount = 99.00;

    /** Precio formateado para mostrar en vistas */
    public string $formattedPrice = 'L. 99.00';

    /** Instrucciones bancarias HTML (de GeneralSettings) */
    public string $bankInstructions = '';

    /** Número WhatsApp de soporte */
    public string $supportWhatsApp = '';

    /**
     * Inicialización: Detectar estado financiero + cargar settings
     */
    public function mount(): void
    {
        $tenant = Filament::getTenant();

        if (! $tenant) {
            return;
        }

        // ═══════════════════════════════════════════════════════════════════
        // CARGAR CONFIGURACIÓN DESDE GeneralSettings
        // ═══════════════════════════════════════════════════════════════════
        try {
            $settings = app(GeneralSettings::class);
            $this->subscriptionAmount = $settings->pro_plan_price ?? 99.00;
            $this->formattedPrice = 'L. '.number_format($this->subscriptionAmount, 2);
            $this->bankInstructions = $settings->bank_transfer_instructions ?? '';
            $this->supportWhatsApp = $settings->support_whatsapp_number ?? '';
        } catch (\Exception $e) {
            // Fallback a valores por defecto si settings no existe
            $this->subscriptionAmount = 99.00;
            $this->formattedPrice = 'L. 99.00';
            $this->bankInstructions = '';
            $this->supportWhatsApp = '';
        }

        // ═══════════════════════════════════════════════════════════════════
        // DETERMINAR ESTADO FINANCIERO
        // ═══════════════════════════════════════════════════════════════════

        // Verificar si hay pago pendiente
        $this->pendingPayment = Payment::where('tenant_id', $tenant->id)
            ->where('status', 'pending')
            ->latest()
            ->first();

        if ($this->pendingPayment) {
            // Estado 1: PENDING - Bloquear interfaz, mostrar banner de espera
            $this->isPending = true;
            $this->isPro = false;
            $this->isFree = false;
        } elseif ($tenant->is_pro) {
            // Estado 2: PRO - Mostrar dashboard con historial
            $this->isPro = true;
            $this->isPending = false;
            $this->isFree = false;

            // Calcular días restantes para semáforo de vencimiento
            if ($tenant->plan_expires_at) {
                $this->daysRemaining = max(0, Carbon::now()->diffInDays($tenant->plan_expires_at, false));
                $this->expiresAt = $tenant->plan_expires_at->format('d/m/Y');
            }
        } else {
            // Estado 3: FREE - Mostrar wizard de pago
            $this->isFree = true;
            $this->isPending = false;
            $this->isPro = false;
        }

        // Inicializar formulario
        $this->form->fill([
            'bank_reference' => '',
            'proof_image_path' => null,
        ]);
    }

    /**
     * Formulario del Wizard de Pago (para usuarios FREE)
     */
    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\Wizard::make([
                    // ═══════════════════════════════════════════════════════
                    // PASO 1: INSTRUCCIONES BANCARIAS
                    // ═══════════════════════════════════════════════════════
                    Forms\Components\Wizard\Step::make('Información Bancaria')
                        ->icon('heroicon-o-building-library')
                        ->description('Datos para la transferencia')
                        ->schema([
                            Forms\Components\Placeholder::make('bank_info')
                                ->label('')
                                ->content(fn () => new HtmlString($this->getBankInstructionsHtml())),
                        ]),

                    // ═══════════════════════════════════════════════════════
                    // PASO 2: SUBIR COMPROBANTE
                    // ═══════════════════════════════════════════════════════
                    Forms\Components\Wizard\Step::make('Confirmar Pago')
                        ->icon('heroicon-o-document-check')
                        ->description('Sube tu comprobante')
                        ->schema([
                            Forms\Components\TextInput::make('bank_reference')
                                ->label('Número de Referencia / Autorización')
                                ->placeholder('Ej: 12345678')
                                ->required()
                                ->maxLength(100)
                                ->helperText('Lo encontrarás en el comprobante de tu banco.')
                                ->prefixIcon('heroicon-o-hashtag'),

                            Forms\Components\FileUpload::make('proof_image_path')
                                ->label('Subir Comprobante (Captura de Pantalla)')
                                ->image()
                                ->required()
                                ->directory('payments/proofs')
                                ->maxSize(5120) // 5MB
                                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                                ->helperText('Sube la captura de pantalla de tu transferencia (máx. 5MB)')
                                ->imagePreviewHeight('200')
                                ->columnSpanFull(),
                        ]),
                ])
                    ->submitAction(new HtmlString('
                    <button 
                        type="submit" 
                        wire:click="submitPayment"
                        class="fi-btn fi-btn-size-md relative grid-flow-col items-center justify-center font-semibold outline-none transition duration-75 focus:ring-2 rounded-lg fi-btn-color-primary gap-1.5 px-3 py-2 text-sm inline-grid shadow-sm bg-primary-600 text-white hover:bg-primary-500 focus:ring-primary-500/50 dark:bg-primary-500 dark:hover:bg-primary-400"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <span>Enviar Pago a Revisión</span>
                    </button>
                ')),
            ])
            ->statePath('data');
    }

    /**
     * Genera el HTML de instrucciones bancarias con precio dinámico
     */
    protected function getBankInstructionsHtml(): string
    {
        $price = $this->formattedPrice;

        // Si tenemos instrucciones personalizadas, usarlas
        if (! empty($this->bankInstructions)) {
            return str_replace('{precio}', $price, $this->bankInstructions);
        }

        // HTML por defecto con precio dinámico
        return '
            <div class="bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-gray-800 dark:to-gray-900 rounded-2xl p-6 border border-blue-100 dark:border-gray-700">
                <div class="text-center mb-6">
                    <div class="inline-flex items-center justify-center w-16 h-16 bg-blue-100 dark:bg-blue-900/30 rounded-full mb-4">
                        <svg class="w-8 h-8 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white">Plan PRO Empresario</h3>
                    <p class="text-gray-500 dark:text-gray-400">Suscripción mensual</p>
                </div>
                
                <div class="bg-white dark:bg-gray-800 rounded-xl p-5 space-y-4 shadow-sm">
                    <div class="flex justify-between items-center pb-3 border-b border-gray-100 dark:border-gray-700">
                        <span class="text-gray-600 dark:text-gray-400">Banco</span>
                        <span class="font-semibold text-gray-900 dark:text-white">BAC Credomatic</span>
                    </div>
                    <div class="flex justify-between items-center pb-3 border-b border-gray-100 dark:border-gray-700">
                        <span class="text-gray-600 dark:text-gray-400">Beneficiario</span>
                        <span class="font-semibold text-gray-900 dark:text-white">Conecta HN S.A.</span>
                    </div>
                    <div class="flex justify-between items-center pb-3 border-b border-gray-100 dark:border-gray-700">
                        <span class="text-gray-600 dark:text-gray-400">Número de Cuenta</span>
                        <span class="font-mono font-semibold text-gray-900 dark:text-white">123-456-7890</span>
                    </div>
                    <div class="flex justify-between items-center pt-2">
                        <span class="text-gray-600 dark:text-gray-400">Monto a Pagar</span>
                        <span class="text-3xl font-bold text-green-600 dark:text-green-400">'.$price.'</span>
                    </div>
                </div>
                
                <div class="mt-6 p-4 bg-amber-50 dark:bg-amber-900/20 rounded-xl">
                    <div class="flex items-start gap-3">
                        <svg class="w-6 h-6 text-amber-600 dark:text-amber-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        </svg>
                        <div class="text-sm text-amber-800 dark:text-amber-300">
                            <strong>Importante:</strong> Realiza la transferencia desde tu banca en línea y guarda la captura de pantalla del comprobante. La necesitarás en el siguiente paso.
                        </div>
                    </div>
                </div>
            </div>
        ';
    }

    /**
     * Procesar envío de pago
     */
    public function submitPayment(): void
    {
        $data = $this->form->getState();
        $tenant = Filament::getTenant();

        if (! $tenant) {
            Notification::make()
                ->danger()
                ->title('Error')
                ->body('No se pudo procesar el pago.')
                ->send();

            return;
        }

        // Verificar que no haya otro pago pendiente
        $existingPending = Payment::where('tenant_id', $tenant->id)
            ->where('status', 'pending')
            ->exists();

        if ($existingPending) {
            Notification::make()
                ->warning()
                ->title('Ya tienes un pago pendiente')
                ->body('Espera a que se procese tu pago anterior.')
                ->send();

            return;
        }

        // Crear registro de pago
        Payment::create([
            'tenant_id' => $tenant->id,
            'amount' => $this->subscriptionAmount,
            'bank_reference' => $data['bank_reference'],
            'proof_image_path' => $data['proof_image_path'],
            'status' => 'pending',
        ]);

        Notification::make()
            ->success()
            ->title('¡Pago enviado a revisión!')
            ->body('Nuestro equipo validará tu transferencia en breve.')
            ->send();

        // Recargar página para mostrar estado PENDING
        $this->redirect(self::getUrl());
    }

    /**
     * Tabla de historial de pagos (para usuarios PRO)
     */
    public function table(Table $table): Table
    {
        $tenant = Filament::getTenant();

        return $table
            ->query(
                Payment::query()
                    ->where('tenant_id', $tenant?->id ?? 0)
                    ->orderBy('created_at', 'desc')
            )
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('bank_reference')
                    ->label('Referencia')
                    ->searchable()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Monto')
                    ->money('HNL')
                    ->sortable(),

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
                        default => $state,
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('download_receipt')
                    ->label('Recibo')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->visible(fn (Payment $record) => $record->status === 'approved')
                    ->action(function (Payment $record) {
                        return $this->downloadReceipt($record);
                    }),
            ])
            ->emptyStateHeading('Sin pagos registrados')
            ->emptyStateDescription('Tu historial de pagos aparecerá aquí.')
            ->emptyStateIcon('heroicon-o-banknotes');
    }

    /**
     * Generar y descargar recibo PDF
     */
    public function downloadReceipt(Payment $payment)
    {
        $tenant = Filament::getTenant();

        $pdf = Pdf::loadView('pdf.receipt', [
            'payment' => $payment,
            'tenant' => $tenant,
            'issueDate' => $payment->reviewed_at ?? $payment->created_at,
        ]);

        $pdf->setPaper('letter', 'portrait');

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, 'recibo-'.$payment->id.'.pdf', [
            'Content-Type' => 'application/pdf',
        ]);
    }

    /**
     * Acciones del header
     */
    protected function getHeaderActions(): array
    {
        if ($this->isPro && $this->daysRemaining <= 7) {
            return [
                Action::make('renew')
                    ->label('Renovar Suscripción')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->url(self::getUrl()),
            ];
        }

        return [];
    }
}
