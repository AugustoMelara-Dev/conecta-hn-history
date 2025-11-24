<x-filament-widgets::widget>
    <x-filament::section>
        <div class="flex items-center justify-between gap-4">
            <div class="flex-1">
                <h2 class="text-lg font-bold text-gray-900 dark:text-white">Plan Gratuito</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Estás usando la versión gratuita de Conecta HN. Tienes un límite de {{ $limit }} productos.
                </p>
                
                <div class="mt-4">
                    <div class="flex justify-between mb-1">
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Uso de Productos</span>
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ $productCount }} / {{ $limit }}</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2.5 dark:bg-gray-700">
                        <div class="bg-primary-600 h-2.5 rounded-full" style="width: {{ $percentage }}%"></div>
                    </div>
                </div>
            </div>

            <div class="flex flex-col items-end gap-2">
                <x-filament::button
                    href="#"
                    tag="a"
                    color="warning"
                    icon="heroicon-m-star"
                >
                    Actualizar a PRO
                </x-filament::button>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
