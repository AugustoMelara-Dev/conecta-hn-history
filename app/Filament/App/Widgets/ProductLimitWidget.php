<?php

declare(strict_types=1);

namespace App\Filament\App\Widgets;

use App\Settings\GeneralSettings;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;

class ProductLimitWidget extends Widget
{
    protected static string $view = 'filament.app.widgets.product-limit-widget';

    protected static bool $isLazy = false;

    protected static ?int $sort = -10;

    public bool $isPro = false;

    public int $currentCount = 0;

    public int $limit = 10;

    public float $percentage = 0;

    public string $upgradeUrl = '';

    public string $billingUrl = '';

    public ?string $planExpiresAt = null;

    public function getColumnSpan(): int|string|array
    {
        return 'full';
    }

    public function mount(): void
    {
        $tenant = Filament::getTenant();

        if (! $tenant) {
            return;
        }

        $this->isPro = (bool) $tenant->is_pro;

        try {
            $this->upgradeUrl = route('filament.app.pages.manage-billing', ['tenant' => $tenant->slug]);
            $this->billingUrl = route('filament.app.pages.manage-billing', ['tenant' => $tenant->slug]);
        } catch (\Exception $e) {
            $this->upgradeUrl = url('/app');
            $this->billingUrl = url('/app');
        }

        if (
            $this->isPro
            && array_key_exists('plan_expires_at', $tenant->getAttributes())
            && $tenant->plan_expires_at
        ) {
            $this->planExpiresAt = $tenant->plan_expires_at->format('d/m/Y');
        }

        if (! $this->isPro) {
            $this->currentCount = $tenant->products()->count();

            $this->limit = $tenant->product_limit
                ?? app(GeneralSettings::class)->free_plan_product_limit
                ?? 10;

            $this->percentage = $this->limit > 0
                ? min(100, round(($this->currentCount / $this->limit) * 100, 1))
                : 0;
        }
    }

    public function getProgressColor(): string
    {
        if ($this->percentage < 50) {
            return 'bg-success-500';
        } elseif ($this->percentage < 80) {
            return 'bg-warning-500';
        }

        return 'bg-danger-600';
    }

    public function isInDangerZone(): bool
    {
        return $this->percentage >= 90;
    }

    public function getStatusMessage(): string
    {
        if ($this->percentage < 50) {
            return 'Free te deja validar el negocio con micrositio, directorio, contacto y hasta 10 productos.';
        } elseif ($this->percentage < 80) {
            return 'Estas usando bien Free. Revisa PRO cuando necesites mas catalogo, reservas/reportes o IA operativa.';
        } elseif ($this->percentage < 100) {
            return 'Estas cerca del limite Free de 10 productos.';
        }

        return 'Alcanzaste el limite Free de productos. PRO habilita mas catalogo y funciones operativas.';
    }
}
