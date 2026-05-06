<x-filament-widgets::widget>
    <x-filament::section>
        <div class="relative overflow-hidden rounded-lg border-2 border-amber-500 bg-gradient-to-r from-amber-50 to-orange-50 dark:from-amber-950 dark:to-orange-950 p-6">
            <!-- Warning Icon -->
            <div class="absolute -right-8 -top-8 opacity-10">
                <svg class="w-40 h-40 text-amber-500" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                </svg>
            </div>

            <div class="relative">
                <!-- Header -->
                <div class="flex items-start gap-4 mb-4">
                    <div class="flex-shrink-0">
                        <div class="flex items-center justify-center w-12 h-12 bg-amber-500 rounded-full">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                            </svg>
                        </div>
                    </div>
                    <div class="flex-1 min-w-0">
                        <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2">
                            ⚡ Estás en el Plan Gratuito
                        </h3>
                        <p class="text-base text-gray-700 dark:text-gray-300 leading-relaxed">
                            <strong>Estás perdiendo clientes</strong> por no tener WhatsApp integrado, colores de marca personalizados, y capacidad ilimitada de productos. 
                            <span class="text-amber-700 dark:text-amber-400 font-semibold">¡El 73% de tus visitantes esperan contacto directo!</span>
                        </p>
                    </div>
                </div>

                <!-- Benefits Grid -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-6">
                    <div class="flex items-start gap-2 bg-white/50 dark:bg-gray-800/50 rounded-lg p-3">
                        <svg class="w-5 h-5 text-green-600 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                        <div class="text-sm">
                            <strong>Botón WhatsApp</strong>
                            <p class="text-gray-600 dark:text-gray-400">Pedidos directos</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-2 bg-white/50 dark:bg-gray-800/50 rounded-lg p-3">
                        <svg class="w-5 h-5 text-green-600 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                        <div class="text-sm">
                            <strong>Marca Personalizada</strong>
                            <p class="text-gray-600 dark:text-gray-400">Tu color y banner</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-2 bg-white/50 dark:bg-gray-800/50 rounded-lg p-3">
                        <svg class="w-5 h-5 text-green-600 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                        <div class="text-sm">
                            <strong>Productos Ilimitados</strong>
                            <p class="text-gray-600 dark:text-gray-400">Sin restricciones</p>
                        </div>
                    </div>
                </div>

                <!-- CTA -->
                <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                    <div class="text-sm text-gray-600 dark:text-gray-400">
                        Solo <strong class="text-2xl text-amber-600 dark:text-amber-400">L. 1,500</strong>/mes
                    </div>
                    <a href="{{ $this->getUpgradeUrl() }}" 
                       class="inline-flex items-center justify-center gap-2 px-6 py-3 text-base font-semibold text-white bg-gradient-to-r from-amber-500 to-orange-600 rounded-lg shadow-lg hover:shadow-xl hover:from-amber-600 hover:to-orange-700 transition-all duration-200 transform hover:scale-105">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                        Desbloquear Funciones PRO
                    </a>
                </div>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
