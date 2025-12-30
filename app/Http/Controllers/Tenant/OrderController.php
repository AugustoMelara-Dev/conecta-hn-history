<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Tenant;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * OrderController - Order Management for Tenant Dashboard
 *
 * SECURITY: Zero-Trust Tenant Isolation.
 * All queries scoped to $request->user()->tenant->orders()
 */
final class OrderController extends Controller
{
    // ═══════════════════════════════════════════════════════════════════════
    // INDEX - Orders Kanban/List View
    // ═══════════════════════════════════════════════════════════════════════
    public function index(Request $request): InertiaResponse
    {
        $tenant = $this->getTenant($request);

        // Fetch today's orders grouped by status
        $orders = $tenant->orders()
            ->whereDate('created_at', today())
            ->with('items.product')
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('status');

        return Inertia::render('Tenant/Orders/Index', [
            'orders' => [
                'pending' => $this->transformOrders($orders->get('pending', collect())),
                'processing' => $this->transformOrders($orders->get('processing', collect())),
                'completed' => $this->transformOrders($orders->get('completed', collect())),
                'cancelled' => $this->transformOrders($orders->get('cancelled', collect())),
            ],
            'tenant' => $tenant->only('id', 'name', 'slug', 'is_pro'),
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // UPDATE STATUS - Kanban Card Movement
    // ═══════════════════════════════════════════════════════════════════════
    public function updateStatus(Request $request, int $orderId): RedirectResponse
    {
        $tenant = $this->getTenant($request);
        $order = $tenant->orders()->findOrFail($orderId);

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:pending,processing,completed,cancelled'],
        ]);

        $order->update(['status' => $validated['status']]);

        return back()->with('success', 'Estado actualizado.');
    }

    // ═══════════════════════════════════════════════════════════════════════
    // PRINT TICKET - Thermal Printer View
    // ═══════════════════════════════════════════════════════════════════════
    public function printTicket(Request $request, int $orderId)
    {
        $tenant = $this->getTenant($request);
        $order = $tenant->orders()->with('items.product')->findOrFail($orderId);

        // Return a simple HTML view optimized for thermal printers
        return view('orders.ticket', [
            'order' => $order,
            'tenant' => $tenant,
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // DOWNLOAD RECEIPT - Order PDF
    // ═══════════════════════════════════════════════════════════════════════
    public function downloadReceipt(Request $request, int $orderId): Response
    {
        $tenant = $this->getTenant($request);
        $order = $tenant->orders()->with('items.product')->findOrFail($orderId);

        $pdf = Pdf::loadView('orders.receipt', [
            'order' => $order,
            'tenant' => $tenant,
        ]);

        return $pdf->stream("recibo_orden_{$order->id}.pdf");
    }

    // ═══════════════════════════════════════════════════════════════════════
    // PRIVATE HELPERS
    // ═══════════════════════════════════════════════════════════════════════

    private function getTenant(Request $request): Tenant
    {
        $tenant = $request->user()?->tenant;
        abort_unless($tenant, 403, 'Tenant not found.');
        return $tenant;
    }

    /**
     * Transform orders collection for frontend.
     */
    private function transformOrders($orders): array
    {
        return $orders->map(fn (Order $order) => [
            'id' => $order->id,
            'customer_name' => $order->customer_name,
            'total' => (float) $order->total,
            'status' => $order->status,
            'items_count' => $order->items->count(),
            'created_at' => $order->created_at->toIso8601String(),
            'items' => $order->items->map(fn ($item) => [
                'id' => $item->id,
                'product_name' => $item->product?->name ?? $item->name,
                'quantity' => $item->quantity,
                'price' => (float) $item->price,
            ])->toArray(),
        ])->values()->toArray();
    }
}
