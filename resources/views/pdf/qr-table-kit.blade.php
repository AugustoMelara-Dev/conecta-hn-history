<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Menu QR - {{ $tenant->name }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            background: #ffffff;
            color: #000000;
        }

        .container {
            width: 100%;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 40px;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
        }

        .business-name {
            font-size: 32px;
            font-weight: bold;
            color: #000000;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 10px;
        }

        .subtitle {
            font-size: 14px;
            color: #666666;
        }

        .qr-container {
            background: #ffffff;
            border: 4px solid #000000;
            border-radius: 16px;
            padding: 30px;
            margin: 30px 0;
        }

        .qr-code {
            width: 280px;
            height: 280px;
        }

        .cta {
            text-align: center;
            margin-top: 30px;
        }

        .cta-text {
            font-size: 24px;
            font-weight: bold;
            color: #000000;
            margin-bottom: 10px;
        }

        .cta-subtitle {
            font-size: 14px;
            color: #666666;
        }

        .instructions {
            margin-top: 40px;
            padding: 20px;
            background: #f5f5f5;
            border-radius: 8px;
            text-align: center;
        }

        .instructions-title {
            font-size: 12px;
            font-weight: bold;
            color: #333333;
            margin-bottom: 8px;
        }

        .instructions-text {
            font-size: 11px;
            color: #666666;
        }

        .footer {
            margin-top: 40px;
            text-align: center;
            padding-top: 20px;
            border-top: 1px solid #eeeeee;
        }

        .powered-by {
            font-size: 10px;
            color: #999999;
        }

        .url {
            font-size: 10px;
            color: #666666;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="business-name">{{ $tenant->name }}</div>
            <div class="subtitle">Menu digital</div>
        </div>

        <div class="qr-container">
            <img src="{{ $qrBase64 }}" alt="Codigo QR para abrir el menu digital de {{ $tenant->name }}" class="qr-code" />
        </div>

        <div class="cta">
            <div class="cta-text">Escanea para ver el menu</div>
            <div class="cta-subtitle">Abre la camara de tu telefono y apunta al codigo.</div>
        </div>

        <div class="instructions">
            <div class="instructions-title">Como funciona</div>
            <div class="instructions-text">
                1. Abre la app de camara de tu telefono<br>
                2. Apunta al codigo QR<br>
                3. Toca la notificacion que aparece<br>
                4. Explora nuestro menu y haz tu pedido.
            </div>
        </div>

        <div class="footer">
            <div class="url">{{ $storefrontUrl }}</div>
            <div class="powered-by">Powered by Vito Business OS</div>
        </div>
    </div>
</body>
</html>
