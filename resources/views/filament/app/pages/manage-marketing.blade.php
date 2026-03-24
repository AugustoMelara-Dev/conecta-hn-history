<x-filament-panels::page>
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
                                Activa materiales para compartir tu página, mensajes listos para clientes y una organización más clara del menú.
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
                                <p class="biz-action-title">Secciones del menú</p>
                                <p class="biz-action-copy">Ordena tu catálogo para que el cliente entienda mejor lo que ofreces.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="biz-card-accent">
                    <div class="biz-stack">
                        <div>
                            <p class="biz-kicker">Disponible en PRO</p>
                            <h2 class="biz-heading">Desbloquea marketing útil</h2>
                            <p class="biz-muted">
                                En plan Base ves el panorama general. Al activar PRO habilitas estas herramientas dentro del panel.
                            </p>
                        </div>

                        <div class="biz-list">
                            <div class="biz-checklist-item">
                                <div class="biz-checklist-index is-complete">1</div>
                                <div>
                                    <p class="biz-checklist-title">QR descargable</p>
                                    <p class="biz-checklist-copy">Comparte tu negocio con un código listo para imprimir.</p>
                                </div>
                            </div>

                            <div class="biz-checklist-item">
                                <div class="biz-checklist-index is-complete">2</div>
                                <div>
                                    <p class="biz-checklist-title">Mensaje de WhatsApp personalizable</p>
                                    <p class="biz-checklist-copy">Evita respuestas improvisadas y acelera la primera conversación.</p>
                                </div>
                            </div>

                            <div class="biz-checklist-item">
                                <div class="biz-checklist-index is-complete">3</div>
                                <div>
                                    <p class="biz-checklist-title">Secciones del menú</p>
                                    <p class="biz-checklist-copy">Presenta tu catálogo por bloques claros y fáciles de explorar.</p>
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
                                Configura el mensaje base que se enviará cuando el cliente pulse el botón de WhatsApp.
                            </p>
                        </div>

                        <form wire:submit="saveWhatsAppConfig" class="biz-stack">
                            {{ $this->form }}

                            <div class="biz-button-row">
                                <button
                                    type="submit"
                                    class="biz-button"
                                    wire:loading.attr="disabled"
                                    wire:target="saveWhatsAppConfig"
                                >
                                    <span wire:loading.remove wire:target="saveWhatsAppConfig">Guardar configuración</span>
                                    <span wire:loading wire:target="saveWhatsAppConfig">Guardando...</span>
                                </button>
                            </div>

                            <div class="biz-preview">
                                <p class="biz-action-title">Vista previa</p>
                                <div class="biz-preview-bubble">{{ $messagePreview }}</div>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="biz-card-accent">
                    <div class="biz-stack">
                        <div>
                            <p class="biz-kicker">Código QR</p>
                            <h2 class="biz-heading">Comparte tu página pública</h2>
                            <p class="biz-muted">
                                Descarga un cartel limpio para imprimir o revisa tu enlace antes de compartirlo.
                            </p>
                        </div>

                        <div class="biz-qr-frame">
                            @if ($qrCodeDataUri)
                                <img
                                    src="{{ $qrCodeDataUri }}"
                                    alt="Código QR del negocio"
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
                                <button
                                    type="button"
                                    wire:click="downloadPoster"
                                    wire:loading.attr="disabled"
                                    wire:target="downloadPoster"
                                    class="biz-button"
                                >
                                    <span wire:loading.remove wire:target="downloadPoster">Descargar cartel PDF</span>
                                    <span wire:loading wire:target="downloadPoster">Generando PDF...</span>
                                </button>
                                <a href="{{ $businessUrl }}" target="_blank" class="biz-button-secondary">
                                    Ver página pública
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="biz-card">
                <div class="biz-stack">
                    <div>
                        <p class="biz-kicker">Menú</p>
                        <h2 class="biz-heading">Secciones del catálogo</h2>
                        <p class="biz-muted">
                            Crea y ordena las secciones que usa tu menú público.
                        </p>
                    </div>

                    {{ $this->table }}
                </div>
            </div>
        </div>
    @endif
</x-filament-panels::page>
