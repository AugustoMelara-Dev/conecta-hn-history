<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
        .header { background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%); padding: 40px 30px; text-align: center; }
        .header h1 { color: white; margin: 0; font-size: 28px; }
        .header .icon { font-size: 60px; margin-bottom: 10px; }
        .content { padding: 40px 30px; }
        .alert { background: #f3f4f6; border-left: 4px solid #6b7280; padding: 20px; margin: 20px 0; border-radius: 8px; }
        .alert h3 { color: #374151; margin: 0 0 10px 0; }
        .btn { display: inline-block; background: #10b981; color: white; padding: 14px 28px; border-radius: 8px; text-decoration: none; font-weight: 600; margin: 20px 0; }
        .footer { background: #f9fafb; padding: 20px 30px; text-align: center; color: #6b7280; font-size: 14px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="icon">⏸️</div>
            <h1>Plan PRO suspendido</h1>
        </div>
        
        <div class="content">
            <p>Hola <strong>{{ $tenant->user->name ?? 'Cliente' }}</strong>,</p>
            
            <p>Te informamos que tu suscripción PRO de <strong>{{ $tenant->name }}</strong> ha expirado y los beneficios premium han sido desactivados.</p>
            
            <div class="alert">
                <h3>Qué significa esto</h3>
                <p>Tu negocio sigue visible en Conecta HN, pero ahora opera con el plan gratuito:</p>
                <ul style="margin: 10px 0 0 0; color: #6b7280;">
                    <li>Límite de 10 productos visibles</li>
                    <li>Sin personalización de colores</li>
                    <li>Sin banner personalizado</li>
                    <li>Sin botón de WhatsApp</li>
                </ul>
            </div>
            
            <p>La buena noticia es que puedes reactivar tu Plan PRO en cualquier momento manteniendo toda tu información.</p>
            
            <center>
                <a href="{{ config('app.url') }}/app" class="btn">Reactivar plan PRO</a>
            </center>
            
            <p style="text-align: center; color: #6b7280; font-size: 14px;">
                Estamos aquí para ayudarte. Contáctanos si tienes preguntas.
            </p>
        </div>
        
        <div class="footer">
            <p>© {{ date('Y') }} Conecta HN. Todos los derechos reservados.</p>
        </div>
    </div>
</body>
</html>
