<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="refresh" content="60">
    <title>En Mantenimiento | Dovra AI</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: linear-gradient(135deg, #1e3a5f 0%, #0f1419 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
        }
        
        .container {
            text-align: center;
            padding: 2rem;
            max-width: 600px;
        }
        
        .icon {
            width: 120px;
            height: 120px;
            margin: 0 auto 2rem;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            animation: pulse 2s infinite;
        }
        
        .icon svg {
            width: 60px;
            height: 60px;
            fill: #60a5fa;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.05); opacity: 0.8; }
        }
        
        h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            background: linear-gradient(90deg, #60a5fa, #a78bfa);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        p {
            font-size: 1.2rem;
            color: #94a3b8;
            margin-bottom: 2rem;
            line-height: 1.6;
        }
        
        .progress {
            width: 100%;
            height: 4px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 2px;
            overflow: hidden;
            margin-bottom: 1rem;
        }
        
        .progress-bar {
            height: 100%;
            background: linear-gradient(90deg, #60a5fa, #a78bfa);
            animation: loading 2s infinite;
        }
        
        @keyframes loading {
            0% { width: 0%; margin-left: 0; }
            50% { width: 50%; margin-left: 25%; }
            100% { width: 0%; margin-left: 100%; }
        }
        
        .status {
            font-size: 0.9rem;
            color: #64748b;
        }
        
        .contact {
            margin-top: 3rem;
            padding-top: 2rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .contact a {
            color: #60a5fa;
            text-decoration: none;
            font-weight: 500;
        }
        
        .contact a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
            </svg>
        </div>
        
        <h1>Estamos Mejorando</h1>
        
        <p>
            Nuestro equipo está trabajando en mejoras importantes para brindarte 
            una mejor experiencia. Estaremos de vuelta muy pronto.
        </p>
        
        <div class="progress">
            <div class="progress-bar"></div>
        </div>
        
        <p class="status">
            Esta página se actualizará automáticamente...
        </p>
        
        <div class="contact">
            <p>
                ¿Tienes una emergencia? Contáctanos por 
                <a href="https://wa.me/50499999999">WhatsApp</a>
            </p>
        </div>
    </div>
</body>
</html>
