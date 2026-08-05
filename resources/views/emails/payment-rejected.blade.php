<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
        .header { background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); padding: 40px 30px; text-align: center; }
        .header h1 { color: white; margin: 0; font-size: 28px; }
        .header .status { color: white; font-size: 14px; font-weight: 700; letter-spacing: 0.08em; margin-bottom: 10px; text-transform: uppercase; }
        .content { padding: 40px 30px; }
        .alert { background: #fef2f2; border-left: 4px solid #ef4444; padding: 20px; margin: 20px 0; border-radius: 8px; }
        .alert h3 { color: #dc2626; margin: 0 0 10px 0; }
        .reason { background: #f9fafb; padding: 20px; border-radius: 8px; margin: 20px 0; border: 1px solid #e5e7eb; }
        .reason p { margin: 0; color: #374151; font-style: italic; }
        .btn { display: inline-block; background: #3b82f6; color: white; padding: 14px 28px; border-radius: 8px; text-decoration: none; font-weight: 600; margin: 20px 0; }
        .footer { background: #f9fafb; padding: 20px 30px; text-align: center; color: #6b7280; font-size: 14px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="status">Revision requerida</div>
            <h1>Pago no aprobado</h1>
        </div>

        <div class="content">
            <p>Hola,</p>

            <p>Tu comprobante de pago no pudo ser aprobado.</p>

            <div class="alert">
                <h3>Se requiere accion de tu parte</h3>
                <p>Por favor revisa el motivo y sube un nuevo comprobante.</p>
            </div>

            <p><strong>Motivo del rechazo:</strong></p>
            <div class="reason">
                <p>"{{ $reason }}"</p>
            </div>

            <p><strong>Que puedes hacer:</strong></p>
            <ol>
                <li>Verifica que el comprobante sea legible y muestre claramente el monto y la referencia.</li>
                <li>Asegurate de que el monto coincida con el plan solicitado.</li>
                <li>Entra al panel y sube un nuevo comprobante.</li>
            </ol>

            <center>
                <a href="{{ url('/login') }}" class="btn">Entrar al panel</a>
            </center>

            <p style="color: #6b7280; font-size: 14px;">
                Si tienes preguntas, responde a este correo o contactanos en soporte.
            </p>
        </div>

        <div class="footer">
            <p>&copy; {{ date('Y') }} Dovra AI. Todos los derechos reservados.</p>
        </div>
    </div>
</body>
</html>
