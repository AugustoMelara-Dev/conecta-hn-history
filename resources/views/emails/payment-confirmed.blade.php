<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
        .header { background: linear-gradient(135deg, #10b981 0%, #059669 100%); padding: 40px 30px; text-align: center; }
        .header h1 { color: white; margin: 0; font-size: 28px; }
        .header .icon { font-size: 60px; margin-bottom: 10px; }
        .content { padding: 40px 30px; }
        .highlight { background: #ecfdf5; border-left: 4px solid #10b981; padding: 20px; margin: 20px 0; border-radius: 8px; }
        .highlight h3 { color: #059669; margin: 0 0 10px 0; }
        .details { background: #f9fafb; padding: 20px; border-radius: 8px; margin: 20px 0; }
        .details p { margin: 8px 0; color: #374151; }
        .details strong { color: #111827; }
        .btn { display: inline-block; background: #10b981; color: white; padding: 14px 28px; border-radius: 8px; text-decoration: none; font-weight: 600; margin: 20px 0; }
        .footer { background: #f9fafb; padding: 20px 30px; text-align: center; color: #6b7280; font-size: 14px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="icon">✅</div>
            <h1>Pago Confirmado</h1>
        </div>
        
        <div class="content">
            <p>Hola <strong>{{ $tenant->user->name ?? 'Cliente' }}</strong>,</p>
            
            <p>Excelentes noticias! Tu pago ha sido verificado y confirmado exitosamente.</p>
            
            <div class="highlight">
                <h3>Tu Plan PRO esta Activo</h3>
                <p>Ahora tienes acceso a todas las funcionalidades premium de Conecta HN.</p>
            </div>
            
            <div class="details">
                <p><strong>Negocio:</strong> {{ $tenant->name }}</p>
                <p><strong>Monto Pagado:</strong> L. {{ number_format($payment->amount, 2) }}</p>
                <p><strong>Fecha de Activacion:</strong> {{ now()->format('d/m/Y') }}</p>
                <p><strong>Valido Hasta:</strong> {{ optional($tenant->subscription_ends_at ?? $tenant->plan_expires_at)->format('d/m/Y') ?? 'N/A' }}</p>
            </div>
            
            <p>Con tu Plan PRO puedes:</p>
            <ul>
                <li>Subir productos ilimitados</li>
                <li>Personalizar colores y banner</li>
                <li>Activar boton de WhatsApp</li>
                <li>Agregar redes sociales</li>
            </ul>
            
            <center>
                <a href="{{ config('app.url') }}/app" class="btn">Ir a mi Panel</a>
            </center>
        </div>
        
        <div class="footer">
            <p>Gracias por confiar en Conecta HN</p>
            <p>© {{ date('Y') }} Conecta HN. Todos los derechos reservados.</p>
        </div>
    </div>
</body>
</html>
