<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Application\Actions\Orders\GenerateOrderPdfAction;
use App\Domain\Shared\Contracts\TenantContext;
use App\Domain\Tenant\Contracts\OrderRepository;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\UpdateOrderStatusRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * OrderController - Hexagonal Compliant (DIP Remediation)
 *
 * ARCHITECTURAL COMPLIANCE:
 * - Zero App\Models imports.
 * - TenantContext for tenant resolution (no $request->user()->tenant).
 * - Single Responsibility: HTTP translation only.
 *
 * SECURITY: Zero-Trust Tenant Isolation via TenantContext.
 */
final class OrderController extends Controller
{
    public function __construct(
        private readonly OrderRepository $orderRepository,
        private readonly GenerateOrderPdfAction $pdfAction,
        private readonly TenantContext $tenantContext
    ) {}

    // ═══════════════════════════════════════════════════════════════════════
    // INDEX - Orders List View (Paginated)
    // ═══════════════════════════════════════════════════════════════════════
    public function index(Request $request): InertiaResponse
    {
        $tenantId = $this->tenantContext->getTenantId();

        // DELEGATED: Query logic lives in Repository.
        $orders = $this->orderRepository->getDashboardList(
            tenantId: $tenantId,
            filters: $request->only(['status', 'date', 'search']),
            perPage: 15
        );

        return Inertia::render('Tenant/Orders/Index', [
            'orders' => $orders,
            'filters' => $request->only(['status', 'date', 'search']),
            'tenant' => $this->tenantContext->getTenantData(['id', 'name', 'slug', 'is_pro']),
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // UPDATE STATUS - Order Lifecycle
    // ═══════════════════════════════════════════════════════════════════════
    public function updateStatus(UpdateOrderStatusRequest $request, int $orderId): RedirectResponse
    {
        $tenantId = $this->tenantContext->getTenantId();
        $validated = $request->validated();

        // DELEGATED: Update logic lives in Repository
        $updated = $this->orderRepository->updateStatus(
            tenantId: $tenantId,
            orderId: $orderId,
            status: $validated['status']
        );

        if (! $updated) {
            abort(404, 'Order not found.');
        }

        return back()->with('success', 'Estado actualizado.');
    }

    // ═══════════════════════════════════════════════════════════════════════
    // PRINT TICKET - Thermal Printer View
    // ═══════════════════════════════════════════════════════════════════════
    public function printTicket(Request $request, int $orderId)
    {
        $tenantId = $this->tenantContext->getTenantId();

        // DELEGATED: Query via Repository
        $order = $this->orderRepository->findForDetails($tenantId, $orderId);

        if (! $order) {
            abort(404, 'Order not found.');
        }

        return view('orders.ticket', [
            'order' => $order,
            'tenant' => $this->tenantContext->getTenantData(),
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // DOWNLOAD RECEIPT - Order PDF
    // ═══════════════════════════════════════════════════════════════════════
    public function downloadReceipt(Request $request, int $orderId): Response
    {
        $tenantId = $this->tenantContext->getTenantId();

        // DELEGATED: Query via Repository
        $order = $this->orderRepository->findForDetails($tenantId, $orderId);

        if (! $order) {
            abort(404, 'Order not found.');
        }

        // DELEGATED: PDF generation via Action
        // Note: Action may need refactoring to accept ID or DTO instead of Model
        return $this->pdfAction->execute($order, $this->tenantContext->getTenantData());
    }
}
