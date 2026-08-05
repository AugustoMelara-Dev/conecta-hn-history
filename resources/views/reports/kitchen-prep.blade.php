<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Cocina - {{ $tenant->name }}</title>
    <style>
        * { font-family: Arial, sans-serif; }
        body { margin: 15px; color: #333; }
        .header { text-align: center; margin-bottom: 15px; border-bottom: 3px solid #333; padding-bottom: 10px; }
        .header h1 { margin: 0; font-size: 28px; }
        .header p { margin: 5px 0; font-size: 14px; }
        .order-card { border: 2px solid #333; margin-bottom: 20px; page-break-inside: avoid; }
        .order-header { background: #333; color: white; padding: 10px 15px; display: flex; justify-content: space-between; }
        .order-number { font-size: 20px; font-weight: bold; }
        .order-time { font-size: 14px; }
        .order-body { padding: 15px; }
        .customer-info { margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px dashed #ccc; }
        .customer-info strong { font-size: 16px; }
        .items-list { list-style: none; padding: 0; margin: 0; }
        .items-list li { padding: 8px 0; border-bottom: 1px solid #eee; font-size: 16px; }
        .items-list li:last-child { border-bottom: none; }
        .quantity { display: inline-block; width: 40px; font-weight: bold; font-size: 20px; }
        .product-name { font-size: 16px; }
        .notes { margin-top: 15px; padding: 10px; background: #fff3cd; border: 1px solid #ffc107; }
        .check-box { float: right; width: 25px; height: 25px; border: 2px solid #333; }
        .footer { text-align: center; font-size: 10px; color: #999; margin-top: 20px; }
        @media print { 
            body { margin: 0; }
            .order-card { page-break-inside: avoid; }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🍳 Lista de Preparación</h1>
        <p>{{ $tenant->name }} &bull; {{ $generated_at->format('d/m/Y H:i') }}</p>
        <p><strong>{{ $orders->count() }}</strong> órdenes pendientes</p>
    </div>

    @forelse($orders as $order)
        <div class="order-card">
            <div class="order-header">
                <span class="order-number">#{{ $order->order_number }}</span>
                <span class="order-time">{{ $order->created_at->format('H:i') }} ({{ $order->created_at->diffForHumans() }})</span>
            </div>
            <div class="order-body">
                <div class="customer-info">
                    <strong>{{ $order->customer_name ?? 'Cliente' }}</strong>
                    @if($order->customer_phone)
                        <span> &bull; {{ $order->customer_phone }}</span>
                    @endif
                    <span class="check-box"></span>
                </div>

                <ul class="items-list">
                    @foreach($order->items as $item)
                        <li>
                            <span class="quantity">{{ $item->quantity }}x</span>
                            <span class="product-name">{{ $item->product_name ?? $item->product?->name ?? 'Producto' }}</span>
                            @if($item->notes)
                                <br><small style="color: #666; margin-left: 45px;">📝 {{ $item->notes }}</small>
                            @endif
                        </li>
                    @endforeach
                </ul>

                @if($order->notes)
                    <div class="notes">
                        <strong>Notas:</strong> {{ $order->notes }}
                    </div>
                @endif
            </div>
        </div>
    @empty
        <div style="text-align: center; padding: 50px;">
            <p style="font-size: 24px;">✅ No hay órdenes pendientes</p>
        </div>
    @endforelse

    <div class="footer">
        <p>Dovra AI &bull; Impreso: {{ $generated_at->format('d/m/Y H:i:s') }}</p>
    </div>
</body>
</html>
