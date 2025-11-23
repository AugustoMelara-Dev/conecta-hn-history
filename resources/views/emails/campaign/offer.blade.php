<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $campaign->subject }}</title>
    <style>
        body { font-family: sans-serif; background-color: #f8fafc; color: #334155; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 0 auto; background-color: #ffffff; padding: 20px; border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); margin-top: 40px; }
        .header { text-align: center; padding-bottom: 20px; border-bottom: 1px solid #e2e8f0; margin-bottom: 20px; }
        .header img { max-height: 80px; border-radius: 8px; }
        .header h1 { font-size: 24px; color: #0f172a; margin-top: 10px; }
        .content { line-height: 1.6; font-size: 16px; }
        .cta { text-align: center; margin-top: 30px; }
        .cta a { display: inline-block; background-color: #f59e0b; color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold; }
        .footer { text-align: center; margin-top: 40px; font-size: 12px; color: #94a3b8; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            @if($campaign->tenant->logo_path)
                <img src="{{ asset('storage/' . $campaign->tenant->logo_path) }}" alt="{{ $campaign->tenant->name }}">
            @else
                <h1>{{ $campaign->tenant->name }}</h1>
            @endif
        </div>
        
        <div class="content">
            <p>Hola {{ $user->name }},</p>
            
            {!! $campaign->content !!}
        </div>
        
        <div class="cta">
            <a href="{{ route('tenant.show', $campaign->tenant->slug) }}">Ver Negocio</a>
        </div>
        
        <div class="footer">
            <p>Recibiste este correo porque sigues a {{ $campaign->tenant->name }} en Conecta HN.</p>
            <p>&copy; {{ date('Y') }} Conecta HN. Todos los derechos reservados.</p>
        </div>
    </div>
</body>
</html>
