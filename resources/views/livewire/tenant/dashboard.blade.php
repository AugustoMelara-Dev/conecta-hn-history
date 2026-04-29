<div class="min-h-screen bg-gray-50">
    {{-- Dependencia Externa: Chart.js (Solo PRO) con Fallback --}}
    @if(!$this->isFree)
        <script src="https://cdn.jsdelivr.net/npm/chart.js" 
                onerror="console.warn('ChartJS CDN failed, loading local fallback...');"></script>
    @endif

    {{-- HEADER --}}
    <header class="bg-white border-b border-gray-200 sticky top-0 z-40">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <div class="flex items-center gap-4">
                    <a href="/" wire:navigate class="text-xl font-bold text-gray-900">
                        Vito <span class="text-blue-600">Business OS</span>
                    </a>
                    <span class="text-gray-300">|</span>
                    <span class="font-semibold text-gray-700 truncate max-w-[200px]">{{ $tenant->name }}</span>
                    @if($tenant->is_pro)
                        <span class="px-2 py-0.5 bg-gradient-to-r from-emerald-500 to-teal-500 text-white text-xs font-bold rounded-full shadow-sm">
                            PRO ACTIVADO
                        </span>
                    @endif
                </div>
                {{-- Quick Actions --}}
                <div class="flex items-center gap-3">
                    <a href="/{{ $tenant->slug }}" target="_blank" class="text-sm text-gray-600 hover:text-blue-600 transition-colors flex items-center gap-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                        Ver Micrositio
                    </a>
                    @if($this->isFree)
                    <div class="h-6 w-px bg-gray-200"></div>
                    <a href="/admin" wire:navigate class="text-sm text-gray-600 hover:text-gray-900">Panel Completo →</a>
                    @endif
                </div>
            </div>
        </div>
    </header>

    {{-- Admin Navigation --}}
    <x-ui.admin-nav />

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        
        {{-- ══════════════════════════════════════════════════════════════════ --}}
        {{-- VISTA PRO: BUSINESS INTELLIGENCE --}}
        {{-- ══════════════════════════════════════════════════════════════════ --}}
        @if($tenant->is_pro)
            
            <div class="mb-8 flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Panel de Control PRO</h1>
                    <p class="text-gray-600">Analítica de rendimiento en tiempo real</p>
                </div>
                {{-- Estado de Suscripción --}}
                <div class="flex items-center gap-3 bg-white px-4 py-2 rounded-lg border border-gray-200 shadow-sm">
                    <div class="flex items-center gap-2">
                        <span class="relative flex h-3 w-3">
                          <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                          <span class="relative inline-flex rounded-full h-3 w-3 bg-emerald-500"></span>
                        </span>
                        <span class="text-sm font-medium text-gray-700">Activa</span>
                    </div>
                    <span class="text-gray-300">|</span>
                    <span class="text-xs text-gray-500">Renueva: {{ $this->subscription['next_billing_date'] }}</span>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                
                {{-- WIDGET HERO: ROI (Conversiones) --}}
                <div class="bg-white rounded-xl shadow-sm border-l-4 border-emerald-500 p-6 relative overflow-hidden group hover:shadow-md transition-shadow">
                    <div class="relative z-10">
                        <h3 class="text-gray-500 font-medium text-sm uppercase tracking-wider">Oportunidades de Venta</h3>
                        <div class="mt-2 flex items-baseline gap-2">
                            <span class="text-5xl font-bold text-emerald-600">{{ $this->stats['monthly_conversions'] }}</span>
                            <span class="text-gray-500 font-medium">clics</span>
                        </div>
                        <p class="mt-2 text-sm text-gray-600">Clientes potenciales (WhatsApp) últimos 30 días</p>
                    </div>
                    <div class="absolute right-0 bottom-0 opacity-10 transform translate-x-4 translate-y-4 group-hover:scale-110 transition-transform duration-500">
                        <svg class="w-32 h-32 text-emerald-600" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/></svg>
                    </div>
                </div>

                {{-- WIDGET: Visitas Totales --}}
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 hover:shadow-md transition-shadow">
                    <h3 class="text-gray-500 font-medium text-sm uppercase tracking-wider">Tráfico del Perfil</h3>
                    <div class="mt-2 flex items-baseline gap-2">
                        <span class="text-4xl font-bold text-gray-900">{{ number_format($this->stats['total_visits']) }}</span>
                        <span class="text-gray-500">visitas</span>
                    </div>
                    <div class="mt-4 pt-4 border-t border-gray-50 grid grid-cols-2 gap-4">
                        <div>
                            <span class="block text-xl font-bold text-blue-600">{{ $this->stats['visits_today'] }}</span>
                            <span class="text-xs text-gray-400">Hoy</span>
                        </div>
                        <div>
                            <span class="block text-xl font-bold text-indigo-600">{{ $this->stats['visits_this_week'] }}</span>
                            <span class="text-xs text-gray-400">Esta semana</span>
                        </div>
                    </div>
                </div>

                {{-- WIDGET: Capacidad --}}
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 hover:shadow-md transition-shadow">
                     <h3 class="text-gray-500 font-medium text-sm uppercase tracking-wider">Inventario</h3>
                     <div class="mt-4">
                        <div class="flex justify-between text-sm mb-1">
                            <span class="font-medium text-gray-700">{{ $this->stats['product_count'] }} Productos</span>
                            <span class="text-gray-500">Límite: {{ $this->stats['product_limit'] }}</span>
                        </div>
                        <div class="h-2.5 bg-gray-100 rounded-full overflow-hidden">
                            <div class="h-full bg-blue-500 rounded-full" style="width: {{ $this->stats['product_percentage'] }}%"></div>
                        </div>
                        <p class="mt-2 text-xs text-gray-400">
                            Tienes {{ $this->stats['products_remaining'] }} espacios disponibles.
                        </p>
                        <a href="/admin/products" wire:navigate class="mt-4 block text-center text-sm font-medium text-blue-600 hover:text-blue-700 hover:underline">
                            Gestionar Catálogo →
                        </a>
                     </div>
                </div>
            </div>

            {{-- GRÁFICO DE TENDENCIA (Chart.js + Alpine) --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-8">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="font-bold text-gray-900">Tendencia: Visitas vs Conversiones (30 Días)</h3>
                    {{-- Leyenda Custom --}}
                    <div class="flex gap-4 text-sm">
                        <div class="flex items-center gap-2">
                            <span class="w-3 h-3 rounded-full bg-blue-400"></span>
                            <span class="text-gray-600">Visitas</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="w-3 h-3 rounded-full bg-emerald-500"></span>
                            <span class="text-gray-900 font-medium">Conversiones (WA)</span>
                        </div>
                    </div>
                </div>
                
                {{-- Contenedor del Canvas con wire:ignore para evitar re-render --}}
                <div 
                    class="h-72 w-full relative" 
                    wire:ignore
                    x-data="{
                        chart: null,
                        init() {
                            if (typeof Chart === 'undefined') return; // Fallback check

                            const ctx = this.$refs.canvas.getContext('2d');
                            const data = @js($this->chartData);

                            let gradient = ctx.createLinearGradient(0, 0, 0, 400);
                            gradient.addColorStop(0, 'rgba(59, 130, 246, 0.2)');
                            gradient.addColorStop(1, 'rgba(59, 130, 246, 0)');

                            this.chart = new Chart(ctx, {
                                type: 'line',
                                data: {
                                    labels: data.labels,
                                    datasets: [
                                        {
                                            label: 'Conversiones',
                                            data: data.conversions,
                                            borderColor: '#10B981',
                                            backgroundColor: '#10B981',
                                            borderWidth: 3,
                                            tension: 0.3,
                                            yAxisID: 'y1'
                                        },
                                        {
                                            label: 'Visitas',
                                            data: data.visits,
                                            borderColor: '#60A5FA',
                                            backgroundColor: gradient,
                                            borderWidth: 2,
                                            fill: true,
                                            tension: 0.4,
                                            yAxisID: 'y'
                                        }
                                    ]
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    interaction: { mode: 'index', intersect: false },
                                    plugins: { legend: { display: false } },
                                    scales: {
                                        y: {
                                            display: true,
                                            position: 'left',
                                            beginAtZero: true,
                                            grid: { display: false }
                                        },
                                        y1: {
                                            display: true,
                                            position: 'right',
                                            beginAtZero: true,
                                            grid: { color: '#f3f4f6' }
                                        },
                                        x: { grid: { display: false } }
                                    }
                                }
                            });
                        }
                    }"
                >
                    <canvas x-ref="canvas"></canvas>
                </div>
            </div>

        {{-- ══════════════════════════════════════════════════════════════════ --}}
        {{-- VISTA FREE: FRICCIÓN Y ESCASEZ --}}
        {{-- ══════════════════════════════════════════════════════════════════ --}}
        @else
             <div class="mb-6 bg-amber-50 border-l-4 border-amber-500 rounded-r-xl p-4 md:p-5">
                 <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div class="flex items-start gap-3">
                        <span class="text-2xl">⚠️</span>
                        <div>
                            <h3 class="font-bold text-amber-900">Estás en el Plan Gratuito</h3>
                             <p class="text-amber-800 text-sm mt-1">
                                Tus clientes <strong>NO pueden</strong> enviarte pedidos por WhatsApp 
                                y tu visibilidad está <strong>limitada</strong>.
                            </p>
                        </div>
                    </div>
                     <a href="/upgrade/{{ $tenant->slug }}" wire:navigate class="inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-amber-600 hover:bg-amber-700 text-white font-semibold rounded-xl transition-all shadow-lg shadow-amber-500/25 whitespace-nowrap">
                        Activar Ventas PRO
                    </a>
                 </div>
            </div>
            
            <div class="mb-8">
                 <h1 class="text-2xl md:text-3xl font-bold text-gray-900">¡Hola, {{ auth()->user()->name }}! 👋</h1>
                 <p class="text-gray-600 mt-1">Aquí está el resumen de tu negocio</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                {{-- Límite Productos --}}
                 <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="font-semibold text-gray-900">Productos</h3>
                        <span class="text-2xl">📦</span>
                    </div>
                    <div class="flex items-baseline gap-1 mb-4">
                        <span class="text-3xl font-bold text-gray-900">{{ $this->stats['product_count'] }}</span>
                        <span class="text-gray-500">/ {{ $this->stats['product_limit'] }}</span>
                    </div>
                     <div class="h-2.5 bg-gray-100 rounded-full overflow-hidden mb-3">
                        <div class="h-full rounded-full transition-all duration-500 {{ $this->stats['is_danger_zone'] ? 'bg-red-500 animate-pulse' : 'bg-blue-600' }}" style="width: {{ $this->stats['product_percentage'] }}%"></div>
                    </div>
                    <p class="text-sm {{ $this->stats['is_danger_zone'] ? 'text-red-600 font-medium' : 'text-gray-500' }}">
                         @if($this->stats['is_at_limit'])
                            ¡Límite alcanzado! Mejora tu plan para añadir más.
                        @else
                            Has usado {{ $this->stats['product_count'] }} de {{ $this->stats['product_limit'] }} productos.
                        @endif
                    </p>
                    <div class="mt-4 pt-4 border-t border-gray-100">
                        @if($this->stats['is_at_limit'])
                            <a href="/upgrade/{{ $tenant->slug }}" wire:navigate class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-gradient-to-r from-indigo-500 to-purple-500 hover:from-indigo-600 hover:to-purple-600 text-white font-semibold rounded-xl transition-all">
                                Activar Ilimitado
                            </a>
                        @else
                             <a href="/admin/products/create" wire:navigate class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-xl transition-all">
                                Añadir Producto
                            </a>
                        @endif
                    </div>
                 </div>
                 
                 {{-- Visitas / Validación --}}
                 <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="font-semibold text-gray-900">Visitas</h3>
                        <span class="text-2xl">👀</span>
                    </div>
                     <span class="text-3xl font-bold text-gray-900">{{ number_format($this->stats['total_visits']) }}</span>
                     <span class="text-gray-500">totales</span>
                      @if($this->stats['total_visits'] > 10)
                        <div class="mt-4 p-3 bg-amber-50 rounded-lg border border-amber-100">
                             <p class="text-sm text-amber-800">💡 <strong>{{ $this->stats['total_visits'] }} personas</strong> te han visto. Con PRO podrías venderles.</p>
                        </div>
                    @endif
                     <div class="mt-4 pt-4 border-t border-gray-50">
                        <p class="text-sm text-gray-500 text-center">
                            🔒 Estadísticas avanzadas en <a href="/upgrade/{{ $tenant->slug }}" wire:navigate class="text-indigo-600 font-medium hover:underline">Plan PRO</a>
                        </p>
                    </div>
                 </div>
            </div>
        @endif
    </main>
</div>
