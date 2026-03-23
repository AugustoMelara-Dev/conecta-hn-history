<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domain\Identity\Enums\UserRole;
use App\Models\SubscriptionPayment;
use App\Models\Tenant;
use App\Models\User;
use App\Settings\GeneralSettings;
use Filament\Pages\Page;

final class Billing extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationLabel = 'Facturacion';

    protected static ?string $title = 'Facturacion de la plataforma';

    protected static ?string $slug = 'billing';

    protected static ?int $navigationSort = 100;

    protected static string|\UnitEnum|null $navigationGroup = 'Finanzas';

    protected string $view = 'filament.pages.billing';

    public int $pendingPayments = 0;

    public int $approvedPayments = 0;

    public int $rejectedPayments = 0;

    public int $proBusinesses = 0;

    public int $baseBusinesses = 0;

    public string $proPlanPrice = 'L. 99.00';

    public string $supportEmail = 'soporte@conectahn.com';

    public string $supportWhatsApp = '+504 0000-0000';

    /**
     * @var array<int, array<string, string|null>>
     */
    public array $recentPayments = [];

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return false;
        }

        $superAdmins = config('auth.super_admins', []);
        $isWhitelisted = in_array($user->email ?? '', $superAdmins, true);
        $role = $user->role;
        $hasSuperAdminRole = $role instanceof UserRole
            ? $role === UserRole::SUPER_ADMIN
            : (string) $role === UserRole::SUPER_ADMIN->value;

        return $hasSuperAdminRole || $user->isSuperAdmin() || $isWhitelisted;
    }

    public function mount(): void
    {
        $this->loadSettings();
        $this->loadMetrics();
    }

    private function loadSettings(): void
    {
        try {
            $settings = app(GeneralSettings::class);

            $this->proPlanPrice = 'L. ' . number_format((float) ($settings->pro_plan_price ?? 99), 2);
            $this->supportEmail = (string) ($settings->support_email ?? $this->supportEmail);

            $supportWhatsApp = (string) ($settings->support_whatsapp_number ?? '');
            if ($supportWhatsApp !== '') {
                $this->supportWhatsApp = $supportWhatsApp;
            }
        } catch (\Throwable) {
            // Keep the page usable with defaults.
        }
    }

    private function loadMetrics(): void
    {
        try {
            $payments = SubscriptionPayment::query()->withoutGlobalScopes();

            $this->pendingPayments = (clone $payments)
                ->where('status', SubscriptionPayment::STATUS_PENDING)
                ->count();

            $this->approvedPayments = (clone $payments)
                ->where('status', SubscriptionPayment::STATUS_APPROVED)
                ->count();

            $this->rejectedPayments = (clone $payments)
                ->where('status', SubscriptionPayment::STATUS_REJECTED)
                ->count();

            $this->proBusinesses = Tenant::query()
                ->where('plan_type', 'pro')
                ->count();

            $this->baseBusinesses = Tenant::query()
                ->where(function ($query): void {
                    $query->whereNull('plan_type')->orWhere('plan_type', '!=', 'pro');
                })
                ->count();

            $this->recentPayments = (clone $payments)
                ->with('tenant')
                ->latest()
                ->limit(6)
                ->get()
                ->map(function (SubscriptionPayment $payment): array {
                    $tenant = $payment->tenant()->first();

                    return [
                        'business' => $tenant instanceof Tenant ? (string) $tenant->getAttribute('name') : 'Negocio sin registro',
                        'reference' => (string) ($payment->reference_number ?? 'Sin referencia'),
                        'status' => match ((string) $payment->status) {
                            SubscriptionPayment::STATUS_PENDING => 'Pendiente',
                            SubscriptionPayment::STATUS_APPROVED => 'Aprobado',
                            SubscriptionPayment::STATUS_REJECTED => 'Rechazado',
                            default => 'Desconocido',
                        },
                        'amount' => 'L. ' . number_format((float) $payment->amount, 2),
                        'reported_at' => optional($payment->created_at)?->format('d/m/Y H:i'),
                    ];
                })
                ->all();
        } catch (\Throwable) {
            $this->recentPayments = [];
        }
    }
}
