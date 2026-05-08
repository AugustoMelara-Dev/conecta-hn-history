<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
        .header { background: linear-gradient(135deg, #10b981 0%, #059669 100%); padding: 40px 30px; text-align: center; }
        .header h1 { color: white; margin: 0; font-size: 28px; }
        .header .status { color: white; font-size: 14px; font-weight: 700; letter-spacing: 0.08em; margin-bottom: 10px; text-transform: uppercase; }
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
            <div class="status">Pago verificado</div>
            <h1>Pago confirmado</h1>
        </div>

        <div class="content">
            <p>Hola <strong>{{ $tenant->name ?? 'Cliente' }}</strong>,</p>

            <p>Buenas noticias: tu pago fue verificado y confirmado.</p>

            <div class="highlight">
                <h3>Tu Plan PRO esta activo</h3>
                <p>Ahora tienes acceso a las funcionalidades premium de Conecta HN.</p>
            </div>

            <div class="details">
                <p><strong>Negocio:</strong> {{ $tenant->name }}</p>
                <p><strong>Monto pagado:</strong> L. {{ $payment->amount }}</p>
                <p><strong>Fecha de activacion:</strong> {{ now()->format('d/m/Y') }}</p>
                <p><strong>Valido hasta:</strong> {{ $tenant->subscriptionEndsAt ? \Illuminate\Support\Carbon::parse($tenant->subscriptionEndsAt)->format('d/m/Y') : 'Pendiente de actualizar' }}</p>
            </div>

            <p>Con tu Plan PRO puedes:</p>
            <ul>
                <li>Subir productos ilimitados</li>
                <li>Personalizar colores y banner</li>
                <li>Activar boton de WhatsApp</li>
                <li>Agregar redes sociales</li>
            </ul>

            <center>
                <a href="{{ url('/login') }}" class="btn">Entrar al panel</a>
            </center>
        </div>

        <div class="footer">
            <p>Gracias por confiar en Conecta HN</p>
            <p>&copy; {{ date('Y') }} Conecta HN. Todos los derechos reservados.</p>
        </div>
    </div>
</body>
</html>
