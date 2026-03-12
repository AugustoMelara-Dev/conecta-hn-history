<?php

declare(strict_types=1);

namespace App\Filament\App\Widgets;

use App\Models\Interaction;
use Filament\Widgets\Widget;

/**
 * TenantStatsWidget - Dashboard Bento Grid con Psicología de Ventas
 *
 * Arquitectura de Datos:
 * - Visitas: $tenant->views (contador atómico, optimizado)
 * - Clics WhatsApp: Interaction::where('type', 'whatsapp_click')
 * - Productos: $tenant->products()->count()
 * - Rating: $tenant->rating (promedio de reseñas)
 *
 * Growth Hacking:
 * - Tarjeta de Plan cambia de color según FREE/PRO
 * - Límite de productos genera presión visual en FREE
 */
class TenantStatsWidget extends Widget
{
    protected string $view = 'filament.app.widgets.tenant-stats-widget';

    // ═══════════════════════════════════════════════════════════════════════
    // PROPIEDADES PÚBLICAS (Accesibles en la vista Blade)
    // ═══════════════════════════════════════════════════════════════════════
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
        return 1; // Mostrar primero en el dashboard
    }

    public function mount(): void
    {
        $tenant = \Filament\Facades\Filament::getTenant();

        if (! $tenant) {
            return;
        }

        // ═══════════════════════════════════════════════════════════════════
        // 1. VISITAS: Contador atómico del modelo Tenant (OPTIMIZADO)
        // Incrementado en ShowTenant.php cada vez que alguien visita
        // ═══════════════════════════════════════════════════════════════════
        $this->views = $tenant->views ?? 0;

        // ═══════════════════════════════════════════════════════════════════
        // 2. CLICS WHATSAPP: De la tabla Interactions (granular)
        // Esto permite tracking detallado de conversiones
        // ═══════════════════════════════════════════════════════════════════
        $this->whatsappClicks = Interaction::where('tenant_id', $tenant->id)
            ->where('type', 'whatsapp_click')
            ->count();

        // ═══════════════════════════════════════════════════════════════════
        // 3. PRODUCTOS: Conteo directo de productos activos
        // ═══════════════════════════════════════════════════════════════════
        $this->productsCount = $tenant->products()->where('is_active', true)->count();

        // ═══════════════════════════════════════════════════════════════════
        // 4. RATING Y RESEÑAS
        // ═══════════════════════════════════════════════════════════════════
        $this->rating = (float) ($tenant->rating ?? 0);

        // Verificar si existe la relación reviews
        if (method_exists($tenant, 'reviews')) {
            $this->reviewsCount = $tenant->reviews()->count();
        }

        // ═══════════════════════════════════════════════════════════════════
        // 5. ESTADO DEL PLAN (Lógica de Monetización)
        // ═══════════════════════════════════════════════════════════════════
        $this->isPro = (bool) $tenant->is_pro;
        $this->planName = $this->isPro ? 'Plan PRO' : 'Plan Gratuito';

        // Obtener límite de productos desde settings
        try {
            $settings = app(\App\Settings\GeneralSettings::class);
            $this->productLimit = $settings->free_plan_product_limit ?? 10;
        } catch (\Exception $e) {
            $this->productLimit = 10;
        }
    }

    /**
     * URL para la página de upgrade/billing
     */
    public function getUpgradeUrl(): string
    {
        $tenant = \Filament\Facades\Filament::getTenant();

        if ($tenant) {
            return route('filament.app.pages.manage-billing', ['tenant' => $tenant->slug]);
        }

        return '#';
    }

    /**
     * Calcula la tasa de conversión
     */
    public function getConversionRate(): float
    {
        if ($this->views === 0) {
            return 0;
        }

        return round(($this->whatsappClicks / $this->views) * 100, 1);
    }
}
