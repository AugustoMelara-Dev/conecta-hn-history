<x-filament-widgets::widget>
    <x-filament::section>
        <div class="flex items-center justify-between gap-4">
            <div class="flex-1">
                <div class="flex items-center gap-2 mb-2">
                    <x-heroicon-m-exclamation-triangle class="w-6 h-6 text-warning-500" />
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">Estas perdiendo clientes</h2>
                </div>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                    Activa PRO para ampliar catalogo, reservas/reportes, campanas e IA operativa con activacion segura.
                </p>
                
                <div class="mt-4">
                    <div class="flex justify-between mb-1">
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Limite de Productos (Plan Gratuito)</span>
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ $productCount }} / {{ $limit }}</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2.5 dark:bg-gray-700">
                        <div class="bg-{{ $isLimitReached ? 'danger' : 'primary' }}-600 h-2.5 rounded-full" style="width: {{ $percentage }}%"></div>
                    </div>
                </div>
            </div>

            <div class="flex flex-col items-end gap-2">
                <x-filament::button
                    href="https://wa.me/50499999999?text=Quiero%20activar%20mi%20Plan%20PRO%20en%20Vito%20Business%20OS"
                    tag="a"
                    target="_blank"
                    color="success"
                    icon="heroicon-m-star"
                    class="w-full sm:w-auto"
                >
                    Activar Modo PRO
                </x-filament::button>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
