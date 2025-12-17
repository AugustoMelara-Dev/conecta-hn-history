<?php

namespace App\Livewire\Tenant;

use App\Models\Tenant;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Tenant Dashboard - Conditional Behavior Interface
 *
 * Implements "Controlled Friction" and "Loss Aversion" patterns
 * to incentivize FREE → PRO upgrades.
 *
 * Behavior:
 * - PRO users: Full dashboard with all features enabled
 * - FREE users: Persistent conversion banner + resource limits
 */
#[Layout('layouts.app')]
class Dashboard extends Component
{
    // ═══════════════════════════════════════════════════════════════════════
    // CONSTANTS
    // ═══════════════════════════════════════════════════════════════════════

    public const LIMIT_FREE = 10;

    public const LIMIT_PRO = 100;

    public const DANGER_THRESHOLD = 80; // Percentage

    // ═══════════════════════════════════════════════════════════════════════
    // STATE
    // ═══════════════════════════════════════════════════════════════════════

    public Tenant $tenant;

    /**
     * Mount the component with the tenant from URL
     */
    public function mount(Tenant $tenant): void
    {
        // ═══════════════════════════════════════════════════════════════════
        // SECURITY: Verify ownership
        // ═══════════════════════════════════════════════════════════════════
        if (Auth::id() !== $tenant->user_id) {
            abort(403, 'No tienes permiso para acceder a este panel.');
        }

        $this->tenant = $tenant;
    }

    // ═══════════════════════════════════════════════════════════════════════
    // COMPUTED PROPERTIES
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Calculate dashboard statistics
     */
    #[Computed]
    public function stats(): array
    {
        $productCount = $this->tenant->products()->count();
        $limit = $this->tenant->is_pro ? self::LIMIT_PRO : self::LIMIT_FREE;
        $percentage = $limit > 0 ? round(($productCount / $limit) * 100) : 0;
        $percentage = min($percentage, 100); // Cap at 100%

        return [
            // Product metrics
            'product_count' => $productCount,
            'product_limit' => $limit,
            'product_percentage' => $percentage,
            'is_at_limit' => $productCount >= $limit,
            'is_danger_zone' => $percentage >= self::DANGER_THRESHOLD,
            'products_remaining' => max(0, $limit - $productCount),

            // Engagement metrics
            'total_visits' => $this->tenant->tenant_visits_count ?? 0,
            'visits_today' => $this->getTodayVisits(),
            'visits_this_week' => $this->getWeeklyVisits(),

            // Interaction metrics
            'whatsapp_clicks' => $this->tenant->whatsapp_clicks_count ?? 0,
            'favorites_count' => $this->tenant->favorites()->count(),
        ];
    }

    /**
     * Check if user is on FREE plan
     */
    #[Computed]
    public function isFree(): bool
    {
        return ! $this->tenant->is_pro;
    }

    /**
     * Get today's visit count
     */
    protected function getTodayVisits(): int
    {
        return $this->tenant->tenantVisits()
            ->whereDate('visited_at', today())
            ->count();
    }

    /**
     * Get this week's visit count
     */
    protected function getWeeklyVisits(): int
    {
        return $this->tenant->tenantVisits()
            ->whereBetween('visited_at', [now()->startOfWeek(), now()->endOfWeek()])
            ->count();
    }

    // ═══════════════════════════════════════════════════════════════════════
    // RENDER
    // ═══════════════════════════════════════════════════════════════════════

    public function render()
    {
        return view('livewire.tenant.dashboard')
            ->title($this->tenant->name.' - Dashboard | Conecta HN');
    }
}
