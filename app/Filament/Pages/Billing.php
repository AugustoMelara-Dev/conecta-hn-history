<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\Tenant;
use App\Support\Product\ProductIdentity;
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
    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationLabel = 'Facturación';

    protected static ?string $title = 'Mi Suscripción';

    protected static ?string $slug = 'billing';

    protected static ?int $navigationSort = 100;

    protected static ?string $navigationGroup = 'Mi Cuenta';

    protected static string $view = 'filament.pages.billing';

    public ?Tenant $tenant = null;

    public function mount(): void
    {
        $this->tenant = Auth::user()?->tenant;
    }

    /**
     * Get current subscription status data
     */
    public function getSubscriptionData(): array
    {
        if (! $this->tenant) {
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
            ];
        }

        $rawStatus = $this->tenant->subscription_status ?? 'active';

        return [
            'plan' => $this->tenant->plan_type_label ?? 'Plan Semilla',
            'status' => $this->tenant->subscription_status_label ?? 'Activo',
            'raw_status' => $rawStatus,
            'expires_at' => $this->tenant->subscription_ends_at?->format('d M Y'),
            'days_left' => $this->tenant->days_until_expiry,
            // Use the NEW computed is_pro (not is_pro_computed)
            'is_pro' => $this->tenant->is_pro,
            // Semantic flags for UI badges
            'is_trial' => $rawStatus === 'trial',
            'is_past_due' => $rawStatus === 'past_due',
            'is_canceled' => $rawStatus === 'canceled',
            'is_expiring_soon' => $this->tenant->isExpiringSoon(),
        ];
    }

    /**
     * Bank transfer information for manual payments
     */
    public function getBankInfo(): array
    {
        return [
            'bank_name' => 'Banco Atlántida',
            'account_type' => 'Cuenta de Ahorro',
            'account_number' => '2060-1234567-8',
            'account_holder' => ProductIdentity::legalEntityName(),
            'email' => 'Contacta soporte desde tu panel',
        ];
    }

    /**
     * Pricing information
     */
    public function getPricing(): array
    {
        return [
            'monthly' => [
                'price' => 299,
                'period' => 'mes',
                'features' => ['Carrito de compras', 'Personalizacion de marca', 'Catalogo ampliado'],
            ],
            'annual' => [
                'price' => 2499,
                'period' => 'año',
                'discount' => '30% OFF',
                'features' => ['Todo de Pro', '2 meses gratis', 'Soporte prioritario'],
            ],
        ];
    }

    /**
     * Generate WhatsApp URL for payment proof
     */
    public function getWhatsAppPaymentUrl(): string
    {
        $phone = '50499999999'; // Replace with actual support number
        $message = urlencode("¡Hola! Quiero enviar mi comprobante de pago para Plan Pro.\n\nNegocio: ".($this->tenant?->name ?? 'Mi Negocio'));

        return "https://wa.me/{$phone}?text={$message}";
    }
}
