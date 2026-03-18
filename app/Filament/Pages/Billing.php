<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\Tenant;
use App\Models\User;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

/**
 * Billing Dashboard Page
 *
 * Provides subscription status, plan information, and upgrade options.
 * MVP: Manual bank transfer instructions (no Stripe integration yet).
 */
class Billing extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationLabel = 'Facturacion';

    protected static ?string $title = 'Mi Suscripcion';

    protected static ?string $slug = 'billing';

    protected static ?int $navigationSort = 100;

    protected static string|\UnitEnum|null $navigationGroup = 'Mi Cuenta';

    protected string $view = 'filament.pages.billing';

    public ?Tenant $tenant = null;

    public function mount(): void
    {
        $user = Auth::user();
        $tenant = $user instanceof User ? $user->tenant : null;

        $this->tenant = $tenant instanceof Tenant ? $tenant : null;
    }

    /**
     * @return array<string, bool|int|string|null>
     */
    public function getSubscriptionData(): array
    {
        if (! $this->tenant instanceof Tenant) {
            return [
                'plan' => 'Sin Negocio',
                'status' => 'N/A',
                'raw_status' => null,
                'expires_at' => null,
                'days_left' => null,
                'is_pro' => false,
                'is_trial' => false,
                'is_past_due' => false,
                'is_expiring_soon' => false,
                'is_canceled' => false,
            ];
        }

        $rawStatus = $this->tenant->subscription_status ?? 'active';

        return [
            'plan' => $this->tenant->plan_type_label ?? 'Plan Semilla',
            'status' => $this->tenant->subscription_status_label ?? 'Activo',
            'raw_status' => $rawStatus,
            'expires_at' => $this->tenant->subscription_ends_at?->format('d M Y'),
            'days_left' => $this->tenant->days_until_expiry,
            'is_pro' => $this->tenant->getIsProAttribute(),
            'is_trial' => $rawStatus === 'trial',
            'is_past_due' => $rawStatus === 'past_due',
            'is_canceled' => $rawStatus === 'canceled',
            'is_expiring_soon' => $this->tenant->isExpiringSoon(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function getBankInfo(): array
    {
        return [
            'bank_name' => 'Banco Atlantida',
            'account_type' => 'Cuenta de Ahorro',
            'account_number' => '2060-1234567-8',
            'account_holder' => 'CONECTA HN S.A. de C.V.',
            'email' => 'pagos@conectahn.com',
        ];
    }

    /**
     * @return array<string, array<string, array<int, string>|int|string>>
     */
    public function getPricing(): array
    {
        return [
            'monthly' => [
                'price' => 299,
                'period' => 'mes',
                'features' => ['Carrito de compras', 'Personalizacion de marca', 'Sin limite de productos'],
            ],
            'annual' => [
                'price' => 2499,
                'period' => 'ano',
                'discount' => '30% OFF',
                'features' => ['Todo de Pro', '2 meses gratis', 'Soporte prioritario'],
            ],
        ];
    }

    public function getWhatsAppPaymentUrl(): string
    {
        $phone = '50499999999';
        $tenantName = $this->tenant instanceof Tenant ? $this->tenant->name : 'Mi Negocio';
        $message = urlencode("Hola! Quiero enviar mi comprobante de pago para Plan Pro.\n\nNegocio: {$tenantName}");

        return "https://wa.me/{$phone}?text={$message}";
    }
}
