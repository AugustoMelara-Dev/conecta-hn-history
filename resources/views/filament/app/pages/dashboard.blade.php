<x-filament-panels::page>
    <main class="biz-shell" data-testid="panel-dashboard-page">
        <section class="biz-card biz-dashboard-hero" aria-labelledby="tenant-dashboard-title">
            <div class="biz-stack">
                <div class="biz-button-row">
                    <span class="biz-badge {{ $isOpen ? 'biz-badge-success' : 'biz-badge-warning' }}">
                        {{ $isOpen ? 'Abierto' : 'Cerrado' }}
                    </span>
                    <span class="biz-badge biz-badge-neutral">{{ $isPro ? 'Plan Pro' : 'Plan inicial' }}</span>
                </div>

                <div>
                    <p class="biz-kicker">Centro operativo</p>
                    <h1 id="tenant-dashboard-title" class="biz-title">{{ $tenantName }}</h1>
                    <p class="biz-copy">Gestiona catálogo, pedidos, clientes y crecimiento desde un solo panel.</p>
                </div>

                <div class="biz-button-row">
                    <a class="biz-button" href="{{ $links['orders'] ?? '#' }}">Revisar pedidos</a>
                    <a class="biz-button-secondary" href="{{ $links['public'] ?? '#' }}" target="_blank" rel="noopener">Ver página pública</a>
                </div>
            </div>
        </section>

        <section class="biz-metrics" aria-label="Resumen del negocio">
            @foreach ([
                ['label' => 'Productos', 'value' => $productsCount, 'href' => $links['products'] ?? '#'],
                ['label' => 'Pedidos', 'value' => $ordersCount, 'href' => $links['orders'] ?? '#'],
                ['label' => 'Contactos', 'value' => $contactsCount, 'href' => $links['contacts'] ?? '#'],
                ['label' => 'Seguidores', 'value' => $followersCount, 'href' => $links['public'] ?? '#'],
            ] as $metric)
                <a class="biz-stat biz-stat-link" href="{{ $metric['href'] }}">
                    <span class="biz-stat-label">{{ $metric['label'] }}</span>
                    <strong class="biz-stat-value">{{ number_format($metric['value']) }}</strong>
                </a>
            @endforeach
        </section>

        <section class="biz-card" aria-labelledby="dashboard-tools-title">
            <p class="biz-kicker">Herramientas</p>
            <h2 id="dashboard-tools-title" class="biz-heading">Continúa donde genera más valor</h2>
            <div class="biz-actions biz-actions-grid">
                @foreach ([
                    ['title' => 'Catálogo', 'copy' => 'Publica y actualiza productos.', 'href' => $links['products'] ?? '#'],
                    ['title' => 'Clientes', 'copy' => 'Da seguimiento a contactos y oportunidades.', 'href' => $links['contacts'] ?? '#'],
                    ['title' => 'Reportes', 'copy' => 'Consulta ventas y rendimiento.', 'href' => $links['reports'] ?? '#'],
                    ['title' => 'Marketing', 'copy' => 'Configura campañas y conversiones.', 'href' => $links['marketing'] ?? '#'],
                    ['title' => 'Configuración', 'copy' => 'Completa la información del negocio.', 'href' => $links['settings'] ?? '#'],
                    ['title' => 'Plan y consumo', 'copy' => 'Revisa límites y opciones del plan.', 'href' => $links['billing'] ?? '#'],
                ] as $action)
                    <a class="biz-action" href="{{ $action['href'] }}">
                        <span class="biz-action-title">{{ $action['title'] }}</span>
                        <span class="biz-action-copy">{{ $action['copy'] }}</span>
                    </a>
                @endforeach
            </div>
        </section>
    </main>
</x-filament-panels::page>
