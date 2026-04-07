<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Domain\Orders\Contracts\ReceiptGeneratorInterface;
use App\Domain\Shared\Contracts\TenantContext;
use App\Domain\Tenant\Contracts\OrderRepository;
use App\Http\Controllers\Controller;
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
        private readonly ReceiptGeneratorInterface $receiptGenerator,
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
            'tenant' => $this->tenantContext->getTenantData(['id', 'name', 'slug', 'plan_type', 'is_paid_plan']),
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // UPDATE STATUS - Order Lifecycle
    // ═══════════════════════════════════════════════════════════════════════
    public function updateStatus(Request $request, int $orderId): RedirectResponse
    {
        return redirect()
            ->to(route('filament.app.resources.orders.index', ['tenant' => $this->tenantSlug()], false))
            ->with('error', 'La gestion operativa de pedidos ya se administra desde el panel oficial en /app.');
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

        /** @var view-string $view */
        $view = 'orders.ticket';

        return view($view, [
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

        $pdfContent = $this->receiptGenerator->generate($order);
        $filename = sprintf('recibo-%d.pdf', $orderId);

        return response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('inline; filename="%s"', $filename),
        ]);
    }

    private function tenantSlug(): string
    {
        return (string) ($this->tenantContext->getTenantData(['slug'])['slug'] ?? '');
    }
}
