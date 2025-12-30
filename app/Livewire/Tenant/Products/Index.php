<?php

namespace App\Livewire\Tenant\Products;

use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Products Index - Catalog Management
 *
 * Features:
 * - Real-time search with debounce
 * - Pagination (12 items per page)
 * - Toggle visibility
 * - Soft delete with confirmation
 * - Plan limit enforcement
 */
#[Layout('layouts.app')]
class Index extends Component
{
    use WithPagination;

    // ═══════════════════════════════════════════════════════════════════════
    // PLAN LIMITS
    // ═══════════════════════════════════════════════════════════════════════

    public const LIMIT_FREE = 10;

    public const LIMIT_PRO = 100;

    // ═══════════════════════════════════════════════════════════════════════
    // STATE
    // ═══════════════════════════════════════════════════════════════════════

    public Tenant $tenant;

    #[Url]
    public string $search = '';

    /**
     * Mount with tenant from authenticated user
     */
    public function mount(): void
    {
        $user = Auth::user();

        if (! $user || ! $user->tenant) {
            abort(403, 'No tienes un negocio asociado.');
        }

        $this->tenant = $user->tenant;
    }

    // ═══════════════════════════════════════════════════════════════════════
    // COMPUTED PROPERTIES
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Paginated products with search filter
     */
    #[Computed]
    public function products()
    {
        // Use Model Helper to avoid Architecture Violation (withoutGlobalScopes)
        return Product::getAdminList($this->tenant->id, $this->search);
    }

    /**
     * Plan limits and quota status
     */
    #[Computed]
    public function limits(): array
    {
        // Use Model Helper to avoid Architecture Violation
        $currentCount = Product::countAllForTenant($this->tenant->id);

        $limit = $this->tenant->is_pro ? self::LIMIT_PRO : self::LIMIT_FREE;

        return [
            'current_count' => $currentCount,
            'limit' => $limit,
            'can_create' => $currentCount < $limit,
            'percentage' => $limit > 0 ? round(($currentCount / $limit) * 100) : 0,
        ];
    }

    // ═══════════════════════════════════════════════════════════════════════
    // ACTIONS
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Toggle product visibility (is_visible)
     */
    public function toggleVisibility(Product $product): void
    {
        // Security: Verify ownership
        if ($product->tenant_id !== $this->tenant->id) {
            session()->flash('error', 'No tienes permiso para modificar este producto.');

            return;
        }

        $product->update(['is_visible' => ! $product->is_visible]);

        $status = $product->is_visible ? 'visible' : 'oculto';
        session()->flash('toast', "✅ Producto marcado como {$status}");
    }

    /**
     * Delete product with ownership verification
     */
    public function delete(Product $product): void
    {
        // Security: Verify ownership
        if ($product->tenant_id !== $this->tenant->id) {
            session()->flash('error', 'No tienes permiso para eliminar este producto.');

            return;
        }

        // Delete associated media first
        $product->clearMediaCollection('images');

        // Soft delete the product
        $product->delete();

        session()->flash('toast', '🗑️ Producto eliminado correctamente');
    }

    /**
     * Reset search and pagination
     */
    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    // ═══════════════════════════════════════════════════════════════════════
    // RENDER
    // ═══════════════════════════════════════════════════════════════════════

    public function render()
    {
        return view('livewire.tenant.products.index')
            ->title('Mis Productos | '.$this->tenant->name);
    }
}
