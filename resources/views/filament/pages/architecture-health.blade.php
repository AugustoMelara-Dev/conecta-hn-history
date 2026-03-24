<x-filament-panels::page>
    @php
        $healthStatus = $this->summary['health_status'] ?? 'unknown';
    @endphp

    <div class="biz-shell">
        <div class="biz-card">
            <div class="biz-stack">
                <div>
                    <p class="biz-kicker">Salud técnica</p>
                    <h1 class="biz-title">Arquitectura y eventos</h1>
                    <p class="biz-copy">
                        Vista ejecutiva del estado arquitectónico para detectar incumplimientos antes de que se conviertan en deuda técnica costosa.
                    </p>
                </div>

                <div class="biz-metrics">
                    <div class="biz-stat">
                        <p class="biz-stat-label">Eventos totales</p>
                        <p class="biz-stat-value">{{ number_format($this->summary['total_events'] ?? 0) }}</p>
                        <p class="biz-stat-copy">Eventos revisados por el analizador</p>
                    </div>

                    <div class="biz-stat">
                        <p class="biz-stat-label">Cumplen</p>
                        <p class="biz-stat-value">{{ number_format($this->summary['compliant_count'] ?? 0) }}</p>
                        <p class="biz-stat-copy">Sin observaciones</p>
                    </div>

                    <div class="biz-stat">
                        <p class="biz-stat-label">Incumplimientos</p>
                        <p class="biz-stat-value">{{ number_format($this->summary['non_compliant_count'] ?? 0) }}</p>
                        <p class="biz-stat-copy">Eventos con alertas</p>
                    </div>

                    <div class="biz-stat">
                        <p class="biz-stat-label">Cumplimiento</p>
                        <p class="biz-stat-value">{{ $this->summary['compliance_percentage'] ?? 0 }}%</p>
                        <p class="biz-stat-copy">Porcentaje general</p>
                    </div>
                </div>
            </div>
        </div>

        @if ($healthStatus === 'healthy')
            <div class="biz-card-accent">
                <div class="biz-stack">
                    <div class="biz-badge biz-badge-success">Estado saludable</div>
                    <p class="biz-copy">Todos los eventos revisados cumplen la regla arquitectonica configurada.</p>
                </div>
            </div>
        @elseif ($healthStatus === 'warning')
            <div class="biz-card-warning">
                <div class="biz-stack">
                    <div class="biz-badge biz-badge-warning">Atención requerida</div>
                    <p class="biz-copy">Hay eventos con observaciones. Conviene revisarlos antes de seguir creciendo la base de código.</p>
                </div>
            </div>
        @else
            <div class="biz-card-warning">
                <div class="biz-stack">
                    <div class="biz-badge biz-badge-warning">Sin diagnóstico estable</div>
                    <p class="biz-copy">No se pudo construir un reporte confiable o existen violaciones críticas pendientes.</p>
                </div>
            </div>
        @endif

        @if (! empty($this->violations))
            <div class="biz-card">
                <div class="biz-stack">
                    <div>
                        <p class="biz-kicker">Observaciones</p>
                        <h2 class="biz-heading">Incidencias encontradas</h2>
                    </div>

                    <div class="biz-list">
                        @foreach ($this->violations as $violation)
                            <div class="biz-stat" style="{{ ($violation['severity'] ?? 'warning') === 'critical' ? 'border-color: rgba(244, 63, 94, 0.28); background: rgba(244, 63, 94, 0.10);' : 'border-color: rgba(245, 158, 11, 0.24); background: rgba(245, 158, 11, 0.10);' }}">
                                <p class="biz-action-title">{{ $violation['event'] ?? 'Evento' }}</p>
                                <p class="biz-action-copy">
                                    {{ $violation['rule'] ?? 'Regla' }}: {{ $violation['message'] ?? 'Sin detalle' }}
                                </p>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        <div class="biz-card">
            <div class="biz-stack">
                <div>
                    <p class="biz-kicker">Detalle</p>
                        <h2 class="biz-heading">Revisión por evento</h2>
                    <p class="biz-muted">
                        Lista de eventos y estado de cumplimiento según las reglas activas.
                    </p>
                </div>

                <div class="biz-table">
                    <table class="biz-table-grid">
                        <thead>
                            <tr class="biz-table-head-row">
                                <th class="biz-table-head-cell">Evento</th>
                                <th class="biz-table-head-cell">Estado</th>
                                <th class="biz-table-head-cell">Reglas afectadas</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($this->events as $class => $compliance)
                                <tr class="biz-table-row">
                                    <td class="biz-table-cell biz-table-cell-mono">{{ class_basename((string) $class) }}</td>
                                    <td class="biz-table-cell">
                                        @if (($compliance['is_compliant'] ?? false) === true)
                                            <x-filament::badge color="success">Cumple</x-filament::badge>
                                        @else
                                            <x-filament::badge color="danger">
                                                {{ $compliance['violation_count'] ?? 0 }} observaciones
                                            </x-filament::badge>
                                        @endif
                                    </td>
                                    <td class="biz-table-cell biz-table-cell-muted">
                                        {{ implode(', ', array_keys($compliance['violations'] ?? [])) ?: 'Sin incidencias' }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="biz-table-cell">
                                        @include('filament.app.components.table-empty-state', [
                                            'heading' => 'Todavía no hay datos para mostrar',
                                            'description' => 'Cuando el analizador tenga eventos procesados o reglas activas, el detalle aparecerá aquí.',
                                        ])
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <p class="biz-muted">
                    Última revisión: {{ $this->summary['last_checked_at'] ?? now()->format('Y-m-d H:i:s') }}
                </p>
            </div>
        </div>
    </div>
</x-filament-panels::page>
