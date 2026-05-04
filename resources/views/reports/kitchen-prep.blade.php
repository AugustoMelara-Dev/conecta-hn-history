<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cocina - {{ $tenant->name }}</title>
    <style>
        * { font-family: Arial, sans-serif; box-sizing: border-box; }
        body { margin: 15px; color: #333; }
        .header { text-align: center; margin-bottom: 15px; border-bottom: 3px solid #333; padding-bottom: 10px; }
        .header h1 { margin: 0; font-size: 28px; }
        .header p { margin: 5px 0; font-size: 14px; }
        .order-card { border: 2px solid #333; margin-bottom: 20px; page-break-inside: avoid; }
        .order-header { background: #333; color: white; padding: 10px 15px; display: flex; justify-content: space-between; gap: 12px; }
        .order-number { font-size: 20px; font-weight: bold; }
        .order-time { font-size: 14px; text-align: right; }
        .order-body { padding: 15px; }
        .customer-info { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px dashed #ccc; }
        .customer-info strong { font-size: 16px; }
        .customer-meta { min-width: 0; }
        .items-list { list-style: none; padding: 0; margin: 0; }
        .items-list li { padding: 8px 0; border-bottom: 1px solid #eee; font-size: 16px; }
        .items-list li:last-child { border-bottom: none; }
        .quantity { display: inline-block; width: 40px; font-weight: bold; font-size: 20px; }
        .product-name { font-size: 16px; }
        .item-notes { color: #666; display: block; margin-left: 45px; }
        .notes { margin-top: 15px; padding: 10px; background: #fff3cd; border: 1px solid #ffc107; }
        .check-box { flex: 0 0 auto; width: 25px; height: 25px; border: 2px solid #333; }
        .footer { text-align: center; font-size: 10px; color: #999; margin-top: 20px; }
        @media (max-width: 640px) {
            body { margin: 10px; }
            .header h1 { font-size: 22px; }
            .order-header { flex-direction: column; }
            .order-time { text-align: left; }
            .item-notes { margin-left: 0; margin-top: 4px; }
        }
        @media print {
            body { margin: 0; }
            .order-card { page-break-inside: avoid; }
        }
    </style>
</head>
<body>
    <header class="header">
        <h1>Lista de Preparacion</h1>
        <p>{{ $tenant->name }} &bull; {{ $generated_at->format('d/m/Y H:i') }}</p>
        <p><strong>{{ $orders->count() }}</strong> ordenes pendientes</p>
    </header>

    <main aria-label="Ordenes pendientes para cocina">
        @forelse($orders as $order)
            <article class="order-card" aria-labelledby="order-{{ $order->id ?? $order->order_number }}-title">
                <header class="order-header">
                    <span id="order-{{ $order->id ?? $order->order_number }}-title" class="order-number">Orden #{{ $order->order_number }}</span>
                    <span class="order-time">{{ $order->created_at->format('H:i') }} ({{ $order->created_at->diffForHumans() }})</span>
                </header>
                <div class="order-body">
                    <div class="customer-info">
                        <div class="customer-meta">
                            <strong>{{ $order->customer_name ?? 'Cliente' }}</strong>
                            @if($order->customer_phone)
                                <span> &bull; {{ $order->customer_phone }}</span>
                            @endif
                        </div>
                        <span class="check-box" role="img" aria-label="Casilla para marcar orden preparada"></span>
                    </div>

                    <ul class="items-list" aria-label="Productos de la orden {{ $order->order_number }}">
                        @foreach($order->items as $item)
                            <li>
                                <span class="quantity" aria-label="Cantidad">{{ $item->quantity }}x</span>
                                <span class="product-name">{{ $item->product_name ?? $item->product?->name ?? 'Producto' }}</span>
                                @if($item->notes)
                                    <small class="item-notes">Nota: {{ $item->notes }}</small>
                                @endif
                            </li>
                        @endforeach
                    </ul>

                    @if($order->notes)
                        <div class="notes" role="note">
                            <strong>Notas:</strong> {{ $order->notes }}
                        </div>
                    @endif
                </div>
            </article>
        @empty
            <div style="text-align: center; padding: 50px;" role="status">
                <p style="font-size: 24px;">No hay ordenes pendientes</p>
            </div>
        @endforelse
    </main>

    <footer class="footer">
        <p>Vito Business OS &bull; Impreso: {{ $generated_at->format('d/m/Y H:i:s') }}</p>
    </footer>
</body>
</html>
