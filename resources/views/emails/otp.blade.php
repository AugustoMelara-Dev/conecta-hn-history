<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Código de acceso</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            line-height: 1.6;
            color: #111827;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f4f6f8;
        }
        .container {
            background-color: white;
            border-radius: 8px;
            padding: 40px;
            box-shadow: 0 2px 12px rgba(15, 23, 42, 0.08);
        }
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 800;
        }
        .button {
            display: inline-block;
            background-color: #0f172a;
            color: #ffffff !important;
            padding: 14px 22px;
            border-radius: 8px;
            font-weight: 700;
            text-decoration: none;
        }
        .code-box {
            background-color: #f8fafc;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            padding: 30px;
            text-align: center;
            margin: 30px 0;
        }
        .code {
            font-size: 42px;
            font-weight: bold;
            letter-spacing: 8px;
            color: #0f172a;
            font-family: 'Courier New', monospace;
        }
        .expires {
            color: #64748b;
            font-size: 14px;
            margin-top: 15px;
        }
        .warning {
            background-color: #fff7ed;
            border-left: 4px solid #f97316;
            padding: 15px;
            margin-top: 30px;
            border-radius: 4px;
        }
        .footer {
            text-align: center;
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
            color: #64748b;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">
            <h1>Vito Business OS</h1>
            <p style="color: #64748b; margin: 0;">El sistema operativo para tu negocio</p>
        </div>

        <h2 style="margin-top: 0;">Tu acceso está listo</h2>
        <p>Recibimos una solicitud para iniciar sesión en tu cuenta.</p>

        @if ($magicUrl)
            <p>Si fuiste tú, entra desde este mismo dispositivo con el botón seguro:</p>
            <p style="text-align: center; margin: 28px 0;">
                <a class="button" href="{{ $magicUrl }}">Entrar en este dispositivo</a>
            </p>
            <p>Si abriste este correo en otro dispositivo, usa este código como respaldo:</p>
        @else
            <p>Usa este código para continuar:</p>
        @endif

        <div class="code-box">
            <div class="code">{{ $code }}</div>
            <div class="expires">Válido por {{ $expiresInMinutes }} minutos</div>
        </div>

        <p><strong>¿No fuiste tú?</strong></p>
        <p>Si no intentaste iniciar sesión, cambia tu contraseña para proteger la cuenta:</p>
        <p><a href="{{ $resetPasswordUrl }}">Cambiar contraseña</a></p>

        <div class="warning">
            <strong>Importante:</strong><br>
            Nunca compartas este código con nadie. Nuestro equipo nunca te pedirá este código por teléfono, correo o redes sociales.
        </div>

        <div class="footer">
            <p>© {{ date('Y') }} Vito Business OS. Todos los derechos reservados.</p>
            <p>Este es un correo automático, por favor no respondas.</p>
        </div>
    </div>
</body>
</html>
