<?php

declare(strict_types=1);

namespace App\Filament\App\Widgets;

use App\Models\Interaction;
use App\Models\Tenant;
use App\Settings\GeneralSettings;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;

class TenantStatsWidget extends Widget
{
    protected static bool $isLazy = false;

    protected string $view = 'filament.app.widgets.tenant-stats-widget';

    public int $views = 0;

    public int $whatsappClicks = 0;

    public int $productsCount = 0;

    public float $rating = 0.0;

    public int $reviewsCount = 0;

    public string $planName = 'Plan Gratuito';

    public bool $isPro = false;

    public int $productLimit = 10;

    public static function getSort(): int
    {
        return 1;
    }

    public function mount(): void
    {
        $tenant = $this->getTenantRecord();

        if (! $tenant instanceof Tenant) {
            return;
        }

        $tenantId = (string) $tenant->getKey();
        $this->views = Interaction::query()
            ->where('tenant_id', $tenantId)
            ->where('type', 'view')
            ->count();
        $this->whatsappClicks = Interaction::query()
            ->where('tenant_id', $tenantId)
            ->where('type', 'whatsapp_click')
            ->count();
        $this->productsCount = $tenant->products()->where('is_active', true)->count();
        $reviewsCount = $tenant->getAttribute('reviews_count');
        $this->reviewsCount = is_numeric($reviewsCount)
            ? (int) $reviewsCount
            : $tenant->reviews()->count();

        $ratingAverage = $tenant->getAttribute('rating_avg');
        if (is_numeric($ratingAverage)) {
            $this->rating = round((float) $ratingAverage, 1);
        } elseif ($this->reviewsCount > 0) {
            $this->rating = round((float) ($tenant->reviews()->avg('rating') ?? 0), 1);
        }

        $this->isPro = $tenant->getIsProAttribute();
        $this->planName = $this->isPro ? 'Plan PRO' : 'Plan Gratuito';

        try {
            $this->productLimit = (int) (app(GeneralSettings::class)->free_plan_product_limit ?? 10);
        } catch (\Throwable) {
            $this->productLimit = 10;
        }
    }

    public function getUpgradeUrl(): string
    {
        $tenant = $this->getTenantRecord();

        if (! $tenant instanceof Tenant) {
            return '#';
        }

        return route('filament.app.pages.manage-billing', ['tenant' => (string) ($tenant->getAttribute('slug') ?? '')]);
    }

    public function getConversionRate(): float
    {
        if ($this->views === 0) {
            return 0;
        }

        return round(($this->whatsappClicks / $this->views) * 100, 1);
    }

    private function getTenantRecord(): ?Tenant
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Tenant ? $tenant : null;
    }
}
