<x-filament-panels::page>
    @include('filament.components.panel-critical-styles')

    <div class="biz-shell">
        <div class="biz-card">
            <div class="biz-grid-two">
                <div class="biz-stack">
                    <div class="biz-badge {{ $isOpen ? 'biz-badge-success' : 'biz-badge-warning' }}">
                        <span>{{ $isOpen ? 'Abierto' : 'Cerrado' }}</span>
                    </div>

                    <div class="biz-stack">
                        <p class="biz-kicker">Panel del negocio</p>
                        <h1 class="biz-title">{{ $tenantName }}</h1>
                        <p class="biz-copy">
                            Gestiona tu catalogo, revisa tu presencia publica y completa la configuracion esencial desde un panel claro y directo.
                        </p>
                    </div>

                    <div class="biz-metrics">
                        <div class="biz-stat">
                            <p class="biz-stat-label">Productos</p>
                            <p class="biz-stat-value">{{ $productsCount }}</p>
                            <p class="biz-stat-copy">{{ $activeProductsCount }} activos</p>
                        </div>

                        <div class="biz-stat">
                            <p class="biz-stat-label">Secciones</p>
                            <p class="biz-stat-value">{{ $sectionsCount }}</p>
                            <p class="biz-stat-copy">Categorias del menu</p>
                        </div>

                        <div class="biz-stat">
                            <p class="biz-stat-label">Seguidores</p>
                            <p class="biz-stat-value">{{ $followersCount }}</p>
                            <p class="biz-stat-copy">Audiencia registrada</p>
                        </div>

                        <div class="biz-stat">
                            <p class="biz-stat-label">Plan</p>
                            <p class="biz-stat-value">{{ $isPro ? 'PRO' : 'Base' }}</p>
                            <p class="biz-stat-copy">{{ $isPro ? 'Funciones premium activas' : 'Modo esencial' }}</p>
                        </div>
                    </div>
                </div>

                <div class="biz-card-accent">
                    <div class="biz-stack">
                        <div>
                            <p class="biz-kicker">Accesos rapidos</p>
                            <h2 class="biz-heading">Lo mas usado hoy</h2>
                            <p class="biz-muted">
                                Entra directo a las areas clave sin perderte entre pantallas.
                            </p>
                        </div>

                        <div class="biz-actions">
                            <a href="{{ $productsUrl }}" class="biz-action">
                                <p class="biz-action-title">Administrar productos</p>
                                <p class="biz-action-copy">Agrega, edita o publica tu catalogo.</p>
                            </a>

                            <a href="{{ $profileUrl }}" class="biz-action">
                                <p class="biz-action-title">Configuracion del negocio</p>
                                <p class="biz-action-copy">Nombre, WhatsApp, direccion y mensaje base.</p>
                            </a>

                            <a href="{{ $publicUrl }}" target="_blank" class="biz-action">
                                <p class="biz-action-title">Ver pagina publica</p>
                                <p class="biz-action-copy">Abre tu micrositio tal como lo ve el cliente.</p>
                            </a>

                            <a href="{{ $marketingUrl }}" class="biz-action">
                                <p class="biz-action-title">Marketing y QR</p>
                                <p class="biz-action-copy">Comparte tu negocio y organiza mejor tu menu.</p>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="biz-grid-two">
            <div class="biz-card">
                <div class="biz-stack">
                    <div>
                        <p class="biz-kicker">Checklist</p>
                        <h2 class="biz-heading">Lo esencial para verte profesional</h2>
                        <p class="biz-muted">
                            Completa estos puntos para que tu pagina publica sea mas clara y convierta mejor.
                        </p>
                    </div>

                    <div class="biz-list">
                        <div class="biz-checklist-item">
                            <div class="biz-checklist-index {{ $productsCount > 0 ? 'is-complete' : 'is-pending' }}">
                                {{ $productsCount > 0 ? 'OK' : '1' }}
                            </div>
                            <div>
                                <p class="biz-checklist-title">Catalogo cargado</p>
                                <p class="biz-checklist-copy">
                                    {{ $productsCount > 0 ? 'Ya tienes productos visibles o listos para publicar.' : 'Agrega al menos un producto para que tu vitrina no salga vacia.' }}
                                </p>
                            </div>
                        </div>

                        <div class="biz-checklist-item">
                            <div class="biz-checklist-index {{ $hasWhatsApp ? 'is-complete' : 'is-pending' }}">
                                {{ $hasWhatsApp ? 'OK' : '2' }}
                            </div>
                            <div>
                                <p class="biz-checklist-title">WhatsApp configurado</p>
                                <p class="biz-checklist-copy">
                                    {{ $hasWhatsApp ? 'Tus clientes ya pueden contactarte sin friccion.' : 'Agrega tu numero para captar conversaciones desde la pagina publica.' }}
                                </p>
                            </div>
                        </div>

                        <div class="biz-checklist-item">
                            <div class="biz-checklist-index {{ $hasSchedule ? 'is-complete' : 'is-pending' }}">
                                {{ $hasSchedule ? 'OK' : '3' }}
                            </div>
                            <div>
                                <p class="biz-checklist-title">Horario definido</p>
                                <p class="biz-checklist-copy">
                                    {{ $hasSchedule ? 'Tu horario ya da mas confianza al cliente.' : 'Completa tus horarios para indicar cuando estas atendiendo.' }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="biz-card-accent">
                <div class="biz-stack">
                    <div>
                        <p class="biz-kicker">Siguiente paso</p>
                        <h2 class="biz-heading">Muevelo y compartelo</h2>
                        <p class="biz-copy">
                            Publica tu catalogo, revisa tu pagina y comparte el enlace desde donde ya trabajas.
                        </p>
                    </div>

                    <div class="biz-list">
                        <div class="biz-stat">
                            <p class="biz-stat-label">Recomendacion</p>
                            <p class="biz-action-copy">Publica tu pagina, agrega al menos un producto y comparte el enlace por WhatsApp.</p>
                        </div>

                        <div class="biz-stat">
                            <p class="biz-stat-label">Estado actual</p>
                            <p class="biz-action-copy">
                                {{ $isPro ? 'Tu negocio ya tiene funciones PRO activas.' : 'Sigues en plan Base; revisa Suscripcion si necesitas herramientas premium.' }}
                            </p>
                        </div>
                    </div>

                    <div class="biz-button-row">
                        <a href="{{ $publicUrl }}" target="_blank" class="biz-button">Abrir mi pagina</a>
                        <a href="{{ $productsUrl }}" class="biz-button-secondary">Ir a productos</a>
                        <a href="{{ $billingUrl }}" class="biz-button-secondary">Ver suscripcion</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
