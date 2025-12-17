<x-filament-widgets::widget>
    <x-filament::section>
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
            {{-- Añadir Producto --}}
            <a 
                href="{{ route('filament.app.resources.products.create', ['tenant' => $tenant?->slug]) }}"
                class="flex flex-col items-center justify-center gap-2 p-5 
                       bg-blue-50 dark:bg-blue-900/20 
                       hover:bg-blue-100 dark:hover:bg-blue-900/40
                       rounded-2xl transition-all
                       min-h-[100px]"
            >
                <div class="flex items-center justify-center w-12 h-12 bg-blue-500 text-white rounded-xl">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                    </svg>
                </div>
                <span class="text-sm font-medium text-blue-700 dark:text-blue-300 text-center">
                    Añadir Producto
                </span>
            </a>

            {{-- Estado del Negocio --}}
            <a 
                href="{{ route('filament.app.pages.edit-tenant-profile', ['tenant' => $tenant?->slug]) }}"
                class="flex flex-col items-center justify-center gap-2 p-5 
                       {{ $isOpen 
                           ? 'bg-emerald-50 dark:bg-emerald-900/20' 
                           : 'bg-red-50 dark:bg-red-900/20' }}
                       hover:opacity-80
                       rounded-2xl transition-all
                       min-h-[100px]"
            >
                <div class="flex items-center justify-center w-12 h-12 rounded-xl
                            {{ $isOpen 
                                ? 'bg-emerald-500 text-white' 
                                : 'bg-red-500 text-white' }}">
                    @if($isOpen)
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    @else
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                    </svg>
                    @endif
                </div>
                <span class="text-sm font-medium text-center
                             {{ $isOpen 
                                 ? 'text-emerald-700 dark:text-emerald-300' 
                                 : 'text-red-700 dark:text-red-300' }}">
                    {{ $isOpen ? 'Abierto' : 'Cerrado' }}
                </span>
            </a>

            {{-- Ver Página Pública --}}
            <a 
                href="{{ url('/' . $tenant?->slug) }}"
                target="_blank"
                class="flex flex-col items-center justify-center gap-2 p-5 
                       bg-purple-50 dark:bg-purple-900/20 
                       hover:bg-purple-100 dark:hover:bg-purple-900/40
                       rounded-2xl transition-all
                       min-h-[100px]"
            >
                <div class="flex items-center justify-center w-12 h-12 bg-purple-500 text-white rounded-xl">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                    </svg>
                </div>
                <span class="text-sm font-medium text-purple-700 dark:text-purple-300 text-center">
                    Ver mi Página
                </span>
            </a>

            {{-- Editar Perfil --}}
            <a 
                href="{{ route('filament.app.pages.edit-tenant-profile', ['tenant' => $tenant?->slug]) }}"
                class="flex flex-col items-center justify-center gap-2 p-5 
                       bg-gray-50 dark:bg-gray-700/50 
                       hover:bg-gray-100 dark:hover:bg-gray-700
                       rounded-2xl transition-all
                       min-h-[100px]"
            >
                <div class="flex items-center justify-center w-12 h-12 bg-gray-500 text-white rounded-xl">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    </svg>
                </div>
                <span class="text-sm font-medium text-gray-700 dark:text-gray-300 text-center">
                    Configuración
                </span>
            </a>
        </div>

        {{-- Estadística Rápida --}}
        <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700 flex items-center justify-center gap-4 text-sm text-gray-500">
            <span>🛍️ {{ $productsCount }} productos en tu catálogo</span>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
