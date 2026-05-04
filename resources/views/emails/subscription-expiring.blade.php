<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
        .header { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); padding: 40px 30px; text-align: center; }
        .header h1 { color: white; margin: 0; font-size: 28px; }
        .header .icon { font-size: 60px; margin-bottom: 10px; }
        .content { padding: 40px 30px; }
        .warning { background: #fffbeb; border-left: 4px solid #f59e0b; padding: 20px; margin: 20px 0; border-radius: 8px; }
        .warning h3 { color: #d97706; margin: 0 0 10px 0; }
        .countdown { text-align: center; padding: 30px; background: #f9fafb; border-radius: 12px; margin: 20px 0; }
        .countdown .number { font-size: 48px; font-weight: bold; color: #d97706; }
        .countdown .label { color: #6b7280; font-size: 14px; }
        .btn { display: inline-block; background: #10b981; color: white; padding: 14px 28px; border-radius: 8px; text-decoration: none; font-weight: 600; margin: 20px 0; }
        .footer { background: #f9fafb; padding: 20px 30px; text-align: center; color: #6b7280; font-size: 14px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="icon">⏰</div>
            <h1>Tu plan PRO vence pronto</h1>
        </div>
        
        <div class="content">
            <p>Hola <strong>{{ $tenant->user->name ?? 'Cliente' }}</strong>,</p>
            
            <p>Te escribimos para recordarte que tu suscripción PRO de <strong>{{ $tenant->name }}</strong> está por vencer.</p>
            
            <div class="countdown">
                <div class="number">{{ $daysRemaining }}</div>
                <div class="label">{{ $daysRemaining === 1 ? 'DÍA RESTANTE' : 'DÍAS RESTANTES' }}</div>
            </div>
            
            <div class="warning">
                <h3>No pierdas tus beneficios</h3>
                <p>Al vencer tu plan, perderás acceso a:
                    <ul style="margin: 10px 0 0 0;">
                        <li>Productos ilimitados</li>
                        <li>Personalización de colores</li>
                        <li>Banner personalizado</li>
                        <li>Botón de WhatsApp</li>
                    </ul>
                </p>
            </div>
            
            <p><strong>Fecha de vencimiento:</strong> {{ optional($tenant->subscription_ends_at ?? $tenant->plan_expires_at)->format('d/m/Y') ?? 'N/A' }}</p>
            
            <center>
                <a href="{{ config('app.url') }}/app" class="btn">Renovar ahora</a>
            </center>
            
            <p style="text-align: center; color: #6b7280; font-size: 14px;">
                Renueva hoy y mantén tu negocio visible sin interrupciones.
            </p>
        </div>
        
        <div class="footer">
            <p>© {{ date('Y') }} Conecta HN. Todos los derechos reservados.</p>
        </div>
    </div>
</body>
</html>
