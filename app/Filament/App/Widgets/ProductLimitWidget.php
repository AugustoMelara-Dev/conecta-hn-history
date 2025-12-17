<?php

namespace App\Filament\App\Widgets;

use App\Settings\GeneralSettings;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;

/**
 * ProductLimitWidget - Monitor Inteligente de Recursos
 * 
 * Widget de alto impacto que cambia radicalmente según el plan:
 * - FREE: Barra de progreso con semáforo + CTA de upgrades
 * - PRO: Tarjeta VIP premium con validación de beneficios
 * 
 * Posicionado en la parte superior del dashboard (sort: -10)
 * Ocupa todo el ancho (columnSpan: 'full')
 */
class ProductLimitWidget extends Widget
{
    /**
     * Vista del widget (non-static in v4.2)
     */
    protected string $view = 'filament.app.widgets.product-limit-widget';

    /**
     * Prioridad: Aparece primero en el dashboard (static in v4.2)
     */
    protected static ?int $sort = -10;

    /**
     * Propiedades públicas accesibles en la vista
     */
    public bool $isPro = false;
    public int $currentCount = 0;
    public int $limit = 10;
    public float $percentage = 0;
    public string $upgradeUrl = '';
    public string $billingUrl = '';
    public ?string $planExpiresAt = null;

    /**
     * Configuración del layout
     */
    public function getColumnSpan(): int | string | array
    {
        return 'full';
    }

    /**
     * Inicialización y cálculo de métricas
     */
    public function mount(): void
    {
        $tenant = Filament::getTenant();

        if (!$tenant) {
            return;
        }

        // Determinar tipo de plan
        $this->isPro = (bool) $tenant->is_pro;

        // URLs de navegación (puede fallar durante boot)
        try {
            $this->upgradeUrl = route('filament.app.pages.manage-billing', ['tenant' => $tenant->slug]);
            $this->billingUrl = route('filament.app.pages.manage-billing', ['tenant' => $tenant->slug]);
        } catch (\Exception $e) {
            $this->upgradeUrl = '#';
            $this->billingUrl = '#';
        }

        // Fecha de expiración PRO
        if ($this->isPro && $tenant->plan_expires_at) {
            $this->planExpiresAt = $tenant->plan_expires_at->format('d/m/Y');
        }

        // ═══════════════════════════════════════════════════════════════
        // CÁLCULO DE MÉTRICAS (Solo para usuarios Free)
        // ═══════════════════════════════════════════════════════════════
        if (!$this->isPro) {
            // Conteo eficiente de productos
            $this->currentCount = $tenant->products()->count();

            // Límite: primero del tenant, luego de settings globales
            $this->limit = $tenant->product_limit 
                ?? app(GeneralSettings::class)->free_plan_product_limit 
                ?? 10;

            // Porcentaje de saturación (manejo de división por cero)
            $this->percentage = $this->limit > 0 
                ? min(100, round(($this->currentCount / $this->limit) * 100, 1))
                : 0;
        }
    }

    /**
     * Determina el color del semáforo según el porcentaje
     */
    public function getProgressColor(): string
    {
        if ($this->percentage < 50) {
            return 'bg-success-500'; // Verde
        } elseif ($this->percentage < 80) {
            return 'bg-warning-500'; // Ámbar
        } else {
            return 'bg-danger-600'; // Rojo
        }
    }

    /**
     * Determina si está en zona crítica
     */
    public function isInDangerZone(): bool
    {
        return $this->percentage >= 90;
    }

    /**
     * Obtiene el mensaje de estado según el uso
     */
    public function getStatusMessage(): string
    {
        if ($this->percentage < 50) {
            return '¡Tienes espacio de sobra! Sigue agregando productos.';
        } elseif ($this->percentage < 80) {
            return 'Estás aprovechando bien tu plan. Considera actualizar pronto.';
        } elseif ($this->percentage < 100) {
            return '¡Atención! Estás a punto de quedarte sin espacio.';
        } else {
            return '⚠️ Has alcanzado el límite. Actualiza para seguir creciendo.';
        }
    }
}
