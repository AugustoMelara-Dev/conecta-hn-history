{{--
    Products Index - Catalog Grid
    
    Features:
    - Grid layout with product cards
    - Real-time search
    - Visibility toggle
    - Delete with confirmation
    - Empty state
--}}

<div class="min-h-screen bg-gray-50">
    {{-- Header --}}
    <header class="bg-white border-b border-gray-200 sticky top-0 z-40">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <div class="flex items-center gap-4">
                    <a href="/app/{{ $tenant->slug }}" wire:navigate class="text-gray-500 hover:text-gray-700">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                        </svg>
                    </a>
                    <h1 class="text-xl font-bold text-gray-900">Mis Productos</h1>
                </div>

                {{-- Quota Indicator --}}
                <div class="hidden md:flex items-center gap-3 text-sm">
                    <span class="text-gray-500">
                        {{ $this->limits['current_count'] }} / {{ $this->limits['limit'] }} productos
                    </span>
                    <div class="w-24 h-2 bg-gray-200 rounded-full overflow-hidden">
                        <div 
                            class="h-full {{ $this->limits['percentage'] >= 80 ? 'bg-red-500' : 'bg-blue-500' }} rounded-full"
                            style="width: {{ min($this->limits['percentage'], 100) }}%"
                        ></div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    {{-- Admin Navigation --}}
    <x-ui.admin-nav />

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {{-- Search and Actions Bar --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
            {{-- Search --}}
            <div class="relative flex-1 max-w-md">
                <div class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                </div>
                <input
                    wire:model.live.debounce.300ms="search"
                    type="text"
                    placeholder="Buscar productos..."
                    class="w-full pl-11 pr-4 py-2.5 bg-white border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                />
            </div>

            {{-- Create Button --}}
            @if($this->limits['can_create'])
                <a 
                    href="{{ route('tenant.products.create') }}" 
                    wire:navigate
                    class="inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-xl transition-all shadow-lg shadow-blue-500/25"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Nuevo Producto
                </a>
            @else
                <div class="relative group">
                    <button 
                        disabled
                        class="inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-gray-300 text-gray-500 font-semibold rounded-xl cursor-not-allowed"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        Nuevo Producto
                    </button>
                    {{-- Tooltip --}}
                    <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-3 py-2 bg-gray-900 text-white text-sm rounded-lg opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap">
                        Límite del plan alcanzado
                        <a href="/upgrade/{{ $tenant->slug }}" wire:navigate class="text-blue-300 hover:underline ml-1">Mejorar</a>
                    </div>
                </div>
            @endif
        </div>

        {{-- Products Grid --}}
        @if($this->products->count() > 0)
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                @foreach($this->products as $product)
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden group hover:shadow-md transition-shadow">
                        {{-- Image --}}
                        <div class="aspect-square bg-gray-100 relative overflow-hidden">
                            @if($product->image_path)
                                <img 
                                    src="{{ Storage::url($product->image_path) }}" 
                                    alt="{{ $product->name }}"
                                    class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                                />
                            @else
                                <div class="w-full h-full flex items-center justify-center text-gray-400">
                                    <svg class="w-16 h-16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                    </svg>
                                </div>
                            @endif

                            {{-- Visibility Badge --}}
                            <div class="absolute top-2 right-2">
                                @if($product->is_visible)
                                    <span class="px-2 py-1 bg-emerald-500 text-white text-xs font-medium rounded-full">Visible</span>
                                @else
                                    <span class="px-2 py-1 bg-gray-500 text-white text-xs font-medium rounded-full">Oculto</span>
                                @endif
                            </div>
                        </div>

                        {{-- Content --}}
                        <div class="p-4">
                            <h3 class="font-semibold text-gray-900 line-clamp-2 mb-1">{{ $product->name }}</h3>
                            <p class="text-lg font-bold text-blue-600">L. {{ number_format($product->price, 2) }}</p>

                            {{-- Actions --}}
                            <div class="mt-4 flex items-center gap-2">
                                {{-- Visibility Toggle --}}
                                <button 
                                    wire:click="toggleVisibility({{ $product->id }})"
                                    class="flex-1 py-2 px-3 text-sm font-medium rounded-lg transition-colors {{ $product->is_visible ? 'bg-gray-100 text-gray-600 hover:bg-gray-200' : 'bg-emerald-50 text-emerald-600 hover:bg-emerald-100' }}"
                                >
                                    {{ $product->is_visible ? 'Ocultar' : 'Mostrar' }}
                                </button>

                                {{-- Edit --}}
                                <a 
                                    href="{{ route('tenant.products.edit', $product) }}"
                                    wire:navigate
                                    class="p-2 bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-100 transition-colors"
                                >
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                    </svg>
                                </a>

                                {{-- Delete --}}
                                <button 
                                    wire:click="delete({{ $product->id }})"
                                    wire:confirm="¿Estás seguro de eliminar '{{ $product->name }}'? Esta acción no se puede deshacer."
                                    class="p-2 bg-red-50 text-red-600 rounded-lg hover:bg-red-100 transition-colors"
                                >
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Pagination --}}
            <div class="mt-8">
                {{ $this->products->links() }}
            </div>
        @else
            {{-- Empty State --}}
            <div class="text-center py-16">
                <div class="inline-flex items-center justify-center w-24 h-24 bg-gray-100 rounded-full mb-6">
                    <svg class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                    </svg>
                </div>
                <h3 class="text-xl font-semibold text-gray-900 mb-2">
                    @if($search)
                        No se encontraron productos
                    @else
                        Aún no tienes productos
                    @endif
                </h3>
                <p class="text-gray-500 mb-6">
                    @if($search)
                        Intenta con otro término de búsqueda
                    @else
                        Comienza añadiendo tu primer producto a la vitrina
                    @endif
                </p>
                @if(!$search && $this->limits['can_create'])
                    <a 
                        href="{{ route('tenant.products.create') }}" 
                        wire:navigate
                        class="inline-flex items-center justify-center gap-2 px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-xl transition-all shadow-lg shadow-blue-500/25"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        Crear mi primer producto
                    </a>
                @endif
            </div>
        @endif
    </main>
</div>
