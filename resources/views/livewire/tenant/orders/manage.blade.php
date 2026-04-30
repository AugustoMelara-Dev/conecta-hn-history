{{--
    Orders Management - Tiered UI
    
    PRO: Kanban workflow board
    FREE: Standard data table
    BOTH: POS Lite creation modal
--}}

<div class="min-h-screen bg-gray-50">
    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    {{-- HEADER --}}
    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    <header class="bg-white border-b border-gray-200 sticky top-0 z-40">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <div class="flex items-center gap-3">
                    <h1 class="text-xl font-bold text-gray-900">Gestión de Pedidos</h1>
                    <span class="px-2 py-1 bg-gray-100 text-gray-600 text-xs font-medium rounded-full">
                        Hoy: {{ $this->orders->count() }}
                    </span>
                </div>
                <button
                    wire:click="$set('isCreating', true)"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-xl shadow-lg shadow-blue-500/25 transition-colors"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Registrar Venta
                </button>
            </div>
        </div>
    </header>

    {{-- Admin Navigation --}}
    <x-ui.admin-nav />

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        @if($this->isPro)
            {{-- ═══════════════════════════════════════════════════════════════ --}}
            {{-- KANBAN VIEW (PRO) --}}
            {{-- ═══════════════════════════════════════════════════════════════ --}}
            <div class="overflow-x-auto pb-4">
                <div class="flex gap-4 min-w-max">
                    {{-- PENDING Column --}}
                    <div class="w-80 flex-shrink-0">
                        <div class="bg-amber-50 rounded-xl p-4">
                            <div class="flex items-center justify-between mb-3">
                                <h3 class="font-semibold text-amber-800">⏳ Pendientes</h3>
                                <span class="px-2 py-0.5 bg-amber-200 text-amber-800 text-xs font-medium rounded-full">
                                    {{ $this->kanbanData['pending']->count() }}
                                </span>
                            </div>
                            <div class="space-y-3 max-h-[60vh] overflow-y-auto">
                                @forelse($this->kanbanData['pending'] as $order)
                                    @include('livewire.tenant.orders.partials.kanban-card', ['order' => $order, 'nextAction' => 'confirmed'])
                                @empty
                                    <p class="text-sm text-amber-600 text-center py-4">Sin pedidos pendientes</p>
                                @endforelse
                            </div>
                        </div>
                    </div>

                    {{-- PROCESSING Column --}}
                    <div class="w-80 flex-shrink-0">
                        <div class="bg-blue-50 rounded-xl p-4">
                            <div class="flex items-center justify-between mb-3">
                                <h3 class="font-semibold text-blue-800">🔥 Preparando</h3>
                                <span class="px-2 py-0.5 bg-blue-200 text-blue-800 text-xs font-medium rounded-full">
                                    {{ $this->kanbanData['processing']->count() }}
                                </span>
                            </div>
                            <div class="space-y-3 max-h-[60vh] overflow-y-auto">
                                @forelse($this->kanbanData['processing'] as $order)
                                    @include('livewire.tenant.orders.partials.kanban-card', ['order' => $order, 'nextAction' => 'completed'])
                                @empty
                                    <p class="text-sm text-blue-600 text-center py-4">Sin pedidos en proceso</p>
                                @endforelse
                            </div>
                        </div>
                    </div>

                    {{-- COMPLETED Column --}}
                    <div class="w-80 flex-shrink-0">
                        <div class="bg-emerald-50 rounded-xl p-4">
                            <div class="flex items-center justify-between mb-3">
                                <h3 class="font-semibold text-emerald-800">✅ Completados</h3>
                                <span class="px-2 py-0.5 bg-emerald-200 text-emerald-800 text-xs font-medium rounded-full">
                                    {{ $this->kanbanData['completed']->count() }}
                                </span>
                            </div>
                            <div class="space-y-3 max-h-[60vh] overflow-y-auto">
                                @forelse($this->kanbanData['completed'] as $order)
                                    @include('livewire.tenant.orders.partials.kanban-card', ['order' => $order, 'nextAction' => null])
                                @empty
                                    <p class="text-sm text-emerald-600 text-center py-4">Sin pedidos completados</p>
                                @endforelse
                            </div>
                        </div>
                    </div>

                    {{-- CANCELLED Column --}}
                    <div class="w-80 flex-shrink-0">
                        <div class="bg-red-50 rounded-xl p-4">
                            <div class="flex items-center justify-between mb-3">
                                <h3 class="font-semibold text-red-800">❌ Cancelados</h3>
                                <span class="px-2 py-0.5 bg-red-200 text-red-800 text-xs font-medium rounded-full">
                                    {{ $this->kanbanData['cancelled']->count() }}
                                </span>
                            </div>
                            <div class="space-y-3 max-h-[60vh] overflow-y-auto">
                                @forelse($this->kanbanData['cancelled'] as $order)
                                    @include('livewire.tenant.orders.partials.kanban-card', ['order' => $order, 'nextAction' => null])
                                @empty
                                    <p class="text-sm text-red-600 text-center py-4">Sin cancelados</p>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @else
            {{-- ═══════════════════════════════════════════════════════════════ --}}
            {{-- TABLE VIEW (FREE) --}}
            {{-- ═══════════════════════════════════════════════════════════════ --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">#</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cliente</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($this->orders as $order)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    {{ $order->order_number ?? '#'.$order->id }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ $order->created_at->format('h:i A') }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ $order->customer_name ?? 'Sin nombre' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @php
                                        $statusColors = [
                                            'pending' => 'bg-yellow-100 text-yellow-800',
                                            'confirmed' => 'bg-blue-100 text-blue-800',
                                            'preparing' => 'bg-indigo-100 text-indigo-800',
                                            'completed' => 'bg-green-100 text-green-800',
                                            'delivered' => 'bg-emerald-100 text-emerald-800',
                                            'cancelled' => 'bg-red-100 text-red-800',
                                        ];
                                        $statusLabels = [
                                            'pending' => 'Pendiente',
                                            'confirmed' => 'Confirmado',
                                            'preparing' => 'Preparando',
                                            'completed' => 'Completado',
                                            'delivered' => 'Entregado',
                                            'cancelled' => 'Cancelado',
                                        ];
                                    @endphp
                                    <span class="px-2 py-1 text-xs font-medium rounded-full {{ $statusColors[$order->status] ?? 'bg-gray-100 text-gray-800' }}">
                                        {{ $statusLabels[$order->status] ?? $order->status }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-900">
                                    L. {{ number_format($order->total, 2) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    @if(in_array($order->status, ['pending', 'confirmed', 'preparing']))
                                        <button
                                            wire:click="advanceOrder({{ $order->id }})"
                                            class="text-blue-600 hover:text-blue-800 font-medium"
                                        >
                                            Avanzar →
                                        </button>
                                    @else
                                        <span class="text-gray-400">-</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                                    <p>No hay pedidos hoy</p>
                                    <p class="text-sm mt-1">Los pedidos aparecerán aquí</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Upsell Banner --}}
            <div class="mt-6 bg-gradient-to-r from-indigo-50 to-blue-50 rounded-xl p-4 border border-indigo-200">
                <div class="flex items-center gap-4">
                    <div class="p-3 bg-indigo-100 rounded-xl">
                        <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"/>
                        </svg>
                    </div>
                    <div class="flex-1">
                        <h3 class="font-semibold text-gray-900">💡 Tablero Kanban disponible en PRO</h3>
                        <p class="text-sm text-gray-600">Organiza tu cocina visualmente arrastrando pedidos entre columnas.</p>
                    </div>
                    <a href="/upgrade/{{ $tenant->slug }}" wire:navigate class="px-4 py-2 bg-indigo-600 text-white font-medium rounded-xl hover:bg-indigo-700 text-sm">
                        Activar PRO
                    </a>
                </div>
            </div>
        @endif
    </main>

    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    {{-- POS MODAL (BOTH PLANS) --}}
    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    @if($isCreating)
        <div class="fixed inset-0 z-50 overflow-y-auto">
            {{-- Backdrop --}}
            <div class="fixed inset-0 bg-gray-500/75 transition-opacity" wire:click="resetModal"></div>

            <div class="flex min-h-full items-center justify-center p-4">
                <div class="relative bg-white rounded-2xl shadow-xl max-w-2xl w-full p-6 transform transition-transform max-h-[90vh] overflow-y-auto">
                    {{-- Header --}}
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-xl font-bold text-gray-900">📦 Registrar Venta</h3>
                        <button wire:click="resetModal" class="p-2 text-gray-400 hover:text-gray-600 rounded-full hover:bg-gray-100">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        {{-- LEFT: Product Search --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Buscar Producto</label>
                            <input
                                wire:model.live.debounce.300ms="productSearch"
                                type="text"
                                placeholder="Escribe para buscar..."
                                class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            />

                            {{-- Product Results --}}
                            @if($this->availableProducts->isNotEmpty())
                                <div class="mt-2 border border-gray-200 rounded-xl divide-y divide-gray-100 max-h-48 overflow-y-auto">
                                    @foreach($this->availableProducts as $product)
                                        <button
                                            wire:click="addToCart({{ $product->id }})"
                                            class="w-full px-4 py-3 text-left hover:bg-gray-50 flex items-center justify-between"
                                        >
                                            <span class="font-medium text-gray-900">{{ $product->name }}</span>
                                            <span class="text-gray-500">L. {{ number_format($product->price, 2) }}</span>
                                        </button>
                                    @endforeach
                                </div>
                            @endif

                            {{-- Customer Info --}}
                            <div class="mt-4 space-y-3">
                                <input
                                    wire:model="customerName"
                                    type="text"
                                    placeholder="Nombre del cliente (opcional)"
                                    class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-xl text-sm"
                                />
                                <input
                                    wire:model="customerContact"
                                    type="text"
                                    placeholder="Teléfono (opcional)"
                                    class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-xl text-sm"
                                />
                                <textarea
                                    wire:model="orderNotes"
                                    rows="2"
                                    placeholder="Notas especiales..."
                                    class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-xl text-sm resize-none"
                                ></textarea>
                            </div>
                        </div>

                        {{-- RIGHT: Cart --}}
                        <div class="bg-gray-50 rounded-xl p-4">
                            <h4 class="font-semibold text-gray-900 mb-3">🛒 Carrito</h4>

                            @if(empty($cart))
                                <p class="text-sm text-gray-500 text-center py-8">Agrega productos para comenzar</p>
                            @else
                                <div class="space-y-2 max-h-48 overflow-y-auto">
                                    @foreach($cart as $item)
                                        <div class="flex items-center justify-between bg-white rounded-lg p-3">
                                            <div class="flex-1">
                                                <p class="font-medium text-gray-900 text-sm">{{ $item['name'] }}</p>
                                                <p class="text-xs text-gray-500">L. {{ number_format($item['price'], 2) }} c/u</p>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <button
                                                    wire:click="decrementItem({{ $item['product_id'] }})"
                                                    class="w-7 h-7 flex items-center justify-center bg-gray-100 rounded-lg text-gray-600 hover:bg-gray-200"
                                                >−</button>
                                                <span class="w-6 text-center font-medium">{{ $item['qty'] }}</span>
                                                <button
                                                    wire:click="incrementItem({{ $item['product_id'] }})"
                                                    class="w-7 h-7 flex items-center justify-center bg-gray-100 rounded-lg text-gray-600 hover:bg-gray-200"
                                                >+</button>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>

                                {{-- Total --}}
                                <div class="mt-4 pt-4 border-t border-gray-200">
                                    <div class="flex items-center justify-between">
                                        <span class="text-gray-600">Total:</span>
                                        <span class="text-2xl font-bold text-gray-900">L. {{ number_format($this->cartTotal, 2) }}</span>
                                    </div>
                                </div>
                            @endif

                            {{-- Submit Button --}}
                            <button
                                wire:click="createManualOrder"
                                wire:loading.attr="disabled"
                                @if(empty($cart)) disabled @endif
                                class="w-full mt-4 px-6 py-3 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded-xl shadow-lg shadow-emerald-500/25 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                <span wire:loading.remove>Registrar Venta</span>
                                <span wire:loading>Procesando...</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
