<?php

declare(strict_types=1);

namespace App\Infrastructure\Reporting;

use App\Domain\Orders\Contracts\ReceiptGeneratorInterface;
use App\Domain\Orders\Exceptions\PdfGenerationException;
use App\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;

/**
 * DomPdfReceiptGenerator - Infrastructure Adapter using DomPDF
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  ENCAPSULATES: DomPDF configuration and rendering.                      ║
 * ║  SWAPPABLE: Can be replaced with Snappy, Browsershot, etc.              ║
 * ║  NO HTTP: Returns raw bytes only - no Response objects.                 ║
 * ║  NO SIDE EFFECTS: Does not mutate input - expects hydrated data.        ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * CONTRACT: Caller is responsible for providing Order with relations loaded.
 */
final class DomPdfReceiptGenerator implements ReceiptGeneratorInterface
{
    private const PAPER_SIZE = 'A4';

    private const PAPER_ORIENTATION = 'portrait';

    /**
     * Generate PDF receipt from pre-hydrated Order.
     *
     * @param  Order  $order  Must have items and tenant relations already loaded
     * @return string Raw binary PDF content
     *
     * @throws PdfGenerationException On render failure
     */
    public function generate(object $order): string
    {
        /** @var \App\Models\Order $order */
        try {
            // NO loadMissing() - caller must provide hydrated data
            // This ensures immutability and no side effects

            // Create PDF from Blade view
            $pdf = Pdf::loadView('orders.receipt', [
                'order' => $order,
                'tenant' => $order->tenant,
            ]);

            // Configure paper size and orientation
            $pdf->setPaper(self::PAPER_SIZE, self::PAPER_ORIENTATION);

            // Extract raw binary content (NOT stream/download)
            $content = $pdf->output();

            Log::info('PDF receipt generated', [
                'order_id' => $order->id,
                'tenant_id' => $order->tenant_id,
                'size_bytes' => strlen($content),
            ]);

            return $content;

        } catch (\Throwable $e) {
            Log::error('PDF generation failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            throw PdfGenerationException::forOrder($order->id, $e);
        }
    }
}
