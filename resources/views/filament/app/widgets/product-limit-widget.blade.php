{{--
    PRODUCT LIMIT WIDGET - Monitor Inteligente de Recursos
    
    Diseño bifurcado:
    - FREE: Barra de progreso con semáforo + CTA upgrade
    - PRO: Tarjeta VIP premium con beneficios
--}}

<x-filament-widgets::widget>
    @if($this->isPro)
    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    {{-- ESCENARIO B: EXPERIENCIA PRO (Validación y Estatus VIP) --}}
    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    <div class="relative overflow-hidden rounded-xl bg-gradient-to-r from-indigo-600 via-purple-600 to-indigo-700 p-6 shadow-lg">
        {{-- Patrón decorativo de fondo --}}
        <div class="absolute inset-0 opacity-10">
            <svg class="h-full w-full" viewBox="0 0 100 100" preserveAspectRatio="none">
                <defs>
                    <pattern id="grid" width="10" height="10" patternUnits="userSpaceOnUse">
                        <path d="M 10 0 L 0 0 0 10" fill="none" stroke="white" stroke-width="0.5"/>
                    </pattern>
                </defs>
                <rect width="100" height="100" fill="url(#grid)" />
            </svg>
        </div>

        <div class="relative flex items-center justify-between gap-6">
            {{-- Icono VIP --}}
            <div class="flex items-center gap-4">
                <div class="flex h-16 w-16 flex-shrink-0 items-center justify-center rounded-full bg-white/20 backdrop-blur-sm">
                    <x-heroicon-s-check-badge class="h-10 w-10 text-amber-300" />
                </div>

                <div class="text-white">
                    <div class="flex items-center gap-2">
                        <h3 class="text-xl font-bold">Tu cuenta es PRO</h3>
                        <span class="inline-flex items-center rounded-full bg-amber-400 px-2 py-0.5 text-xs font-bold text-amber-900">
                            ⭐ VIP
                        </span>
                    </div>
                    
                    <p class="mt-1 text-sm text-indigo-100">
                        Productos ilimitados • Prioridad en Búsquedas • Soporte VIP
                    </p>

                    @if($this->planExpiresAt)
                    <p class="mt-2 text-xs text-indigo-200">
                        <x-heroicon-m-calendar class="inline h-3 w-3" />
                        Próxima renovación: {{ $this->planExpiresAt }}
                    </p>
                    @endif
                </div>
            </div>

            {{-- Botón de gestión --}}
            <a 
                href="{{ $this->billingUrl }}"
                class="flex items-center gap-2 rounded-lg border border-white/30 bg-white/10 px-4 py-2 text-sm font-medium text-white backdrop-blur-sm transition-all hover:bg-white/20"
            >
                <x-heroicon-m-cog-6-tooth class="h-4 w-4" />
                Gestionar Suscripción
            </a>
        </div>
    </div>

    @else
    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    {{-- ESCENARIO A: EXPERIENCIA FREE (Ansiedad Positiva) --}}
    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            {{-- Información y barra de progreso --}}
            <div class="flex-1">
                <div class="mb-3 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <x-heroicon-o-cube class="h-5 w-5 text-gray-400" />
                        <h3 class="font-semibold text-gray-900 dark:text-white">
                            Uso de tu Plan Gratuito
                        </h3>
                    </div>
                    <span class="text-sm font-medium text-gray-600 dark:text-gray-300">
                        {{ $this->currentCount }} / {{ $this->limit }} productos
                    </span>
                </div>

                {{-- Barra de progreso con semáforo de colores --}}
                <div class="mb-2 h-4 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                    <div 
                        class="{{ $this->getProgressColor() }} h-full rounded-full transition-all duration-500 ease-out"
                        style="width: {{ $this->percentage }}%"
                    ></div>
                </div>

                {{-- Mensaje de estado dinámico --}}
                <p class="text-sm {{ $this->isInDangerZone() ? 'font-bold text-danger-600 dark:text-danger-400' : 'text-gray-500 dark:text-gray-400' }}">
                    {{ $this->getStatusMessage() }}
                </p>

                {{-- Estadísticas rápidas --}}
                <div class="mt-3 flex items-center gap-4 text-xs text-gray-400">
                    <span>
                        <x-heroicon-m-arrow-trending-up class="inline h-3 w-3" />
                        {{ $this->percentage }}% utilizado
                    </span>
                    <span>
                        <x-heroicon-m-plus-circle class="inline h-3 w-3" />
                        {{ max(0, $this->limit - $this->currentCount) }} disponibles
                    </span>
                </div>
            </div>

            {{-- CTA de Upgrade --}}
            <div class="flex flex-col items-center gap-2 lg:items-end">
                <a 
                    href="{{ $this->upgradeUrl }}"
                    class="inline-flex items-center gap-2 rounded-lg bg-gradient-to-r from-indigo-600 to-purple-600 px-5 py-3 text-sm font-bold text-white shadow-lg transition-all hover:from-indigo-700 hover:to-purple-700 hover:shadow-xl"
                >
                    🚀 Actualizar a PRO
                </a>
                <span class="text-xs text-gray-400">Productos ilimitados</span>
            </div>
        </div>

        {{-- Alerta de zona crítica --}}
        @if($this->isInDangerZone())
        <div class="mt-4 flex items-center gap-3 rounded-lg border border-danger-200 bg-danger-50 p-3 dark:border-danger-800 dark:bg-danger-900/30">
            <x-heroicon-m-exclamation-triangle class="h-5 w-5 flex-shrink-0 text-danger-600 dark:text-danger-400" />
            <div class="flex-1">
                <p class="text-sm font-medium text-danger-800 dark:text-danger-200">
                    ¡Tu catálogo está creciendo!
                </p>
                <p class="text-xs text-danger-600 dark:text-danger-400">
                    Actualiza a PRO para no perder impulso y desbloquear funciones premium.
                </p>
            </div>
        </div>
        @endif
    </div>
    @endif
</x-filament-widgets::widget>
