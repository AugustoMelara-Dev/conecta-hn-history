<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>QR Menu - {{ $tenant->name }}</title>
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
            <div class="subtitle">Menú Digital</div>
        </div>
        
        <div class="qr-container">
            <img src="{{ $qrBase64 }}" alt="QR Code" class="qr-code" />
        </div>
        
        <div class="cta">
            <div class="cta-text">📱 Escanea para ver el Menú</div>
            <div class="cta-subtitle">Abre la cámara de tu teléfono y apunta al código</div>
        </div>
        
        <div class="instructions">
            <div class="instructions-title">¿Cómo funciona?</div>
            <div class="instructions-text">
                1. Abre la app de cámara de tu teléfono<br>
                2. Apunta al código QR<br>
                3. Toca la notificación que aparece<br>
                4. ¡Explora nuestro menú y haz tu pedido!
            </div>
        </div>
        
        <div class="footer">
            <div class="url">{{ $storefrontUrl }}</div>
            <div class="powered-by">Powered by Conecta HN</div>
        </div>
    </div>
</body>
</html>
