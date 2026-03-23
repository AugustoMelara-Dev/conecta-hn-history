<?php

declare(strict_types=1);

namespace App\Filament\App\Pages;

use App\Models\SubscriptionPayment;
use App\Models\Tenant;
use App\Settings\GeneralSettings;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Filament\Actions\Action;
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
use Illuminate\Support\HtmlString;
/**
 * @property-read Schema $form
 */
class ManageBilling extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected string $view = 'filament.app.pages.manage-billing';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';

    public bool $isPending = false;

    public bool $isPro = false;

    public bool $isFree = true;

    public ?SubscriptionPayment $pendingPayment = null;

    public int $daysRemaining = 0;

    public ?string $expiresAt = null;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public float $subscriptionAmount = 99.00;

    public string $formattedPrice = 'L. 99.00';

    public string $bankInstructions = '';

    public string $supportWhatsApp = '';

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
            \App\Domain\TenantGovernance\Enums\Capability::CATALOG_BASIC,
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
        return 'Suscripcion y Pagos';
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

        $this->pendingPayment = SubscriptionPayment::query()
            ->where('tenant_id', $tenant->getKey())
            ->where('status', SubscriptionPayment::STATUS_PENDING)
            ->latest()
            ->first();

        $this->isPending = $this->pendingPayment instanceof SubscriptionPayment;
        $this->isPro = (bool) $tenant->is_pro && ! $this->isPending;
        $this->isFree = ! $this->isPending && ! $this->isPro;

        $subscriptionEndsAt = $tenant->subscription_ends_at;

        if ($this->isPro && $subscriptionEndsAt) {
            $this->daysRemaining = max(0, (int) Carbon::now()->diffInDays($subscriptionEndsAt, false));
            $this->expiresAt = $subscriptionEndsAt->format('d/m/Y');
        }

        $this->form->fill([
            'bank_reference' => '',
            'proof_image_path' => null,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
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
                        <h3 class="biz-heading">Plan PRO mensual</h3>
                        <p class="biz-muted">Activa el plan y habilita funciones premium del panel.</p>
                    </div>

                    <div class="biz-list">
                        <div class="biz-split-item">
                            <span class="biz-muted">Banco</span>
                            <strong>BAC Credomatic</strong>
                        </div>
                        <div class="biz-split-item">
                            <span class="biz-muted">Beneficiario</span>
                            <strong>Conecta HN S.A.</strong>
                        </div>
                        <div class="biz-split-item">
                            <span class="biz-muted">Cuenta</span>
                            <strong>123-456-7890</strong>
                        </div>
                        <div class="biz-split-item">
                            <span class="biz-muted">Monto</span>
                            <strong style="font-size: 1.4rem; color: #34d399;">' . $price . '</strong>
                        </div>
                    </div>

                    <div class="biz-empty-state" style="padding: 1rem; text-align: left;">
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

        if (! $tenant instanceof Tenant) {
            Notification::make()
                ->danger()
                ->title('No se pudo procesar el pago')
                ->body('No encontramos el negocio actual.')
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
                    ->placeholder('—'),
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
                Action::make('download_receipt')
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
        }, 'recibo-' . $payment->id . '.pdf', [
            'Content-Type' => 'application/pdf',
        ]);
    }

    protected function getHeaderActions(): array
    {
        if ($this->isPro && $this->daysRemaining <= 7) {
            return [
                Action::make('renew')
                    ->label('Renovar suscripcion')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->url(self::getUrl()),
            ];
        }

        return [];
    }

    private function loadSettings(): void
    {
        try {
            $settings = app(GeneralSettings::class);
            $this->subscriptionAmount = (float) ($settings->pro_plan_price ?? 99.00);
            $this->formattedPrice = 'L. ' . number_format($this->subscriptionAmount, 2);
            $this->bankInstructions = (string) ($settings->bank_transfer_instructions ?? '');
            $this->supportWhatsApp = (string) ($settings->support_whatsapp_number ?? '');
        } catch (\Throwable) {
            $this->subscriptionAmount = 99.00;
            $this->formattedPrice = 'L. 99.00';
            $this->bankInstructions = '';
            $this->supportWhatsApp = '';
        }
    }

    protected function getTenantRecord(): ?Tenant
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Tenant ? $tenant : null;
    }
}
