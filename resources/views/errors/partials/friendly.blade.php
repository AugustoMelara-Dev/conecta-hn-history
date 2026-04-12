@php
    $status = (string) ($status ?? 'Error');
    $title = (string) ($title ?? 'Algo salio mal');
    $message = (string) ($message ?? 'No pudimos completar esta accion en este momento.');
    $hint = isset($hint) ? (string) $hint : null;
    $actions = $actions ?? [
        ['label' => 'Volver al inicio', 'href' => '/'],
        ['label' => 'Explorar negocios', 'href' => '/explore'],
    ];
@endphp
<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $status }} | {{ $title }}</title>
        <style>
            :root {
                color-scheme: light;
                --bg-1: #f8fafc;
                --bg-2: #eef2ff;
                --surface: #ffffff;
                --text-strong: #0f172a;
                --text-soft: #475569;
                --ring: #c7d2fe;
                --primary: #0f172a;
                --primary-hover: #020617;
                --secondary: #ffffff;
                --secondary-border: #cbd5e1;
                --secondary-hover: #f8fafc;
            }
            * { box-sizing: border-box; }
            body {
                margin: 0;
                font-family: "Inter", "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
                min-height: 100vh;
                display: grid;
                place-items: center;
                background: radial-gradient(circle at top right, var(--bg-2), var(--bg-1) 52%);
                color: var(--text-strong);
            }
            .card {
                width: min(640px, calc(100vw - 2rem));
                background: var(--surface);
                border: 1px solid #e2e8f0;
                border-radius: 16px;
                padding: 2rem;
                box-shadow: 0 24px 50px -26px rgba(15, 23, 42, 0.35);
            }
            .status {
                display: inline-flex;
                align-items: center;
                border: 1px solid var(--ring);
                color: #3730a3;
                background: #eef2ff;
                border-radius: 999px;
                padding: .35rem .7rem;
                font-weight: 700;
                font-size: .8rem;
                letter-spacing: .03em;
                text-transform: uppercase;
            }
            h1 {
                margin: .9rem 0 .5rem;
                font-size: clamp(1.4rem, 3.6vw, 2rem);
                line-height: 1.2;
                letter-spacing: -.02em;
            }
            p {
                margin: 0;
                color: var(--text-soft);
                line-height: 1.6;
            }
            .hint {
                margin-top: .75rem;
            }
            .actions {
                margin-top: 1.5rem;
                display: flex;
                flex-wrap: wrap;
                gap: .75rem;
            }
            .btn {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                text-decoration: none;
                font-weight: 600;
                border-radius: 10px;
                padding: .72rem 1rem;
                transition: .16s ease;
                border: 1px solid transparent;
                min-width: 180px;
            }
            .btn-primary {
                background: var(--primary);
                color: #fff;
            }
            .btn-primary:hover {
                background: var(--primary-hover);
            }
            .btn-secondary {
                background: var(--secondary);
                border-color: var(--secondary-border);
                color: var(--text-strong);
            }
            .btn-secondary:hover {
                background: var(--secondary-hover);
            }
            .support {
                margin-top: 1rem;
                font-size: .88rem;
                color: #64748b;
            }
            .support code {
                font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
                color: #334155;
                background: #f1f5f9;
                padding: .08rem .36rem;
                border-radius: 6px;
                border: 1px solid #e2e8f0;
            }
        </style>
    </head>
    <body>
        <main class="card" role="main" aria-live="polite">
            <span class="status">{{ $status }}</span>
            <h1>{{ $title }}</h1>
            <p>{{ $message }}</p>
            @if($hint)
                <p class="hint">{{ $hint }}</p>
            @endif

            <nav class="actions" aria-label="Acciones de recuperacion">
                @foreach($actions as $index => $action)
                    <a class="btn {{ $index === 0 ? 'btn-primary' : 'btn-secondary' }}" href="{{ $action['href'] }}">
                        {{ $action['label'] }}
                    </a>
                @endforeach
            </nav>

            <p class="support">
                Si el problema persiste, comparte este codigo con soporte: <code>{{ $status }}</code>
            </p>
        </main>
    </body>
</html>
