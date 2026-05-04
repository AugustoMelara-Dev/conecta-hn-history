{{--
    Reports Index - Analytics Dashboard
    
    Features:
    - KPI cards with semantic colors
    - Chart.js combo (Bar + Line) with dual Y-axis
    - PRO blur overlay for FREE users
--}}

<div class="min-h-screen bg-gray-50">
    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    {{-- HEADER --}}
    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    <header class="bg-white border-b border-gray-200 sticky top-0 z-40">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col gap-3 py-3 sm:flex-row sm:items-center sm:justify-between sm:py-0 sm:min-h-16">
                <div class="flex items-center gap-3">
                    <h1 id="reports-title" class="text-xl font-bold text-gray-900">📊 Reportes</h1>
                    @if($this->isPro)
                        <span class="px-2 py-1 bg-emerald-100 text-emerald-700 text-xs font-medium rounded-full">PRO</span>
                    @endif
                </div>

                {{-- Date Range Filter --}}
                <div class="flex max-w-full items-center gap-2 overflow-x-auto pb-1 sm:pb-0" role="group" aria-label="Filtrar reportes por rango de fechas">
                    @foreach($this->rangeOptions as $value => $label)
                        <button
                            wire:click="$set('dateRange', '{{ $value }}')"
                            @if(!$this->isPro && $value !== 'today') disabled @endif
                            aria-pressed="{{ $dateRange === $value ? 'true' : 'false' }}"
                            aria-label="Mostrar reportes para {{ $label }}{{ !$this->isPro && $value !== 'today' ? ' (requiere PRO)' : '' }}"
                            class="px-3 py-1.5 text-sm font-medium rounded-lg transition-all
                                {{ $dateRange === $value 
                                    ? 'bg-blue-600 text-white' 
                                    : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}
                                {{ !$this->isPro && $value !== 'today' ? 'opacity-40 cursor-not-allowed' : '' }}"
                        >
                            {{ $label }}
                            @if(!$this->isPro && $value !== 'today')
                                🔒
                            @endif
                        </button>
                    @endforeach
                </div>
            </div>
        </div>
    </header>

    {{-- Admin Navigation --}}
    <x-ui.admin-nav />

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6" aria-labelledby="reports-title">
        {{-- ═══════════════════════════════════════════════════════════════════ --}}
        {{-- KPI CARDS --}}
        {{-- ═══════════════════════════════════════════════════════════════════ --}}
        <section class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8" aria-label="Resumen de indicadores del periodo">
            {{-- Revenue Card --}}
            <article class="bg-white rounded-xl shadow-sm border border-gray-100 p-6" aria-label="Ingresos del periodo">
                <div class="flex items-center gap-4">
                    <div class="p-3 bg-emerald-100 rounded-xl">
                        <svg class="w-6 h-6 text-emerald-600" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Ingresos</p>
                        <p class="text-3xl font-bold text-gray-900">L. {{ number_format($this->kpiStats['revenue'], 2) }}</p>
                    </div>
                </div>
            </article>

            {{-- Orders Card --}}
            <article class="bg-white rounded-xl shadow-sm border border-gray-100 p-6" aria-label="Pedidos del periodo">
                <div class="flex items-center gap-4">
                    <div class="p-3 bg-blue-100 rounded-xl">
                        <svg class="w-6 h-6 text-blue-600" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Pedidos</p>
                        <p class="text-3xl font-bold text-gray-900">{{ $this->kpiStats['orders'] }}</p>
                    </div>
                </div>
            </article>

            {{-- Average Ticket Card --}}
            <article class="bg-white rounded-xl shadow-sm border border-gray-100 p-6" aria-label="Ticket promedio del periodo">
                <div class="flex items-center gap-4">
                    <div class="p-3 bg-violet-100 rounded-xl">
                        <svg class="w-6 h-6 text-violet-600" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Ticket Promedio</p>
                        <p class="text-3xl font-bold text-gray-900">L. {{ number_format($this->kpiStats['avg_ticket'], 2) }}</p>
                    </div>
                </div>
            </article>
        </section>

        {{-- ═══════════════════════════════════════════════════════════════════ --}}
        {{-- CHART SECTION --}}
        {{-- ═══════════════════════════════════════════════════════════════════ --}}
        <section class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 sm:p-6 relative" aria-labelledby="reports-chart-title" aria-describedby="reports-chart-help">
            <div class="mb-4">
                <h2 id="reports-chart-title" class="font-semibold text-gray-900">Tendencia de Ventas</h2>
                <p id="reports-chart-help" class="mt-1 text-sm text-gray-500">Compara pedidos e ingresos para el rango seleccionado.</p>
            </div>

            @if($this->isPro)
                {{-- PRO: Full Chart --}}
                <div 
                    x-data="{ 
                        chart: null,
                        init() {
                            this.renderChart();
                        },
                        renderChart() {
                            const ctx = this.$refs.canvas.getContext('2d');
                            const data = @js($this->chartData);
                            
                            if (this.chart) {
                                this.chart.destroy();
                            }
                            
                            this.chart = new Chart(ctx, {
                                type: 'bar',
                                data: {
                                    labels: data.labels,
                                    datasets: [
                                        {
                                            type: 'line',
                                            label: 'Ingresos (L.)',
                                            data: data.revenue,
                                            borderColor: 'rgb(16, 185, 129)',
                                            backgroundColor: 'rgba(16, 185, 129, 0.1)',
                                            fill: true,
                                            tension: 0.4,
                                            yAxisID: 'y1',
                                            order: 1
                                        },
                                        {
                                            type: 'bar',
                                            label: 'Pedidos',
                                            data: data.orders,
                                            backgroundColor: 'rgba(59, 130, 246, 0.5)',
                                            borderColor: 'rgb(59, 130, 246)',
                                            borderWidth: 1,
                                            yAxisID: 'y',
                                            order: 2
                                        }
                                    ]
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    interaction: {
                                        mode: 'index',
                                        intersect: false
                                    },
                                    scales: {
                                        y: {
                                            type: 'linear',
                                            position: 'left',
                                            title: { display: true, text: 'Pedidos' },
                                            beginAtZero: true
                                        },
                                        y1: {
                                            type: 'linear',
                                            position: 'right',
                                            title: { display: true, text: 'Ingresos (L.)' },
                                            grid: { drawOnChartArea: false },
                                            beginAtZero: true
                                        }
                                    },
                                    plugins: {
                                        legend: { position: 'top' },
                                        tooltip: {
                                            callbacks: {
                                                label: function(context) {
                                                    if (context.dataset.label.includes('Ingresos')) {
                                                        return 'L. ' + context.raw.toFixed(2);
                                                    }
                                                    return context.raw + ' pedidos';
                                                }
                                            }
                                        }
                                    }
                                }
                            });
                        }
                    }"
                    wire:ignore
                    class="h-72 sm:h-96"
                >
                    <canvas x-ref="canvas" role="img" aria-label="Grafico de tendencia de ventas con ingresos y pedidos para el rango seleccionado."></canvas>
                </div>
            @else
                {{-- FREE: Blurred Chart with Upgrade CTA --}}
                <div class="relative">
                    {{-- Placeholder Chart (Blurred) --}}
                    <div class="h-72 sm:h-96 bg-gradient-to-br from-gray-100 to-gray-200 rounded-xl blur-sm opacity-50 flex items-center justify-center" aria-hidden="true">
                        <svg class="w-24 h-24 text-gray-300" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z"/>
                        </svg>
                    </div>

                    {{-- Upgrade Overlay --}}
                    <div class="absolute inset-0 flex items-center justify-center">
                        <div class="bg-white rounded-2xl shadow-xl p-8 text-center max-w-sm mx-auto">
                            <div class="w-16 h-16 bg-indigo-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                <svg class="w-8 h-8 text-indigo-600" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                </svg>
                            </div>
                            <h3 class="text-lg font-bold text-gray-900 mb-2">Analítica Histórica</h3>
                            <p class="text-sm text-gray-600 mb-4">
                                Desbloquea gráficos de tendencias, comparativas de 30 días y más insights para hacer crecer tu negocio.
                            </p>
                            <a 
                                href="/upgrade/{{ $tenant->slug }}" 
                                wire:navigate
                                aria-label="Activar PRO para ver analitica historica de reportes"
                                class="inline-flex items-center gap-2 px-6 py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded-xl shadow-lg shadow-indigo-500/25 transition-all"
                            >
                                <svg class="w-5 h-5" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                </svg>
                                Activar PRO
                            </a>
                        </div>
                    </div>
                </div>
            @endif
        </section>

        {{-- Quick Stats Summary --}}
        <div class="mt-6 bg-gray-100 rounded-xl p-4 text-center">
            <p class="text-sm text-gray-600">
                📅 Mostrando datos de: 
                <strong>
                    {{ match($dateRange) {
                        'today' => 'Hoy',
                        '7_days' => 'Últimos 7 días',
                        '30_days' => 'Últimos 30 días',
                        default => $dateRange
                    } }}
                </strong>
            </p>
        </div>
    </main>

    {{-- Chart.js CDN --}}
    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    @endpush
</div>
