<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cartel QR - {{ $tenant->name }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        @page {
            margin: 0;
            size: letter portrait;
        }

        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            background: white;
            width: 100%;
            height: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 40px;
        }

        .container {
            width: 100%;
            max-width: 600px;
            text-align: center;
        }

        /* Header con llamado a la acción */
        .header {
            margin-bottom: 30px;
        }

        .header h1 {
            font-size: 48px;
            font-weight: 800;
            color: #1a202c;
            line-height: 1.2;
            margin-bottom: 10px;
        }

        .header p {
            font-size: 20px;
            color: #4a5568;
        }

        /* Nombre del negocio */
        .business-name {
            font-size: 36px;
            font-weight: 700;
            color: #2b6cb0;
            margin-bottom: 30px;
            padding: 15px 30px;
            background: linear-gradient(135deg, #ebf8ff 0%, #bee3f8 100%);
            border-radius: 16px;
            display: inline-block;
        }

        /* Contenedor del QR */
        .qr-container {
            background: white;
            padding: 30px;
            border-radius: 24px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            border: 3px solid #e2e8f0;
            display: inline-block;
            margin-bottom: 30px;
        }

        .qr-container svg {
            width: 400px;
            height: 400px;
        }

        /* Instrucciones */
        .instructions {
            background: #f7fafc;
            border-radius: 16px;
            padding: 25px;
            margin-bottom: 30px;
        }

        .instructions h2 {
            font-size: 22px;
            color: #2d3748;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .instructions p {
            font-size: 18px;
            color: #4a5568;
            line-height: 1.6;
        }

        /* URL de respaldo */
        .url-backup {
            background: #edf2f7;
            border-radius: 8px;
            padding: 12px 20px;
            display: inline-block;
            margin-bottom: 30px;
        }

        .url-backup span {
            font-size: 16px;
            color: #718096;
        }

        .url-backup a {
            font-size: 18px;
            color: #3182ce;
            font-weight: 600;
            text-decoration: none;
        }

        /* Footer con branding */
        .footer {
            margin-top: auto;
            padding-top: 20px;
            border-top: 2px solid #e2e8f0;
            width: 100%;
        }

        .footer p {
            font-size: 14px;
            color: #a0aec0;
        }

        .footer .brand {
            font-weight: 600;
            color: #3182ce;
        }

        /* Icono de cámara */
        .camera-icon {
            font-size: 32px;
        }
    </style>
</head>
<body>
    <div class="container">
        {{-- Header con llamado a la acción --}}
        <div class="header">
            <h1>📱 ¡Escanea Nuestro Menú!</h1>
            <p>Ve precios, fotos y contáctanos al instante</p>
        </div>

        {{-- Nombre del negocio --}}
        <div class="business-name">
            {{ $tenant->name }}
        </div>

        {{-- Código QR gigante --}}
        <div class="qr-container">
            @if($qrCode)
                {!! $qrCode !!}
            @else
                <div style="width: 400px; height: 400px; background: #edf2f7; border-radius: 16px; display: flex; align-items: center; justify-content: center;">
                    <span style="color: #a0aec0; font-size: 24px;">QR Code</span>
                </div>
            @endif
        </div>

        {{-- Instrucciones --}}
        <div class="instructions">
            <h2>
                <span class="camera-icon">📷</span>
                ¿Cómo escanear?
            </h2>
            <p>
                Abre la <strong>cámara</strong> de tu celular y apunta al código.<br>
                Se abrirá automáticamente nuestro catálogo digital.
            </p>
        </div>

        {{-- URL de respaldo --}}
        <div class="url-backup">
            <span>O visita: </span>
            <a href="{{ $url }}">{{ str_replace(['https://', 'http://'], '', $url) }}</a>
        </div>

        {{-- Footer con branding --}}
        <div class="footer">
            <p>Powered by <span class="brand">Vito Business OS</span> - La vitrina digital de Honduras</p>
        </div>
    </div>
</body>
</html>
