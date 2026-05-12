<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Código de Verificación</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background-color: white;
            border-radius: 8px;
            padding: 40px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo h1 {
            margin: 0;
            font-size: 24px;
            font-weight: bold;
        }
        .code-box {
            background-color: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 30px;
            text-align: center;
            margin: 30px 0;
        }
        .code {
            font-size: 42px;
            font-weight: bold;
            letter-spacing: 8px;
            color: #000;
            font-family: 'Courier New', monospace;
        }
        .expires {
            color: #6c757d;
            font-size: 14px;
            margin-top: 15px;
        }
        .warning {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin-top: 30px;
            border-radius: 4px;
        }
        .footer {
            text-align: center;
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #e9ecef;
            color: #6c757d;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">
            <h1>🇭🇳 Vito Business OS</h1>
            <p style="color: #6c757d; margin: 0;">La Plataforma Digital de Honduras</p>
        </div>

        <h2 style="margin-top: 0;">Tu Código de Verificación</h2>
        <p>Hemos recibido una solicitud para acceder a tu cuenta. Usa el siguiente código para continuar:</p>

        <div class="code-box">
            <div class="code">{{ $code }}</div>
            <div class="expires">⏱️ Válido por {{ $expiresInMinutes }} minutos</div>
        </div>

        <p><strong>¿No solicitaste este código?</strong></p>
        <p>Si no intentaste iniciar sesión, puedes ignorar este correo de forma segura. Tu cuenta permanece protegida.</p>

        <div class="warning">
            <strong>⚠️ Importante:</strong><br>
            Nunca compartas este código con nadie. Nuestro equipo nunca te pedirá este código por teléfono, email o redes sociales.
        </div>

        <div class="footer">
            <p>© 2025 Vito Business OS - Todos los derechos reservados</p>
            <p>Este es un correo automático, por favor no respondas.</p>
        </div>
    </div>
</body>
</html>
