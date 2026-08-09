<x-filament-panels::page>
    <main class="biz-shell">
        <section class="biz-card">
            <p class="biz-kicker">Configuración</p>
            <h1 class="biz-title">Ajustes globales</h1>
            <p class="biz-copy">Resumen de parámetros operativos no sensibles. Los secretos se administran mediante variables de entorno.</p>
        </section>

        <section class="biz-grid-two">
            <article class="biz-card">
                <h2 class="biz-heading">Aplicación</h2>
                <dl class="biz-definition-list">
                    <div><dt>Entorno</dt><dd>{{ app()->environment() }}</dd></div>
                    <div><dt>Zona horaria</dt><dd>{{ config('app.timezone') }}</dd></div>
                    <div><dt>Idioma</dt><dd>{{ config('app.locale') }}</dd></div>
                </dl>
            </article>
            <article class="biz-card">
                <h2 class="biz-heading">Servicios</h2>
                <dl class="biz-definition-list">
                    <div><dt>Cola</dt><dd>{{ config('queue.default') }}</dd></div>
                    <div><dt>Cache</dt><dd>{{ config('cache.default') }}</dd></div>
                    <div><dt>Archivos</dt><dd>{{ config('filesystems.default') }}</dd></div>
                </dl>
            </article>
        </section>
    </main>
</x-filament-panels::page>
