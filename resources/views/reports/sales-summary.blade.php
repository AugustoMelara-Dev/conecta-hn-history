<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Ventas - {{ $tenant->name }}</title>
    <style>
        * { font-family: Arial, sans-serif; font-size: 12px; }
        body { margin: 20px; color: #333; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 15px; }
        .header h1 { margin: 0; font-size: 24px; }
        .header p { margin: 5px 0; color: #666; }
        .summary { display: flex; margin-bottom: 30px; }
        .summary-box { flex: 1; padding: 15px; background: #f5f5f5; margin-right: 10px; text-align: center; }
        .summary-box:last-child { margin-right: 0; }
        .summary-box h3 { margin: 0; font-size: 14px; color: #666; }
        .summary-box p { margin: 5px 0 0; font-size: 24px; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #333; color: white; }
        tr:nth-child(even) { background: #f9f9f9; }
        .status-delivered { color: #28a745; }
        .status-cancelled { color: #dc3545; }
        .status-pending { color: #ffc107; }
        .footer { margin-top: 30px; text-align: center; font-size: 10px; color: #999; }
        .total-row { font-weight: bold; background: #e9e9e9; }
        @media print { body { margin: 0; } }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $tenant->name }}</h1>
        <p>Reporte de Ventas</p>
        @if($start_date || $end_date)
            <p>
                Período: {{ $start_date ?? 'Inicio' }} - {{ $end_date ?? 'Actualidad' }}
            </p>
        @endif
        <p>Generado: {{ $generated_at->format('d/m/Y H:i:s') }}</p>
    </div>

    <table style="width: auto; border: none; margin-bottom: 30px;">
        <tr>
            <td style="border: none; padding: 10px 20px 10px 0;">
                <strong>Total Órdenes:</strong><br>
                <span style="font-size: 20px;">{{ number_format($summary['total_orders']) }}</span>
            </td>
            <td style="border: none; padding: 10px 20px;">
                <strong>Ingresos Totales:</strong><br>
                <span style="font-size: 20px;">L. {{ number_format($summary['total_revenue'], 2) }}</span>
            </td>
            <td style="border: none; padding: 10px 20px;">
                <strong>Ticket Promedio:</strong><br>
                <span style="font-size: 20px;">L. {{ number_format($summary['avg_order_value'], 2) }}</span>
            </td>
        </tr>
    </table>

    <h3>Detalle de Órdenes</h3>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Fecha</th>
                <th>Cliente</th>
                <th>Estado</th>
                <th>Pago</th>
                <th style="text-align: right;">Total</th>
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
                    <td style="text-align: right;">L. {{ number_format($order->total, 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" style="text-align: center;">No hay órdenes en este período.</td>
                </tr>
            @endforelse
            @if($orders->count() > 0)
                <tr class="total-row">
                    <td colspan="5" style="text-align: right;">TOTAL:</td>
                    <td style="text-align: right;">L. {{ number_format($grandTotal, 2) }}</td>
                </tr>
            @endif
        </tbody>
    </table>

    <div class="footer">
        <p>Generado por Vito Business OS &bull; {{ $generated_at->format('d/m/Y H:i:s') }}</p>
    </div>
</body>
</html>
