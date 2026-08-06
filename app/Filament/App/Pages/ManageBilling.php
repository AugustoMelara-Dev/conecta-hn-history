<?php

declare(strict_types=1);

namespace App\Filament\App\Pages;

use App\Application\Access\Contracts\TenantPermissionGuardInterface;
use App\Application\Billing\Results\BillingCheckoutResult;
use App\Application\Billing\Services\BillingCheckoutService;
use App\Domain\Access\Enums\Permission;
use App\Domain\Billing\Gateways\SubscriptionCheckoutGatewayInterface;
use App\Domain\Billing\Services\AiCreditUsageService;
use App\Domain\Billing\Services\PlanEntitlementService;
use App\Domain\TenantGovernance\Enums\Capability;
use App\Models\SubscriptionPayment;
use App\Models\Tenant;
use App\Models\User;
use App\Settings\GeneralSettings;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Schemas\Components\Section;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Schema;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

use Illuminate\Support\HtmlString;

/**
 * @property-read Form $form
 */
class ManageBilling extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static string $view = 'filament.app.pages.manage-billing';

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    public bool $isPending = false;

    public bool $isPro = false;

    public bool $isFree = true;

    public ?SubscriptionPayment $pendingPayment = null;

    public int $daysRemaining = 0;

    public ?string $expiresAt = null;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public bool $paymentsTableAvailable = true;

    public float $subscriptionAmount = 0.0;

    public string $formattedPrice = '';

    public string $bankInstructions = '';

    public string $supportWhatsApp = '';

    /** @var array<string, mixed> */
    public array $planPresentation = [];

    /** @var array<string, mixed> */
    public array $planEntitlements = [];

    /** @var array<string, mixed> */
    public array $aiUsage = [
        'allowance' => 0.0,
        'daily_limit' => 0.0,
        'spent' => 0.0,
        'reserved' => 0.0,
        'available' => 0.0,
        'percentage' => 0,
        'threshold' => 0,
        'status' => 'healthy',
        'resets_at' => null,
        'reset_label' => null,
    ];

    public static function canAccess(): bool
    {
        $user = auth()->user();
        $tenant = Filament::getTenant();

        if (! $user instanceof \App\Models\User || ! $tenant instanceof Tenant) {
            return false;
        }

        return app(\App\Application\Access\Contracts\TenantPermissionGuardInterface::class)->allows(
            $user,
            $tenant,
            \App\Domain\TenantGovernance\Enums\Capability::BILLING_PORTAL,
            \App\Domain\Access\Enums\Permission::BILLING_VIEW
        );
    }

    public static function getNavigationLabel(): string
    {
        return 'Suscripcion';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Cuenta';
    }

    public function getTitle(): string
    {
        return 'Suscripcion y pagos';
    }

    public function getSubheading(): ?string
    {
        return $this->isPro
            ? 'Revisa la vigencia del plan, pagos aprobados y renovacion desde un solo flujo.'
            : 'Activa tu siguiente nivel comercial, reporta la transferencia y sigue la revision desde el mismo panel.';
    }

    public static function getNavigationSort(): ?int
    {
        return 4;
    }

    public function mount(): void
    {
        $tenant = $this->getTenantRecord();

        if (! $tenant instanceof Tenant) {
            return;
        }

        $this->loadSettings();
        $this->planPresentation = $this->resolvePlanPresentation($tenant);
        $this->planEntitlements = app(PlanEntitlementService::class)->forTenant($tenant)->toArray();
        $this->paymentsTableAvailable = Schema::hasTable((new SubscriptionPayment)->getTable());

        if (Schema::hasTable('ai_credit_reservations')) {
            $this->aiUsage = app(AiCreditUsageService::class)->forTenant($tenant);
            $this->aiUsage['reset_label'] = Carbon::parse((string) $this->aiUsage['resets_at'])
                ->timezone(config('app.timezone'))
                ->format('d/m/Y');
        }

        $this->pendingPayment = $this->paymentsTableAvailable
            ? SubscriptionPayment::query()
                ->where('tenant_id', $tenant->getKey())
                ->where('status', SubscriptionPayment::STATUS_PENDING)
                ->latest()
                ->first()
            : null;

        $this->isPending = $this->pendingPayment instanceof SubscriptionPayment;
        $this->isPro = in_array((string) ($this->planEntitlements['id'] ?? ''), ['pro', 'enterprise'], true) && ! $this->isPending;
        $this->isFree = ! $this->isPending && ! $this->isPro;

        $subscriptionEndsAt = $tenant->getRawOriginal('subscription_ends_at');
        $subscriptionEndsAt = $subscriptionEndsAt ? Carbon::parse($subscriptionEndsAt) : null;

        if ($this->isPro && $subscriptionEndsAt) {
            $this->daysRemaining = max(0, (int) Carbon::now()->diffInDays($subscriptionEndsAt, false));
            $this->expiresAt = $subscriptionEndsAt->format('d/m/Y');
        }

        $this->form->fill([
            'bank_reference' => '',
            'proof_image_path' => null,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Pago por transferencia')
                    ->description('Transfiere el monto exacto, guarda tu comprobante y envialo a revision.')
                    ->schema([
                        Forms\Components\Placeholder::make('bank_info')
                            ->label('')
                            ->content(fn (): HtmlString => new HtmlString($this->getBankInstructionsHtml())),

                        Forms\Components\TextInput::make('bank_reference')
                            ->label('Numero de referencia')
                            ->placeholder('Ej: 12345678')
                            ->required()
                            ->maxLength(100)
                            ->helperText('Usa el numero que aparece en la banca en linea o en tu recibo.'),

                        Forms\Components\FileUpload::make('proof_image_path')
                            ->label('Comprobante')
                            ->image()
                            ->required()
                            ->directory('payments/proofs')
                            ->panelLayout('compact')
                            ->previewable(false)
                            ->maxSize(5120)
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->imagePreviewHeight('180')
                            ->helperText('Sube una captura clara de la transferencia. Maximo 5 MB.')
                            ->columnSpanFull(),
                    ])
                    ->columns(1),
            ])
            ->statePath('data');
    }

    protected function getBankInstructionsHtml(): string
    {
        $price = $this->formattedPrice;

        if ($this->bankInstructions !== '') {
            return str_replace('{precio}', $price, $this->bankInstructions);
        }

        return '
            <div class="biz-card">
                <div class="biz-stack">
                    <div>
                        <p class="biz-kicker">Transferencia bancaria</p>
                        <h3 class="biz-heading">'.e(($this->planPresentation['marketingLabel'] ?? 'Plan PRO').' mensual').'</h3>
                        <p class="biz-muted">'.e($this->planPresentation['marketingCopy'] ?? 'Activa el plan y habilita funciones premium del panel.').'</p>
                    </div>

                    <div class="biz-list">
                        <div class="biz-split-item">
                            <span class="biz-muted">Banco</span>
                            <strong>BAC Credomatic</strong>
                        </div>
                        <div class="biz-split-item">
                            <span class="biz-muted">Beneficiario</span>
                            <strong>Dovra AI</strong>
                        </div>
                        <div class="biz-split-item">
                            <span class="biz-muted">Cuenta</span>
                            <strong>123-456-7890</strong>
                        </div>
                        <div class="biz-split-item">
                            <span class="biz-muted">Monto</span>
                            <strong class="biz-heading">'.$price.'</strong>
                        </div>
                    </div>

                    <div class="biz-empty-state">
                        <p class="biz-action-copy">
                            Guarda una captura clara del deposito. El equipo revisa el pago manualmente y activa tu negocio cuando la transferencia queda validada.
                        </p>
                    </div>
                </div>
            </div>
        ';
    }

    public function submitPayment(): void
    {
        $data = $this->form->getState();
        $tenant = $this->getTenantRecord();
        $user = auth()->user();

        if (! $tenant instanceof Tenant) {
            Notification::make()
                ->danger()
                ->title('No se pudo procesar el pago')
                ->body('No encontramos el negocio actual.')
                ->send();

            return;
        }

        if (! $user instanceof User || ! app(TenantPermissionGuardInterface::class)->allows($user, $tenant, Capability::BILLING_PORTAL, Permission::BILLING_MANAGE)) {
            Notification::make()
                ->warning()
                ->title('No tienes permisos para reportar pagos')
                ->send();

            return;
        }

        if (! $this->paymentsTableAvailable) {
            Notification::make()
                ->warning()
                ->title('Historial de pagos no disponible')
                ->body('La tabla de pagos no existe en este entorno. Revisa migraciones antes de reportar comprobantes.')
                ->send();

            return;
        }

        $existingPending = SubscriptionPayment::query()
            ->where('tenant_id', $tenant->getKey())
            ->where('status', SubscriptionPayment::STATUS_PENDING)
            ->exists();

        if ($existingPending) {
            Notification::make()
                ->warning()
                ->title('Ya tienes un pago pendiente')
                ->body('Espera a que el equipo revise el comprobante anterior.')
                ->send();

            return;
        }

        SubscriptionPayment::forceCreate([
            'tenant_id' => $tenant->getKey(),
            'amount' => $this->subscriptionAmount,
            'bank' => 'bank_transfer',
            'reference_number' => $data['bank_reference'],
            'proof_path' => $data['proof_image_path'],
            'status' => SubscriptionPayment::STATUS_PENDING,
        ]);

        Notification::make()
            ->success()
            ->title('Pago enviado a revision')
            ->body('Ya recibimos tu comprobante y lo validaremos en breve.')
            ->send();

        $this->redirect(self::getUrl());
    }

    public function startStripeCheckout(BillingCheckoutService $billingCheckoutService)
    {
        $tenant = $this->getTenantRecord();
        $user = auth()->user();

        if (! $tenant instanceof Tenant) {
            Notification::make()
                ->danger()
                ->title('No se pudo iniciar el checkout')
                ->body('No encontramos el negocio actual.')
                ->send();

            return null;
        }

        if (! $user instanceof User || ! app(TenantPermissionGuardInterface::class)->allows($user, $tenant, Capability::BILLING_PORTAL, Permission::BILLING_MANAGE)) {
            Notification::make()
                ->warning()
                ->title('No tienes permisos para gestionar pagos')
                ->send();

            return null;
        }

        if ($this->stripeSubscriptionIdFor($tenant) !== null) {
            Notification::make()
                ->warning()
                ->title('La suscripcion ya existe')
                ->body('Usa el portal de Stripe para renovar o administrar la suscripcion existente sin crear un cobro recurrente duplicado.')
                ->send();

            return null;
        }

        $result = $billingCheckoutService->start(
            tenantId: (string) $tenant->getKey(),
            planIdentifier: 'pro',
            clientIdempotencyKey: 'filament-upgrade-'.$tenant->getKey().'-pro-'.now()->utc()->format('Y-m'),
        );

        if ($result->status === BillingCheckoutResult::STATUS_REQUIRES_PAYMENT && filled($result->checkoutUrl)) {
            return redirect()->away((string) $result->checkoutUrl);
        }

        if ($result->status === BillingCheckoutResult::STATUS_MANUAL_PENDING) {
            Notification::make()
                ->warning()
                ->title('Pago manual activo')
                ->body('Stripe no esta activo en este entorno. Usa el flujo de comprobante bancario.')
                ->send();

            return null;
        }

        Notification::make()
            ->danger()
            ->title('No se pudo iniciar el checkout')
            ->body($result->message ?? 'Revisa la configuracion de Stripe antes de reintentar.')
            ->send();

        return null;
    }

    public function openCustomerPortal(SubscriptionCheckoutGatewayInterface $checkoutGateway)
    {
        $tenant = $this->getTenantRecord();
        $user = auth()->user();
        $stripeCustomerId = $tenant instanceof Tenant ? $this->stripeCustomerIdFor($tenant) : null;

        if (! $tenant instanceof Tenant || ! is_string($stripeCustomerId) || $stripeCustomerId === '') {
            Notification::make()
                ->warning()
                ->title('Portal no disponible')
                ->body('Este negocio todavia no tiene una referencia activa de Stripe.')
                ->send();

            return null;
        }

        if (! $user instanceof User || ! app(TenantPermissionGuardInterface::class)->allows($user, $tenant, Capability::BILLING_PORTAL, Permission::BILLING_MANAGE)) {
            Notification::make()
                ->warning()
                ->title('No tienes permisos para gestionar pagos')
                ->send();

            return null;
        }

        $result = $checkoutGateway->createCustomerPortalSession(
            customerId: $stripeCustomerId,
            returnUrl: self::getUrl(),
        );

        if ($result->success && filled($result->url)) {
            return redirect()->away((string) $result->url);
        }

        Notification::make()
            ->danger()
            ->title('No se pudo abrir el portal')
            ->body($result->errorMessage ?? 'Revisa la configuracion de Stripe antes de reintentar.')
            ->send();

        return null;
    }

    public function table(Table $table): Table
    {
        $tenant = $this->getTenantRecord();

        return $table
            ->query(
                SubscriptionPayment::query()
                    ->where('tenant_id', $tenant?->getKey() ?? '')
                    ->orderBy('created_at', 'desc')
            )
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('reference_number')
                    ->label('Referencia')
                    ->searchable()
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Monto')
                    ->money('HNL')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        SubscriptionPayment::STATUS_APPROVED => 'success',
                        SubscriptionPayment::STATUS_PENDING => 'warning',
                        SubscriptionPayment::STATUS_REJECTED => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        SubscriptionPayment::STATUS_APPROVED => 'Aprobado',
                        SubscriptionPayment::STATUS_PENDING => 'Pendiente',
                        SubscriptionPayment::STATUS_REJECTED => 'Rechazado',
                        default => $state,
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('download_receipt')
                    ->label('Recibo')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->visible(fn (SubscriptionPayment $record): bool => $record->status === SubscriptionPayment::STATUS_APPROVED)
                    ->action(fn (SubscriptionPayment $record) => $this->downloadReceipt($record)),
            ])
            ->emptyState(fn () => view('filament.app.components.table-empty-state', [
                'heading' => 'Todavia no tienes pagos registrados',
                'description' => 'Cuando un pago sea aprobado o rechazado, aqui veras el historial y podras descargar el recibo.',
            ]));
    }

    public function downloadReceipt(SubscriptionPayment $payment)
    {
        $tenant = $this->getTenantRecord();

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

    protected function getHeaderActions(): array
    {
        $actions = [];
        $supportUrl = $this->getSupportWhatsAppUrl();

        if ($this->isFree && ! $this->isPending) {
            if (strtolower((string) config('services.payment.provider', 'generic')) === 'stripe') {
                $actions[] = Action::make('start_stripe_checkout')
                    ->label('Activar PRO con tarjeta')
                    ->icon('heroicon-o-credit-card')
                    ->color('success')
                    ->action('startStripeCheckout');
            } else {
                $actions[] = Action::make('submit_payment')
                    ->label('Enviar pago a revision')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->keyBindings(['mod+s'])
                    ->action('submitPayment');
            }
        }

        if ($this->isPro && $this->daysRemaining <= 7 && ! $this->isPending) {
            $stripeEnabled = strtolower((string) config('services.payment.provider', 'generic')) === 'stripe';
            $tenant = $this->getTenantRecord();
            $hasStripeSubscription = $tenant instanceof Tenant
                && $this->stripeSubscriptionIdFor($tenant) !== null;
            $actions[] = Action::make('renew')
                ->label('Renovar suscripcion')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->action($stripeEnabled && $hasStripeSubscription ? 'openCustomerPortal' : ($stripeEnabled ? 'startStripeCheckout' : 'submitPayment'));
        }

        $tenant = $this->getTenantRecord();
        if (
            $this->isPro
            && strtolower((string) config('services.payment.provider', 'generic')) === 'stripe'
            && $tenant instanceof Tenant
            && filled($this->stripeCustomerIdFor($tenant))
        ) {
            $actions[] = Action::make('customer_portal')
                ->label('Administrar tarjeta')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('gray')
                ->action('openCustomerPortal');
        }

        if ($supportUrl !== null) {
            $actions[] = Action::make('contact_support')
                ->label('Hablar con soporte')
                ->icon('heroicon-o-chat-bubble-left-right')
                ->color('gray')
                ->url($supportUrl, shouldOpenInNewTab: true);
        }

        return $actions;
    }

    private function stripeCustomerIdFor(Tenant $tenant): ?string
    {
        $customerId = DB::table('tenant_billing_references')
            ->where('tenant_id', $tenant->getKey())
            ->where('provider', 'stripe')
            ->value('provider_customer_id');

        return is_string($customerId) && $customerId !== '' ? $customerId : null;
    }

    private function stripeSubscriptionIdFor(Tenant $tenant): ?string
    {
        $subscriptionId = DB::table('tenant_billing_references')
            ->where('tenant_id', $tenant->getKey())
            ->where('provider', 'stripe')
            ->value('provider_subscription_id');

        return is_string($subscriptionId) && $subscriptionId !== '' ? $subscriptionId : null;
    }

    private function loadSettings(): void
    {
        $this->subscriptionAmount = \App\Domain\TenantGovernance\Services\SubscriptionPricingService::canonicalProPriceInMajorUnits();
        $this->formattedPrice = 'L. '.number_format($this->subscriptionAmount, 2);
        $this->bankInstructions = '';
        $this->supportWhatsApp = '';

        try {
            $settings = app(GeneralSettings::class);
            $this->bankInstructions = (string) ($settings->bank_transfer_instructions ?? '');
            $this->supportWhatsApp = (string) ($settings->support_whatsapp_number ?? '');
        } catch (\Throwable) {
            // Canonical plan price remains available even when optional settings storage is unavailable.
        }
    }

    /**
     * @return array<string, string>
     */
    private function resolvePlanPresentation(Tenant $tenant): array
    {
        $planLabel = $tenant->is_pro ? 'Plan PRO' : 'Plan Base';

        return [
            'marketingLabel' => $planLabel,
            'marketingCopy' => $tenant->is_pro
                ? 'Tu negocio puede usar funciones PRO segun capacidades: mas catalogo, reservas/reportes, campanas e IA activable por tenant.'
                : 'Activa PRO para ampliar catalogo, reservas/reportes, campanas e IA privada con activacion segura.',
            'brandingCopy' => 'Free sirve para validar con micrositio, directorio, contacto y hasta 10 productos. Sigue el flujo para activar PRO cuando lo necesites.',
        ];
    }

    public function billingBadge(): string
    {
        if ($this->isPending) {
            return 'Pago en revision';
        }

        return (string) ($this->planPresentation['marketingLabel'] ?? ($this->isPro ? 'Plan PRO' : 'Plan Base'));
    }

    public function billingHeroTitle(): string
    {
        if ($this->isPending) {
            return 'Tu pago esta en revision';
        }

        return $this->isPro ? 'Suscripcion activa' : 'Activa tu plan PRO';
    }

    public function billingHeroCopy(): string
    {
        if ($this->isPending) {
            return 'Ya recibimos tu comprobante. Cuando el equipo valide la transferencia, tu plan comercial quedara activo.';
        }

        if ($this->isPro) {
            return (string) ($this->planPresentation['marketingCopy'] ?? 'Tu negocio ya tiene activadas las funciones premium.');
        }

        return (string) ($this->planPresentation['brandingCopy'] ?? 'Sigue el flujo para transferir, subir el comprobante y dejar tu negocio listo para revision.');
    }

    public function billingPlanHeading(): string
    {
        return $this->isPro ? 'Pagos registrados' : (($this->planPresentation['marketingLabel'] ?? 'Plan PRO').' para tu negocio');
    }

    private function getSupportWhatsAppUrl(): ?string
    {
        $digits = preg_replace('/\D+/', '', $this->supportWhatsApp);

        if ($digits === null || $digits === '') {
            return null;
        }

        if (strlen($digits) === 8) {
            $digits = '504'.$digits;
        }

        $message = rawurlencode('Hola, necesito ayuda con la suscripcion de mi negocio en Dovra AI.');

        return "https://wa.me/{$digits}?text={$message}";
    }

    protected function getTenantRecord(): ?Tenant
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Tenant ? $tenant : null;
    }
}


