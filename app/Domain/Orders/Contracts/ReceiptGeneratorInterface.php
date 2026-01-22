<?php

declare(strict_types=1);

namespace App\Domain\Orders\Contracts;

use App\Models\Order;

/**
 * ReceiptGeneratorInterface - Domain Port for Receipt Generation
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  STRATEGY PATTERN: Allows swapping PDF engines (DomPDF, Snappy, etc.)   ║
 * ║  PROTOCOL NEUTRAL: Returns raw bytes, not HTTP Response.                ║
 * ║  REUSABLE: Works for HTTP downloads, Mailable attachments, S3 storage.  ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
interface ReceiptGeneratorInterface
{
    /**
     * Generate receipt PDF for an order.
     *
     * @param  Order  $order  Order with items relation loaded
     * @return string Raw binary PDF content
     *
     * @throws \App\Domain\Orders\Exceptions\PdfGenerationException On render failure
     */
    public function generate(Order $order): string;
}
