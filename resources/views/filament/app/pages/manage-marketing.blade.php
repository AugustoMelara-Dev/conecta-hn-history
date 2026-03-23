<x-filament-panels::page>
    @include('filament.components.panel-critical-styles')

    @php
        $tenantSlug = filament()->getTenant()?->slug ?? '';
    @endphp

    @if (! $isPro)
        <div class="biz-shell">
            <div class="biz-grid-two">
                <div class="biz-card">
                    <div class="biz-stack">
                        <div class="biz-badge biz-badge-warning">Marketing PRO</div>

                        <div>
                            <p class="biz-kicker">Herramientas de venta</p>
                            <h1 class="biz-title">Impulsa tu negocio con QR y WhatsApp</h1>
                            <p class="biz-copy">
                                Activa materiales para compartir tu pagina, mensajes listos para clientes y una organizacion mas clara del menu.
                            </p>
                        </div>

                        <div class="biz-list">
                            <div class="biz-stat">
                                <p class="biz-action-title">Cartel QR listo para imprimir</p>
                                <p class="biz-action-copy">Descarga un PDF claro para caja, mesas o escaparate.</p>
                            </div>

                            <div class="biz-stat">
                                <p class="biz-action-title">WhatsApp configurable</p>
                                <p class="biz-action-copy">Define el mensaje base que recibe el cliente cuando te escribe.</p>
                            </div>

                            <div class="biz-stat">
                                <p class="biz-action-title">Secciones del menu</p>
                                <p class="biz-action-copy">Ordena tu catalogo para que el cliente entienda mejor lo que ofreces.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="biz-card-accent">
                    <div class="biz-stack">
                        <div>
                            <p class="biz-kicker">Disponible en PRO</p>
                            <h2 class="biz-heading">Desbloquea marketing util</h2>
                            <p class="biz-muted">
                                En plan Base ves el panorama general. Al activar PRO habilitas estas herramientas dentro del panel.
                            </p>
                        </div>

                        <div class="biz-list">
                            <div class="biz-checklist-item">
                                <div class="biz-checklist-index is-complete">1</div>
                                <div>
                                    <p class="biz-checklist-title">QR descargable</p>
                                    <p class="biz-checklist-copy">Comparte tu negocio con un codigo listo para imprimir.</p>
                                </div>
                            </div>

                            <div class="biz-checklist-item">
                                <div class="biz-checklist-index is-complete">2</div>
                                <div>
                                    <p class="biz-checklist-title">Mensaje de WhatsApp personalizable</p>
                                    <p class="biz-checklist-copy">Evita respuestas improvisadas y acelera la primera conversacion.</p>
                                </div>
                            </div>

                            <div class="biz-checklist-item">
                                <div class="biz-checklist-index is-complete">3</div>
                                <div>
                                    <p class="biz-checklist-title">Secciones del menu</p>
                                    <p class="biz-checklist-copy">Presenta tu catalogo por bloques claros y faciles de explorar.</p>
                                </div>
                            </div>
                        </div>

                        <div class="biz-button-row">
                            <a href="{{ route('filament.app.pages.manage-billing', ['tenant' => $tenantSlug]) }}" class="biz-button">Activar plan PRO</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @else
        <div class="biz-shell">
            <div class="biz-grid-two">
                <div class="biz-card">
                    <div class="biz-stack">
                        <div>
                            <p class="biz-kicker">WhatsApp</p>
                            <h2 class="biz-heading">Mensaje de contacto</h2>
                            <p class="biz-muted">
                                Configura el mensaje base que se enviara cuando el cliente pulse el boton de WhatsApp.
                            </p>
                        </div>

                        {{ $this->form }}

                        <div class="biz-preview">
                            <p class="biz-action-title">Vista previa</p>
                            <div class="biz-preview-bubble">{{ $messagePreview }}</div>
                        </div>
                    </div>
                </div>

                <div class="biz-card-accent">
                    <div class="biz-stack">
                        <div>
                            <p class="biz-kicker">Codigo QR</p>
                            <h2 class="biz-heading">Comparte tu pagina publica</h2>
                            <p class="biz-muted">
                                Descarga un cartel limpio para imprimir o revisa tu enlace antes de compartirlo.
                            </p>
                        </div>

                        <div class="biz-qr-frame">
                            @if ($qrCodeDataUri)
                                <img
                                    src="{{ $qrCodeDataUri }}"
                                    alt="Codigo QR del negocio"
                                    class="biz-qr-image"
                                />
                            @else
                                <div class="biz-empty-state">
                                    <h3 class="biz-heading">QR listo al cargar</h3>
                                    <p class="biz-muted">Si no aparece ahora mismo, vuelve a cargar esta vista o usa la descarga del cartel PDF.</p>
                                </div>
                            @endif

                            <a href="{{ $businessUrl }}" target="_blank" class="biz-action-copy" style="display: block; margin-top: 1rem;">
                                {{ $businessUrl }}
                            </a>

                            <div class="biz-button-row" style="justify-content: center; margin-top: 1rem;">
                                <button type="button" wire:click="downloadPoster" class="biz-button">
                                    Descargar cartel PDF
                                </button>
                                <a href="{{ $businessUrl }}" target="_blank" class="biz-button-secondary">
                                    Ver pagina publica
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="biz-card">
                <div class="biz-stack">
                    <div>
                        <p class="biz-kicker">Menu</p>
                        <h2 class="biz-heading">Secciones del catalogo</h2>
                        <p class="biz-muted">
                            Crea y ordena las secciones que usa tu menu publico.
                        </p>
                    </div>

                    {{ $this->table }}
                </div>
            </div>
        </div>
    @endif
</x-filament-panels::page>
