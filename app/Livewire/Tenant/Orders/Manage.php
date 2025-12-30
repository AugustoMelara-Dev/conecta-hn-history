<?php

namespace App\Livewire\Tenant\Orders;

use App\Models\Order;
use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Orders Management - Tiered UI (Kanban PRO / List FREE)
 *
 * Features:
 * - PRO: Kanban board with drag-like workflow
 * - FREE: Standard data table
 * - POS Lite: Quick order creation modal (both plans)
 */
#[Layout('layouts.app')]
class Manage extends Component
{
    public Tenant $tenant;

    // ═══════════════════════════════════════════════════════════════════════
    // MODAL STATE
    // ═══════════════════════════════════════════════════════════════════════

    public bool $isCreating = false;

    public string $customerName = '';

    public string $customerContact = '';

    public string $orderNotes = '';

    public string $productSearch = '';

    public array $cart = [];

    // ═══════════════════════════════════════════════════════════════════════
    // LIFECYCLE
    // ═══════════════════════════════════════════════════════════════════════

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

    #[Computed]
    public function isPro(): bool
    {
        return (bool) $this->tenant->is_pro;
    }

    /**
     * All orders for today (eager loaded)
     */
    #[Computed]
    public function orders()
    {
        return Order::with('items')
            ->where('tenant_id', $this->tenant->id)
            ->today()
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Orders grouped by status for Kanban (PRO only)
     */
    #[Computed]
    public function kanbanData(): array
    {
        $orders = $this->orders;

        return [
            'pending' => $orders->where('status', 'pending'),
            'processing' => $orders->whereIn('status', ['confirmed', 'processing', 'preparing']),
            'completed' => $orders->whereIn('status', ['completed', 'delivered']),
            'cancelled' => $orders->where('status', 'cancelled'),
        ];
    }

    /**
     * Products for quick search in POS modal
     */
    #[Computed]
    public function availableProducts()
    {
        // Use Model Helper to avoid Architecture Violation
        return Product::searchForPos($this->tenant->id, $this->productSearch);
    }

    /**
     * Cart total
     */
    #[Computed]
    public function cartTotal(): float
    {
        return collect($this->cart)->sum(fn ($item) => $item['price'] * $item['qty']);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // POS ACTIONS
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Add product to cart
     */
    public function addToCart(int $productId): void
    {
        $product = Product::findInTenant($productId, $this->tenant->id);

        if (! $product || $product->tenant_id !== $this->tenant->id) {
            return;
        }

        // Check if already in cart
        $index = $this->findCartIndex($productId);

        if ($index !== false) {
            $this->cart[$index]['qty']++;
        } else {
            $this->cart[] = [
                'product_id' => $product->id,
                'name' => $product->name,
                'price' => (float) $product->price,
                'qty' => 1,
            ];
        }

        $this->productSearch = '';
    }

    /**
     * Increment cart item quantity
     */
    public function incrementItem(int $productId): void
    {
        $index = $this->findCartIndex($productId);
        if ($index !== false) {
            $this->cart[$index]['qty']++;
        }
    }

    /**
     * Decrement cart item quantity
     */
    public function decrementItem(int $productId): void
    {
        $index = $this->findCartIndex($productId);
        if ($index !== false) {
            if ($this->cart[$index]['qty'] > 1) {
                $this->cart[$index]['qty']--;
            } else {
                unset($this->cart[$index]);
                $this->cart = array_values($this->cart);
            }
        }
    }

    /**
     * Remove item from cart
     */
    public function removeFromCart(int $productId): void
    {
        $index = $this->findCartIndex($productId);
        if ($index !== false) {
            unset($this->cart[$index]);
            $this->cart = array_values($this->cart);
        }
    }

    /**
     * Find cart index by product ID
     */
    private function findCartIndex(int $productId): int|false
    {
        foreach ($this->cart as $index => $item) {
            if ($item['product_id'] === $productId) {
                return $index;
            }
        }

        return false;
    }

    /**
     * Create manual order (POS Lite)
     */
    public function createManualOrder(): void
    {
        if (empty($this->cart)) {
            session()->flash('error', 'El carrito está vacío');

            return;
        }

        DB::transaction(function () {
            // Create order header
            $order = Order::create([
                'tenant_id' => $this->tenant->id,
                'status' => 'pending',
                'customer_name' => $this->customerName ?: null,
                'customer_phone' => $this->customerContact ?: null,
                'customer_notes' => $this->orderNotes ?: null,
                'subtotal' => $this->cartTotal,
                'total' => $this->cartTotal,
            ]);

            // Create order items with price snapshots
            foreach ($this->cart as $item) {
                $order->items()->create([
                    'product_id' => $item['product_id'],
                    'product_name' => $item['name'],
                    'price' => $item['price'],
                    'quantity' => $item['qty'],
                ]);
            }
        });

        // Reset modal state
        $this->resetModal();

        session()->flash('toast', '✅ Orden #'.($this->orders->count() + 1).' registrada');
    }

    /**
     * Reset modal state
     */
    public function resetModal(): void
    {
        $this->isCreating = false;
        $this->customerName = '';
        $this->customerContact = '';
        $this->orderNotes = '';
        $this->productSearch = '';
        $this->cart = [];
    }

    // ═══════════════════════════════════════════════════════════════════════
    // ORDER WORKFLOW ACTIONS
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Update order status (with ownership verification)
     */
    public function updateStatus(int $orderId, string $status): void
    {
        $allowedStatuses = ['pending', 'confirmed', 'processing', 'preparing', 'completed', 'delivered', 'cancelled'];

        if (! in_array($status, $allowedStatuses)) {
            return;
        }

        $order = Order::where('tenant_id', $this->tenant->id)
            ->where('id', $orderId)
            ->first();

        if (! $order) {
            return;
        }

        $order->update(['status' => $status]);

        session()->flash('toast', '✅ Estado actualizado');
    }

    /**
     * Advance order to next status
     */
    public function advanceOrder(int $orderId): void
    {
        $order = Order::where('tenant_id', $this->tenant->id)
            ->where('id', $orderId)
            ->first();

        if (! $order) {
            return;
        }

        $nextStatus = match ($order->status) {
            'pending' => 'confirmed',
            'confirmed' => 'preparing',
            'preparing' => 'completed',
            default => null,
        };

        if ($nextStatus) {
            $order->update(['status' => $nextStatus]);
            session()->flash('toast', '✅ Orden avanzada');
        }
    }

    // ═══════════════════════════════════════════════════════════════════════
    // RENDER
    // ═══════════════════════════════════════════════════════════════════════

    public function render()
    {
        return view('livewire.tenant.orders.manage')
            ->title('Gestión de Pedidos | '.$this->tenant->name);
    }
}
