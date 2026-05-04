<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reporte de Ventas - {{ $tenant->name }}</title>
    <style>
        * { font-family: Arial, sans-serif; font-size: 12px; box-sizing: border-box; }
        body { margin: 20px; color: #333; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 15px; }
        .header h1 { margin: 0; font-size: 24px; }
        .header p { margin: 5px 0; color: #666; }
        .summary { display: flex; gap: 10px; margin-bottom: 30px; }
        .summary-box { flex: 1; padding: 15px; background: #f5f5f5; text-align: center; }
        .summary-box h2 { margin: 0; font-size: 14px; color: #666; }
        .summary-box p { margin: 5px 0 0; font-size: 24px; font-weight: bold; }
        .table-help { color: #555; margin: 0 0 8px; }
        .table-scroll { max-width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; }
        table { width: 100%; min-width: 680px; border-collapse: collapse; margin-top: 12px; }
        caption { text-align: left; font-weight: bold; margin-bottom: 8px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #333; color: white; }
        tr:nth-child(even) { background: #f9f9f9; }
        .status-delivered { color: #28a745; }
        .status-cancelled { color: #dc3545; }
        .status-pending { color: #856404; }
        .footer { margin-top: 30px; text-align: center; font-size: 10px; color: #999; }
        .total-row { font-weight: bold; background: #e9e9e9; }
        .text-right { text-align: right; }
        @media (max-width: 640px) {
            body { margin: 12px; }
            .header h1 { font-size: 20px; }
            .summary { flex-direction: column; }
            .summary-box p { font-size: 20px; }
        }
        @media print {
            body { margin: 0; }
            .no-print { display: none; }
            .table-scroll { overflow: visible; }
            table { min-width: 0; }
        }
    </style>
</head>
<body>
    <header class="header">
        <h1>{{ $tenant->name }}</h1>
        <p>Reporte de Ventas</p>
        @if($start_date || $end_date)
            <p>
                Periodo: {{ $start_date ?? 'Inicio' }} - {{ $end_date ?? 'Actualidad' }}
            </p>
        @endif
        <p>Generado: {{ $generated_at->format('d/m/Y H:i:s') }}</p>
    </header>

    <main>
        <section class="summary" aria-label="Resumen del reporte de ventas">
            <div class="summary-box">
                <h2>Total Ordenes</h2>
                <p>{{ number_format($summary['total_orders']) }}</p>
            </div>
            <div class="summary-box">
                <h2>Ingresos Totales</h2>
                <p>L. {{ number_format($summary['total_revenue'], 2) }}</p>
            </div>
            <div class="summary-box">
                <h2>Ticket Promedio</h2>
                <p>L. {{ number_format($summary['avg_order_value'], 2) }}</p>
            </div>
        </section>

        <p id="sales-table-help" class="table-help no-print">En pantallas pequenas, desliza horizontalmente la tabla para ver fecha, estado, pago y total.</p>
        <div class="table-scroll" role="region" aria-labelledby="sales-table-title" aria-describedby="sales-table-help" tabindex="0">
            <table>
                <caption id="sales-table-title">Detalle de Ordenes</caption>
                <thead>
                    <tr>
                        <th scope="col">#</th>
                        <th scope="col">Fecha</th>
                        <th scope="col">Cliente</th>
                        <th scope="col">Estado</th>
                        <th scope="col">Pago</th>
                        <th scope="col" class="text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @php $grandTotal = 0; @endphp
                    @forelse($orders as $order)
                        @php $grandTotal += $order->total; @endphp
                        <tr>
                            <td>{{ $order->order_number }}</td>
                            <td>{{ $order->created_at->format('d/m/Y H:i') }}</td>
                            <td>{{ $order->customer_name ?? 'N/A' }}</td>
                            <td class="status-{{ $order->status }}">
                                {{ ucfirst($order->status) }}
                            </td>
                            <td>{{ $order->payment_method?->value ?? $order->payment_method ?? 'N/A' }}</td>
                            <td class="text-right">L. {{ number_format($order->total, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="text-align: center;">No hay ordenes en este periodo.</td>
                        </tr>
                    @endforelse
                    @if($orders->count() > 0)
                        <tr class="total-row">
                            <td colspan="5" class="text-right">TOTAL:</td>
                            <td class="text-right">L. {{ number_format($grandTotal, 2) }}</td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </main>

    <footer class="footer">
        <p>Generado por Vito Business OS &bull; {{ $generated_at->format('d/m/Y H:i:s') }}</p>
    </footer>
</body>
</html>
