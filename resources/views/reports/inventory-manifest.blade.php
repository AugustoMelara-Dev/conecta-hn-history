<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Inventario - {{ $tenant->name }}</title>
    <style>
        * { font-family: Arial, sans-serif; font-size: 12px; box-sizing: border-box; }
        body { margin: 20px; color: #333; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #333; padding-bottom: 15px; }
        .header h1 { margin: 0; font-size: 24px; }
        .header p { margin: 5px 0; color: #666; }
        .alert { padding: 10px; margin-bottom: 20px; }
        .alert-warning { background: #fff3cd; border: 1px solid #ffc107; }
        .alert-danger { background: #f8d7da; border: 1px solid #dc3545; }
        .table-help { color: #555; margin: 0 0 8px; }
        .table-scroll { max-width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; }
        table { width: 100%; min-width: 640px; border-collapse: collapse; }
        caption { text-align: left; font-weight: bold; margin-bottom: 8px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #333; color: white; }
        tr:nth-child(even) { background: #f9f9f9; }
        .low-stock { background: #fff3cd !important; }
        .out-of-stock { background: #f8d7da !important; }
        .check-col { width: 30px; text-align: center; }
        .quantity-col { text-align: center; width: 80px; }
        .signature { margin-top: 30px; border-top: 1px solid #ddd; padding-top: 15px; }
        .notes-box { border: 1px solid #ddd; height: 60px; margin-top: 5px; }
        .footer { margin-top: 30px; text-align: center; font-size: 10px; color: #999; }
        @media (max-width: 640px) {
            body { margin: 12px; }
            .header h1 { font-size: 20px; }
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
        <p>Manifiesto de Inventario</p>
        <p>Generado: {{ $generated_at->format('d/m/Y H:i:s') }}</p>
    </header>

    @if($out_of_stock_count > 0)
        <div class="alert alert-danger" role="status">
            <strong>{{ $out_of_stock_count }}</strong> producto(s) sin stock
        </div>
    @endif

    @if($low_stock_count > 0)
        <div class="alert alert-warning" role="status">
            <strong>{{ $low_stock_count }}</strong> producto(s) con stock bajo
        </div>
    @endif

    <main>
        <p id="inventory-table-help" class="table-help no-print">En pantallas pequenas, desliza horizontalmente la tabla para ver todas las columnas.</p>
        <div class="table-scroll" role="region" aria-labelledby="inventory-table-title" aria-describedby="inventory-table-help" tabindex="0">
            <table>
                <caption id="inventory-table-title">Productos con seguimiento de inventario</caption>
                <thead>
                    <tr>
                        <th scope="col" class="check-col" aria-label="Verificado">OK</th>
                        <th scope="col">SKU</th>
                        <th scope="col">Producto</th>
                        <th scope="col" class="quantity-col">Sistema</th>
                        <th scope="col" class="quantity-col">Fisico</th>
                        <th scope="col" class="quantity-col">Minimo</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($products as $product)
                        @php
                            $isLow = $product->stock_quantity <= $product->low_stock_threshold && $product->stock_quantity > 0;
                            $isOut = $product->stock_quantity <= 0;
                        @endphp
                        <tr class="{{ $isOut ? 'out-of-stock' : ($isLow ? 'low-stock' : '') }}">
                            <td class="check-col" aria-label="Pendiente de verificacion">[ ]</td>
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
        </div>

        <section class="signature" aria-label="Verificacion manual del inventario">
            <p><strong>Verificado por:</strong> _________________________</p>
            <p><strong>Fecha:</strong> _________________________</p>
            <p><strong>Notas:</strong></p>
            <div class="notes-box" aria-hidden="true"></div>
        </section>
    </main>

    <footer class="footer">
        <p>Vito Business OS &bull; {{ $generated_at->format('d/m/Y') }}</p>
    </footer>
</body>
</html>
