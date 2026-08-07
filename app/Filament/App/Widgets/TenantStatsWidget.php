<?php

declare(strict_types=1);

namespace App\Filament\App\Widgets;

use App\Models\Interaction;
use App\Settings\GeneralSettings;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;

class TenantStatsWidget extends Widget
{
    protected string $view = 'filament.app.widgets.tenant-stats-widget';

    protected static bool $isLazy = false;

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
        $tenant = Filament::getTenant();

        if (! $tenant) {
            return;
        }

        $this->views = $tenant->views ?? 0;
        $this->whatsappClicks = Interaction::where('tenant_id', $tenant->id)
            ->where('type', 'whatsapp_click')
            ->count();
        $this->productsCount = $tenant->products()->where('is_active', true)->count();
        $this->rating = (float) ($tenant->rating ?? 0);

        if (method_exists($tenant, 'reviews')) {
            $this->reviewsCount = $tenant->reviews()->count();
        }

        $this->isPro = (bool) $tenant->is_pro;
        $this->planName = $this->isPro ? 'Plan PRO' : 'Plan Gratuito';

        try {
            $settings = app(GeneralSettings::class);
            $this->productLimit = $settings->free_plan_product_limit ?? 10;
        } catch (\Exception $e) {
            $this->productLimit = 10;
        }
    }

    public function getUpgradeUrl(): string
    {
        $tenant = Filament::getTenant();

        if ($tenant) {
            return route('filament.app.pages.manage-billing', ['tenant' => $tenant->slug]);
        }

        return url('/app');
    }

    public function getConversionRate(): float
    {
        if ($this->views === 0) {
            return 0;
        }

        return round(($this->whatsappClicks / $this->views) * 100, 1);
    }
}
