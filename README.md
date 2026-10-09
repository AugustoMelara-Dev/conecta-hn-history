# Dovra — Conecta HN / Vito Business OS

Plataforma para conectar negocios y clientes, administrar operaciones comerciales y desarrollar agentes de IA con contexto del negocio y supervisión humana.

Este repositorio contiene la implementación Laravel que comenzó como **Conecta HN** en noviembre de 2025 y evolucionó hacia **Dovra**. Conserva el nombre Vito-Business-OS y las referencias históricas del código. La implementación actual en Next.js se mantiene por separado en [dovra-next](https://github.com/AugustoMelara-Dev/dovra-next). La marca pública es [Zilviko](https://zilviko.lat).

## Funcionalidades

- Directorio, descubrimiento y páginas de negocios.
- Catálogo, clientes, pedidos y reservas.
- Conversaciones, seguimiento y operaciones del negocio.
- Paneles de administración y separación entre negocios.
- Módulos de automatización, agentes y conexiones con servicios externos.

El proyecto está en desarrollo; la disponibilidad de integraciones depende de su configuración y validación.

## Stack

PHP 8.4+, Laravel 13, React con Inertia, Filament 4, Vite y Node.js. Consulta `composer.json`, `package.json` y sus lockfiles para las dependencias y scripts exactos.

## Desarrollo local

1. Instala las dependencias con `composer install` y `npm ci`.
2. Crea un `.env` local desde `.env.example`, revisa sus valores y configura una base de datos de desarrollo aislada.
3. Genera la clave de aplicación con `php artisan key:generate`.
4. Revisa las migraciones y la configuración de colas, almacenamiento y servicios antes de ejecutarlos.
5. Usa `php artisan serve` y `npm run dev` para desarrollo local.

Las pruebas y scripts que escriben datos deben usar una base desechable. No incluyas credenciales, datos de clientes ni archivos de entorno en los commits.

## Estructura

- `app`: dominio, servicios y controladores.
- `resources`: interfaces y recursos del cliente.
- `routes`: rutas web y API.
- `database`: migraciones y datos de desarrollo.
- `tests`: pruebas automatizadas.

Desarrollado por [Augusto Melara](https://github.com/AugustoMelara-Dev), Honduras. Contacto: augusto@zilviko.lat.

## Archivo histórico

Esta copia conserva el historial de desarrollo del proyecto. Se retiraron entornos, datos locales e informes internos del historial; por ello algunos identificadores de commits cambian, conservando sus fechas y autores. Consulta [HISTORY.md](HISTORY.md).
