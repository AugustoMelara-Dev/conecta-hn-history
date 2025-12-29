<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * SalesExport - Synchronous Streaming Excel Export for Accounting
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  SYNCHRONOUS: Direct download via Excel::download()                     ║
 * ║  MEMORY SAFE: Uses chunked queries for 100k+ orders                     ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * ARCHITECTURE:
 * - FromQuery returns a lazy Builder (not a Collection)
 * - WithChunkReading processes 500 records at a time
 * - Combined: exports 100k+ rows with constant ~50MB memory usage
 *
 * NOTE: ShouldQueue was intentionally REMOVED because Excel::download()
 * requires an immediate binary stream (sync). The chunked architecture
 * ensures memory safety without async processing.
 */
class SalesExport implements FromQuery, WithChunkReading, WithHeadings, WithMapping, WithStyles
{
    use Exportable;

    /**
     * Constructor with dependency injection.
     */
    public function __construct(
        private readonly int $tenantId,
        private readonly ?string $startDate = null,
        private readonly ?string $endDate = null,
        private readonly ?string $status = null
    ) {}

    /**
     * Query builder for streaming data.
     *
     * MEMORY OPTIMIZATION: Returns a query, not a collection.
     * Laravel Excel will chunk this automatically.
     */
    public function query(): Builder
    {
        $query = Order::query()
            ->where('tenant_id', $this->tenantId)
            ->with(['items.product:id,name,price'])
            ->orderBy('created_at', 'desc');

        // Apply date filters
        if ($this->startDate) {
            $query->whereDate('created_at', '>=', $this->startDate);
        }

        if ($this->endDate) {
            $query->whereDate('created_at', '<=', $this->endDate);
        }

        // Apply status filter
        if ($this->status) {
            $query->where('status', $this->status);
        }

        return $query;
    }

    /**
     * Chunk size for memory-efficient processing.
     */
    public function chunkSize(): int
    {
        return 500;
    }

    /**
     * Column headings.
     */
    public function headings(): array
    {
        return [
            'Número de Orden',
            'Fecha',
            'Hora',
            'Cliente',
            'Teléfono',
            'Estado',
            'Método de Pago',
            'Productos',
            'Subtotal',
            'Impuestos',
            'Descuento',
            'Total',
        ];
    }

    /**
     * Map each order to a row.
     */
    public function map($order): array
    {
        $products = $order->items->map(function ($item) {
            return "{$item->quantity}x {$item->product_name}";
        })->join(', ');

        return [
            $order->order_number,
            $order->created_at->format('Y-m-d'),
            $order->created_at->format('H:i:s'),
            $order->customer_name ?? 'N/A',
            $order->customer_phone ?? 'N/A',
            $this->translateStatus($order->status),
            $order->payment_method?->value ?? $order->payment_method ?? 'N/A',
            $products ?: 'Sin productos',
            number_format((float) $order->subtotal, 2),
            number_format((float) $order->tax_amount, 2),
            number_format((float) $order->discount_amount, 2),
            number_format((float) $order->total, 2),
        ];
    }

    /**
     * Style the worksheet.
     */
    public function styles(Worksheet $sheet): array
    {
        return [
            // Header row bold
            1 => ['font' => ['bold' => true]],
        ];
    }

    /**
     * Translate status to Spanish.
     */
    private function translateStatus(string $status): string
    {
        return match ($status) {
            Order::STATUS_PENDING => 'Pendiente',
            Order::STATUS_CONFIRMED => 'Confirmado',
            Order::STATUS_PROCESSING => 'En Preparación',
            Order::STATUS_READY => 'Listo',
            Order::STATUS_DELIVERED => 'Entregado',
            Order::STATUS_CANCELLED => 'Cancelado',
            default => ucfirst($status),
        };
    }
}
