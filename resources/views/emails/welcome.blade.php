<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bienvenido a Conecta HN</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            background-color: #f8fafc;
            color: #334155;
            line-height: 1.6;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            padding: 40px;
            border-radius: 8px;
            margin-top: 40px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo {
            background-color: #0f172a;
            color: white;
            width: 48px;
            height: 48px;
            line-height: 48px;
            border-radius: 8px;
            font-size: 24px;
            font-weight: bold;
            display: inline-block;
            margin-bottom: 10px;
        }
        .title {
            color: #0f172a;
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .content {
            margin-bottom: 30px;
        }
        .button {
            display: inline-block;
            background-color: #0f172a;
            color: white;
            padding: 12px 24px;
            border-radius: 9999px;
            text-decoration: none;
            font-weight: bold;
            text-align: center;
        }
        .footer {
            text-align: center;
            font-size: 12px;
            color: #94a3b8;
            margin-top: 40px;
            border-top: 1px solid #e2e8f0;
            padding-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo">C</div>
            <div class="title">Bienvenido a Conecta HN</div>
        </div>
        
        <div class="content">
            <p>Hola {{ $user->name }},</p>
            
            <p>Gracias por unirte a <strong>Conecta HN</strong>, la infraestructura digital que está transformando el comercio en Honduras.</p>
            
            <p>Tu cuenta ha sido creada exitosamente. Ahora eres parte de una comunidad que valora el crecimiento, la transparencia y la conexión directa.</p>
            
            @if($user->role === 'seller')
            <p>Como negocio, ahora puedes crear tu micrositio, subir productos y llegar a miles de clientes potenciales.</p>
            @else
            <p>Como usuario, ahora puedes explorar los mejores negocios locales, guardar tus favoritos y contactar directamente.</p>
            @endif
            
            <div style="text-align: center; margin-top: 30px;">
                <a href="{{ url('/login') }}" class="button">Acceder a mi Cuenta</a>
            </div>
        </div>
        
        <div class="footer">
            <p>© {{ date('Y') }} Conecta HN. Todos los derechos reservados.</p>
            <p>Tegucigalpa, Honduras</p>
        </div>
    </div>
</body>
</html>
