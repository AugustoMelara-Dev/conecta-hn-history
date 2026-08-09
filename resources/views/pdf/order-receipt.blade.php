<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Pedido {{ $order->order_number }}</title>
    <style>
        body { color: #18181b; font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        h1 { font-size: 20px; margin: 0 0 4px; }
        .muted { color: #71717a; }
        table { border-collapse: collapse; margin-top: 20px; width: 100%; }
        th, td { border-bottom: 1px solid #e4e4e7; padding: 8px 4px; text-align: left; }
        th:last-child, td:last-child { text-align: right; }
        .totals { margin-left: auto; margin-top: 18px; width: 45%; }
        .totals td { border: 0; }
        .total { font-size: 15px; font-weight: bold; }
    </style>
</head>
<body>
    <h1>Pedido {{ $order->order_number }}</h1>
    <div class="muted">Emitido {{ now()->timezone('America/Tegucigalpa')->format('d/m/Y H:i') }}</div>
    <p><strong>Cliente:</strong> {{ $order->customer_name }}<br>
       <strong>Estado:</strong> {{ $order->status }}<br>
       <strong>Pago:</strong> {{ $order->payment_status }}</p>
    <table>
        <thead><tr><th>Producto</th><th>Cantidad</th><th>Total</th></tr></thead>
        <tbody>
        @foreach ($order->items as $item)
            <tr><td>{{ $item->product_name }}</td><td>{{ $item->quantity }}</td><td>{{ $order->currency }} {{ number_format((float) $item->line_total, 2) }}</td></tr>
        @endforeach
        </tbody>
    </table>
    <table class="totals">
        <tr><td>Subtotal</td><td>{{ $order->currency }} {{ number_format((float) $order->subtotal, 2) }}</td></tr>
        <tr><td>Descuento</td><td>-{{ $order->currency }} {{ number_format((float) $order->discount, 2) }}</td></tr>
        <tr><td>Envío</td><td>{{ $order->currency }} {{ number_format((float) $order->delivery_fee, 2) }}</td></tr>
        <tr class="total"><td>Total</td><td>{{ $order->currency }} {{ number_format((float) $order->total, 2) }}</td></tr>
    </table>
</body>
</html>
