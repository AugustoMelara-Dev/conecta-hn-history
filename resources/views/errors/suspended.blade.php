<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cuenta Suspendida - Dovra AI</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .container {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 24px;
            padding: 60px 40px;
            max-width: 500px;
            text-align: center;
        }
        
        .icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
        }
        
        .icon svg {
            width: 40px;
            height: 40px;
            color: white;
        }
        
        h1 {
            color: #ef4444;
            font-size: 28px;
            margin-bottom: 16px;
            font-weight: 700;
        }
        
        .message {
            color: #94a3b8;
            font-size: 16px;
            line-height: 1.6;
            margin-bottom: 30px;
        }
        
        .business-name {
            color: white;
            font-weight: 600;
        }
        
        .divider {
            height: 1px;
            background: rgba(255, 255, 255, 0.1);
            margin: 30px 0;
        }
        
        .contact-info {
            color: #64748b;
            font-size: 14px;
        }
        
        .contact-info a {
            color: #60a5fa;
            text-decoration: none;
        }
        
        .contact-info a:hover {
            text-decoration: underline;
        }
        
        .owner-notice {
            background: rgba(234, 179, 8, 0.1);
            border: 1px solid rgba(234, 179, 8, 0.3);
            border-radius: 12px;
            padding: 20px;
            margin-top: 20px;
        }
        
        .owner-notice p {
            color: #facc15;
            font-size: 14px;
        }
        
        .btn {
            display: inline-block;
            background: #3b82f6;
            color: white;
            padding: 12px 24px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            margin-top: 20px;
            transition: background 0.3s;
        }
        
        .btn:hover {
            background: #2563eb;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
            </svg>
        </div>
        
        <h1>Cuenta Suspendida</h1>
        
        <p class="message">
            El negocio <span class="business-name">{{ $tenant->name ?? 'seleccionado' }}</span> 
            ha sido temporalmente suspendido por el administrador de la plataforma.
        </p>
        
        <div class="divider"></div>
        
        <p class="contact-info">
            Si crees que esto es un error, por favor contacta a soporte desde tu panel o canal de atención asignado.
        </p>
        
        @if(isset($isOwner) && $isOwner)
        <div class="owner-notice">
            <p>
                <strong>Eres el propietario de este negocio.</strong><br>
                Tu cuenta ha sido suspendida. Por favor, revisa tu correo electronico 
                para mas informacion o contacta a soporte.
            </p>
        </div>
        @endif
        
        <a href="/" class="btn">Volver al Inicio</a>
    </div>
</body>
</html>
