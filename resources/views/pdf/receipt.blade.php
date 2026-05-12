<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recibo de Pago - Vito Business OS</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        @page {
            margin: 30px;
            size: letter portrait;
        }

        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            background: white;
            color: #1a202c;
            line-height: 1.5;
            font-size: 14px;
        }

        .container {
            max-width: 700px;
            margin: 0 auto;
            padding: 30px;
        }

        /* Header con logo */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding-bottom: 25px;
            border-bottom: 3px solid #3b82f6;
            margin-bottom: 30px;
        }

        .logo-section {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .logo {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            font-weight: bold;
        }

        .company-name {
            font-size: 24px;
            font-weight: 700;
            color: #1e40af;
        }

        .company-tagline {
            font-size: 12px;
            color: #64748b;
        }

        .document-info {
            text-align: right;
        }

        .document-title {
            font-size: 28px;
            font-weight: 700;
            color: #059669;
            letter-spacing: 2px;
        }

        .document-number {
            font-size: 12px;
            color: #64748b;
            margin-top: 5px;
        }

        /* Sello de pagado */
        .paid-stamp {
            position: absolute;
            top: 150px;
            right: 80px;
            transform: rotate(-15deg);
            font-size: 48px;
            font-weight: 900;
            color: rgba(5, 150, 105, 0.15);
            border: 6px solid rgba(5, 150, 105, 0.15);
            padding: 10px 30px;
            border-radius: 10px;
            letter-spacing: 5px;
        }

        /* Información del cliente */
        .client-section {
            background: #f8fafc;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
        }

        .section-title {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #64748b;
            margin-bottom: 10px;
        }

        .client-name {
            font-size: 20px;
            font-weight: 600;
            color: #1e293b;
        }

        .client-detail {
            font-size: 13px;
            color: #64748b;
            margin-top: 5px;
        }

        /* Tabla de detalle */
        .invoice-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 25px;
        }

        .invoice-table th {
            background: #1e40af;
            color: white;
            padding: 12px 15px;
            text-align: left;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .invoice-table th:first-child {
            border-radius: 8px 0 0 0;
        }

        .invoice-table th:last-child {
            border-radius: 0 8px 0 0;
            text-align: right;
        }

        .invoice-table td {
            padding: 15px;
            border-bottom: 1px solid #e2e8f0;
        }

        .invoice-table td:last-child {
            text-align: right;
            font-weight: 600;
        }

        .invoice-table .description {
            color: #64748b;
            font-size: 12px;
        }

        /* Total */
        .total-section {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 30px;
        }

        .total-box {
            background: linear-gradient(135deg, #059669, #047857);
            color: white;
            padding: 20px 40px;
            border-radius: 12px;
            text-align: right;
        }

        .total-label {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
            opacity: 0.9;
        }

        .total-amount {
            font-size: 32px;
            font-weight: 700;
            margin-top: 5px;
        }

        /* Información de pago */
        .payment-info {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 30px;
        }

        .payment-info-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }

        .payment-info-item {
            text-align: center;
        }

        .payment-info-label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #059669;
            margin-bottom: 5px;
        }

        .payment-info-value {
            font-size: 14px;
            font-weight: 600;
            color: #047857;
        }

        /* Footer */
        .footer {
            text-align: center;
            padding-top: 25px;
            border-top: 1px solid #e2e8f0;
            font-size: 12px;
            color: #94a3b8;
        }

        .footer p {
            margin-bottom: 5px;
        }

        .footer .thanks {
            font-size: 16px;
            color: #1e40af;
            font-weight: 600;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="container">
        {{-- Sello de Pagado --}}
        <div class="paid-stamp">PAGADO</div>

        {{-- Header --}}
        <div class="header">
            <div class="logo-section">
                <div class="logo">C</div>
                <div>
                    <div class="company-name">Vito Business OS</div>
                    <div class="company-tagline">La vitrina digital de Honduras</div>
                </div>
            </div>
            <div class="document-info">
                <div class="document-title">RECIBO</div>
                <div class="document-number">No. {{ str_pad($payment->id, 6, '0', STR_PAD_LEFT) }}</div>
            </div>
        </div>

        {{-- Información del Cliente --}}
        <div class="client-section">
            <div class="section-title">Cliente</div>
            <div class="client-name">{{ $tenant->name }}</div>
            @if($tenant->user)
            <div class="client-detail">{{ $tenant->user->email }}</div>
            @endif
            @if($tenant->address)
            <div class="client-detail">{{ $tenant->address }}</div>
            @endif
        </div>

        {{-- Tabla de Detalle --}}
        <table class="invoice-table">
            <thead>
                <tr>
                    <th>Descripción</th>
                    <th>Monto</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <strong>Suscripción Plan PRO Empresario</strong>
                        <div class="description">
                            Acceso completo por 30 dias: catalogo ampliado, carteleria QR,
                            personalización de marca, y más.
                        </div>
                    </td>
                    <td>L. {{ number_format($payment->amount, 2) }}</td>
                </tr>
            </tbody>
        </table>

        {{-- Total --}}
        <div class="total-section">
            <div class="total-box">
                <div class="total-label">Total Pagado</div>
                <div class="total-amount">L. {{ number_format($payment->amount, 2) }}</div>
            </div>
        </div>

        {{-- Información de Pago --}}
        <div class="payment-info">
            <div class="payment-info-grid">
                <div class="payment-info-item">
                    <div class="payment-info-label">Fecha de Pago</div>
                    <div class="payment-info-value">{{ $issueDate->format('d/m/Y') }}</div>
                </div>
                <div class="payment-info-item">
                    <div class="payment-info-label">Referencia Bancaria</div>
                    <div class="payment-info-value">{{ $payment->bank_reference ?? 'N/A' }}</div>
                </div>
                <div class="payment-info-item">
                    <div class="payment-info-label">Estado</div>
                    <div class="payment-info-value">✓ CONFIRMADO</div>
                </div>
            </div>
        </div>

        {{-- Footer --}}
        <div class="footer">
            <p class="thanks">¡Gracias por tu confianza!</p>
            <p>Este documento es un comprobante válido de pago.</p>
            <p>Para cualquier consulta, contacta soporte desde tu panel o canal asignado.</p>
        </div>
    </div>
</body>
</html>
