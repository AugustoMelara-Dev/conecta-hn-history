<?php

declare(strict_types=1);

namespace App\Filament\App\Widgets;

use App\Infrastructure\TenantGovernance\Presentation\TenantPlanPresentationReader;
use App\Models\Tenant;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;

class ProductLimitWidget extends Widget
{
    protected static bool $isLazy = false;

    protected string $view = 'filament.app.widgets.product-limit-widget';

    protected static ?int $sort = -10;

    public bool $isPro = false;

    public int $currentCount = 0;

    public int $limit = 10;

    public float $percentage = 0;

    public string $upgradeUrl = '';

    public string $billingUrl = '';

    public ?string $planExpiresAt = null;

    public string $planLabel = 'Plan Base';

    public string $catalogCopy = '';

    public string $catalogStatusCopy = '';

    public string $billingCta = 'Activar PRO';

    public function getColumnSpan(): int|string|array
    {
        return 'full';
    }

    public function mount(): void
    {
        $tenant = $this->getTenantRecord();

        if (! $tenant instanceof Tenant) {
            return;
        }

        $plan = app(TenantPlanPresentationReader::class)->forTenant($tenant);
        $tenantSlug = (string) ($tenant->getAttribute('slug') ?? '');

        $this->isPro = $plan['isCatalogUnlimited'];
        $this->planLabel = $plan['marketingLabel'];
        $this->catalogCopy = $plan['catalogCopy'];
        $this->catalogStatusCopy = $plan['catalogStatusCopy'];
        $this->billingCta = $plan['billingCta'];

        try {
            $this->upgradeUrl = route('filament.app.pages.manage-billing', ['tenant' => $tenantSlug]);
            $this->billingUrl = route('filament.app.pages.manage-billing', ['tenant' => $tenantSlug]);
        } catch (\Throwable) {
            $this->upgradeUrl = '#';
            $this->billingUrl = '#';
        }

        $subscriptionEndsAt = $tenant->getAttributes()['subscription_ends_at'] ?? null;
        if ($this->isPro && $subscriptionEndsAt instanceof Carbon) {
            $this->planExpiresAt = $subscriptionEndsAt->format('d/m/Y');
        } elseif ($this->isPro && is_string($subscriptionEndsAt) && $subscriptionEndsAt !== '') {
            try {
                $this->planExpiresAt = Carbon::parse($subscriptionEndsAt)->format('d/m/Y');
            } catch (\Throwable) {
                $this->planExpiresAt = null;
            }
        }

        if (! $this->isPro) {
            $this->currentCount = $tenant->products()->count();
            $this->limit = (int) ($plan['productLimit'] ?? 10);
            $this->percentage = $this->limit > 0
                ? (float) min(100, round(($this->currentCount / $this->limit) * 100, 1))
                : 0.0;
        }
    }

    public function getProgressColor(): string
    {
        if ($this->percentage < 50) {
            return 'bg-success-500';
        }

        if ($this->percentage < 80) {
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
        return $this->catalogStatusCopy !== '' ? $this->catalogStatusCopy : 'Tienes espacio de sobra. Sigue agregando productos.';
    }

    private function getTenantRecord(): ?Tenant
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Tenant ? $tenant : null;
    }
}
