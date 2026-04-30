<div class="biz-card">
    <div class="biz-grid-two">
        <div class="biz-stack">
            <div class="biz-button-row">
                <div class="biz-badge {{ $isOpen ? 'biz-badge-success' : 'biz-badge-warning' }}">
                    <span>{{ $isOpen ? 'Abierto' : 'Cerrado' }}</span>
                </div>

                <div class="biz-badge biz-badge-neutral">
                    <span>{{ $businessTypeLabel }}</span>
                </div>

                <div class="biz-badge biz-badge-neutral">
                    <span>{{ $segmentLabel }}</span>
                </div>
            </div>

            <div class="biz-stack">
                <p class="biz-kicker">Panel del negocio</p>
                <h1 class="biz-title">{{ $tenantName }}</h1>
                <p class="biz-heading">{{ $experienceTitle }}</p>
                <p class="biz-copy">{{ $experienceCopy }}</p>
                <p class="biz-muted">{{ $businessTypeDescription }}</p>

                @if ($segmentTeaser !== '')
                    <p class="biz-muted">{{ $segmentTeaser }}</p>
                @endif
            </div>

            @if ($businessTypeCapabilities !== [])
                <div class="biz-button-row">
                    @foreach ($businessTypeCapabilities as $capability)
                        <x-filament::badge color="success">{{ $capability }}</x-filament::badge>
                    @endforeach
                </div>
            @endif

            @if ($businessExamples !== [])
                <p class="biz-muted">
                    Ejemplos: {{ implode(', ', $businessExamples) }}.
                </p>
            @endif

            <div class="biz-metrics">
                @foreach ($focusMetrics as $metric)
                    <div class="biz-stat">
                        <p class="biz-stat-label">{{ $metric['label'] }}</p>
                        <p class="biz-stat-value">{{ $metric['value'] }}</p>
                        <p class="biz-stat-copy">{{ $metric['copy'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="biz-card-accent">
            <div class="biz-stack">
                <div>
                    <p class="biz-kicker">Onboarding por rubro</p>
                    <h2 class="biz-heading">{{ $onboardingTitle }}</h2>
                    <p class="biz-muted">
                        {{ $onboardingCopy }}
                    </p>
                </div>

                <div class="biz-actions">
                    @foreach ($quickActions as $action)
                        <a href="{{ $action['href'] }}" class="biz-action">
                            <p class="biz-action-title">{{ $action['title'] }}</p>
                            <p class="biz-action-copy">{{ $action['copy'] }}</p>
                        </a>
                    @endforeach
                </div>

                <div class="biz-button-row">
                    <a href="{{ $publicUrl }}" target="_blank" rel="noopener noreferrer" class="biz-button" aria-label="Abrir mi pagina en una pestana nueva">Abrir mi pagina</a>
                    <a href="{{ $profileUrl }}" class="biz-button-secondary">Configurar negocio</a>
                    <a href="{{ $growthUrl }}" class="biz-button-secondary">Centro de crecimiento</a>
                    <a href="{{ $operationsUrl }}" class="biz-button-secondary">Centro operativo</a>
                    <a href="{{ $copilotUrl }}" class="biz-button-secondary">Copiloto IA</a>
                    <a href="{{ $billingUrl }}" class="biz-button-secondary">Ver plan</a>
                </div>
            </div>
        </div>
    </div>
</div>
