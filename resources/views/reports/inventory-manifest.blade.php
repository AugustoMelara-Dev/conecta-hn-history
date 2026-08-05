<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Inventario - {{ $tenant->name }}</title>
    <style>
        * { font-family: Arial, sans-serif; font-size: 12px; }
        body { margin: 20px; color: #333; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #333; padding-bottom: 15px; }
        .header h1 { margin: 0; font-size: 24px; }
        .header p { margin: 5px 0; color: #666; }
        .alert { padding: 10px; margin-bottom: 20px; }
        .alert-warning { background: #fff3cd; border: 1px solid #ffc107; }
        .alert-danger { background: #f8d7da; border: 1px solid #dc3545; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #333; color: white; }
        tr:nth-child(even) { background: #f9f9f9; }
        .low-stock { background: #fff3cd !important; }
        .out-of-stock { background: #f8d7da !important; }
        .check-col { width: 30px; text-align: center; }
        .quantity-col { text-align: center; width: 80px; }
        .footer { margin-top: 30px; text-align: center; font-size: 10px; color: #999; }
        @media print { 
            body { margin: 0; } 
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $tenant->name }}</h1>
        <p>Manifiesto de Inventario</p>
        <p>Generado: {{ $generated_at->format('d/m/Y H:i:s') }}</p>
    </div>

    @if($out_of_stock_count > 0)
        <div class="alert alert-danger">
            ⚠️ <strong>{{ $out_of_stock_count }}</strong> producto(s) sin stock
        </div>
    @endif

    @if($low_stock_count > 0)
        <div class="alert alert-warning">
            ⚡ <strong>{{ $low_stock_count }}</strong> producto(s) con stock bajo
        </div>
    @endif

    <table>
        <thead>
            <tr>
                <th class="check-col">✓</th>
                <th>SKU</th>
                <th>Producto</th>
                <th class="quantity-col">Sistema</th>
                <th class="quantity-col">Físico</th>
                <th class="quantity-col">Mínimo</th>
            </tr>
        </thead>
        <tbody>
            @forelse($products as $product)
                @php
                    $isLow = $product->stock_quantity <= $product->low_stock_threshold && $product->stock_quantity > 0;
                    $isOut = $product->stock_quantity <= 0;
                @endphp
                <tr class="{{ $isOut ? 'out-of-stock' : ($isLow ? 'low-stock' : '') }}">
                    <td class="check-col">☐</td>
                    <td>{{ $product->sku ?? '-' }}</td>
                    <td>{{ $product->name }}</td>
                    <td class="quantity-col">{{ $product->stock_quantity }}</td>
                    <td class="quantity-col" style="border: 2px solid #333;">____</td>
                    <td class="quantity-col">{{ $product->low_stock_threshold }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" style="text-align: center;">No hay productos con seguimiento de inventario.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div style="margin-top: 30px; border-top: 1px solid #ddd; padding-top: 15px;">
        <p><strong>Verificado por:</strong> _________________________</p>
        <p><strong>Fecha:</strong> _________________________</p>
        <p><strong>Notas:</strong></p>
        <div style="border: 1px solid #ddd; height: 60px; margin-top: 5px;"></div>
    </div>

    <div class="footer">
        <p>Dovra AI &bull; {{ $generated_at->format('d/m/Y') }}</p>
    </div>
</body>
</html>
