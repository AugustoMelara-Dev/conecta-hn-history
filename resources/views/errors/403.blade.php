<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Acceso denegado</title>
        <style>
            :root {
                color-scheme: light;
                --background: #fafafa;
                --foreground: #171717;
                --muted: #737373;
                --border: #e5e5e5;
                --card: #ffffff;
            }

            * {
                box-sizing: border-box;
            }

            body {
                margin: 0;
                background: var(--background);
                color: var(--foreground);
                font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            }

            main {
                display: grid;
                min-height: 100dvh;
                place-items: center;
                padding: 24px;
            }

            .panel {
                width: min(100%, 440px);
                border: 1px solid var(--border);
                border-radius: 12px;
                background: var(--card);
                padding: 32px;
                text-align: center;
                box-shadow: 0 18px 45px rgb(15 23 42 / 0.08);
            }

            .code {
                margin: 0 0 8px;
                font-size: clamp(48px, 16vw, 72px);
                font-weight: 700;
                line-height: 1;
            }

            h1 {
                margin: 0 0 12px;
                font-size: 24px;
                line-height: 1.2;
            }

            p {
                margin: 0;
                color: var(--muted);
                line-height: 1.6;
            }

            .actions {
                display: grid;
                gap: 12px;
                margin-top: 28px;
            }

            a {
                display: inline-flex;
                min-height: 44px;
                align-items: center;
                justify-content: center;
                border-radius: 8px;
                padding: 10px 16px;
                font-weight: 600;
                text-decoration: none;
            }

            .primary {
                background: var(--foreground);
                color: #ffffff;
            }

            .secondary {
                border: 1px solid var(--border);
                color: var(--foreground);
            }

            a:focus-visible {
                outline: 2px solid var(--foreground);
                outline-offset: 3px;
            }
        </style>
    </head>
    <body>
        <main>
            <section class="panel" aria-labelledby="error-title">
                <p class="code">403</p>
                <h1 id="error-title">Acceso denegado</h1>
                <p>No tienes permisos para abrir este recurso. Si crees que deberias tener acceso, inicia sesion con la cuenta correcta.</p>
                <div class="actions">
                    <a class="primary" href="/">Volver al inicio</a>
                    <a class="secondary" href="/login">Iniciar sesion</a>
                </div>
            </section>
        </main>
    </body>
</html>
