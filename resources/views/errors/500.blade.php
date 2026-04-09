<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>500 | Error Interno del Servidor</title>
    <style>
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            background: #020617;
            color: #e2e8f0;
            font-family: "Inter", "Segoe UI", sans-serif;
        }
        .card {
            width: min(680px, calc(100vw - 32px));
            border: 1px solid rgba(148, 163, 184, 0.3);
            border-radius: 18px;
            padding: 28px;
            background: rgba(15, 23, 42, 0.92);
            text-align: center;
            box-shadow: 0 25px 70px rgba(0, 0, 0, 0.45);
        }
        .code {
            font-size: 68px;
            line-height: 1;
            font-weight: 800;
            margin: 0;
            color: #f8fafc;
        }
        h1 {
            margin: 10px 0 0;
            font-size: 28px;
            color: #f8fafc;
        }
        p {
            margin: 14px auto 0;
            max-width: 560px;
            color: #cbd5e1;
        }
        .actions {
            margin-top: 24px;
            display: flex;
            gap: 10px;
            justify-content: center;
            flex-wrap: wrap;
        }
        .btn {
            display: inline-block;
            border-radius: 12px;
            padding: 11px 18px;
            text-decoration: none;
            font-weight: 700;
            border: 1px solid transparent;
        }
        .btn-primary {
            background: #f8fafc;
            color: #020617;
        }
        .btn-secondary {
            border-color: rgba(248, 250, 252, 0.35);
            color: #f8fafc;
        }
    </style>
</head>
<body>
    <section class="card">
        <p class="code">500</p>
        <h1>Error Interno del Servidor</h1>
        <p>
            Ocurrio un problema temporal al procesar esta accion.
            Por favor intenta nuevamente en unos segundos.
        </p>

        <div class="actions">
            <a class="btn btn-primary" href="javascript:window.location.reload()">Reintentar</a>
            <a class="btn btn-secondary" href="/">Volver al inicio</a>
        </div>
    </section>
</body>
</html>
