<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Código QR - {{ $tenant->name }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            padding: 40px;
            max-width: 400px;
            width: 100%;
            text-align: center;
        }
        
        .logo {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            margin: 0 auto 20px;
            object-fit: cover;
            border: 4px solid #667eea;
        }
        
        h1 {
            color: #2d3748;
            font-size: 24px;
            margin-bottom: 10px;
        }
        
        .subtitle {
            color: #718096;
            font-size: 14px;
            margin-bottom: 30px;
        }
        
        .qr-container {
            background: #f7fafc;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 30px;
        }
        
        .qr {
            width: 100%;
            max-width: 250px;
            height: auto;
            margin: 0 auto;
            display: block;
        }
        
        .url {
            background: #edf2f7;
            padding: 12px;
            border-radius: 8px;
            font-size: 12px;
            color: #4a5568;
            word-break: break-all;
            margin-top: 20px;
        }
        
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
            color: #a0aec0;
            font-size: 12px;
        }
        
        @media print {
            body {
                background: white;
            }
            .card {
                box-shadow: none;
            }
        }
    </style>
</head>
<body>
    <div class="card">
        @if($tenant->logo_path)
            <img src="{{ asset('storage/' . $tenant->logo_path) }}" alt="{{ $tenant->name }}" class="logo">
        @else
            <div class="logo" style="background: linear-gradient(135deg, #667eea, #764ba2); display: flex; align-items: center; justify-content: center;">
                <span style="color: white; font-size: 32px; font-weight: bold;">{{ substr($tenant->name, 0, 1) }}</span>
            </div>
        @endif
        
        <h1>{{ $tenant->name }}</h1>
        <p class="subtitle">Escanea para ver nuestro catálogo</p>
        
        <div class="qr-container">
            {!! $qr_svg !!}
        </div>
        
        <div class="url">
            {{ $url }}
        </div>
        
        <div class="footer">
            Powered by Dovra AI
        </div>
    </div>
</body>
</html>
