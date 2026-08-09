<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Domain\Shared\Services\TenantContext;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class OrderReceiptController extends Controller
{
    public function __construct(private TenantContext $tenantContext) {}

    public function print(Request $request, int $order): Response
    {
        return $this->document($request, $order, false);
    }

    public function receipt(Request $request, int $order): Response
    {
        return $this->document($request, $order, true);
    }

    private function document(Request $request, int $orderId, bool $download): Response
    {
        abort_unless($request->user() !== null, 401);
        $tenantId = trim((string) $this->tenantContext->id());
        abort_if($tenantId === '', 404);

        $order = Order::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->with('items')
            ->findOrFail($orderId);

        $pdf = Pdf::loadView('pdf.order-receipt', ['order' => $order])->setPaper('a4');
        $filename = 'pedido-'.preg_replace('/[^A-Za-z0-9_-]/', '-', (string) $order->order_number).'.pdf';

        return $download ? $pdf->download($filename) : $pdf->stream($filename);
    }
}
