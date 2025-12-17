{{--
    Public Storefront - Customer-facing shop
    
    Features:
    - Hero with tenant info
    - Product grid (mobile-first)
    - Alpine.js Cart Store (PRO only)
    - LocalStorage persistence
    - WhatsApp checkout
--}}

<div x-data class="pb-24">
    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    {{-- HERO SECTION --}}
    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    <div class="bg-white shadow-sm border-b border-gray-100">
        <div class="max-w-3xl mx-auto px-4 py-6">
            <div class="flex items-center gap-4">
                {{-- Logo Placeholder (Initials) --}}
                <div class="w-16 h-16 rounded-full bg-gradient-to-br from-indigo-500 to-blue-600 flex items-center justify-center text-white font-bold text-2xl flex-shrink-0 shadow-lg">
                    {{ substr($tenant->name, 0, 1) }}
                </div>
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 leading-tight">{{ $tenant->name }}</h1>
                    @if($tenant->is_pro)
                        <div class="flex items-center gap-1 mt-1">
                            <svg class="w-4 h-4 text-emerald-500" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                            <span class="text-xs font-medium text-emerald-600">Negocio Verificado</span>
                        </div>
                    @else
                        <p class="text-xs text-gray-500 mt-1">Catálogo Digital</p>
                    @endif
                </div>
            </div>

            {{-- Description --}}
            @if($tenant->description)
                <p class="mt-4 text-sm text-gray-600">{{ $tenant->description }}</p>
            @endif
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    {{-- PRODUCT GRID --}}
    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    <div class="max-w-3xl mx-auto px-4 py-6">
        @if($products->isEmpty())
            <div class="text-center py-12">
                <div class="inline-flex items-center justify-center w-20 h-20 bg-gray-100 rounded-full mb-4">
                    <svg class="w-10 h-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                    </svg>
                </div>
                <p class="text-gray-500">Este negocio aún no tiene productos disponibles.</p>
            </div>
        @else
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                @foreach($products as $product)
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden flex sm:flex-col h-32 sm:h-auto group">
                        {{-- Image --}}
                        <div class="w-32 sm:w-full h-full sm:h-48 bg-gray-100 flex-shrink-0 relative overflow-hidden">
                            @if($product->image_path)
                                <img 
                                    src="{{ Storage::url($product->image_path) }}" 
                                    alt="{{ $product->name }}" 
                                    class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                                    loading="lazy"
                                >
                            @else
                                <div class="w-full h-full flex items-center justify-center text-gray-300">
                                    <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                    </svg>
                                </div>
                            @endif
                        </div>

                        {{-- Info --}}
                        <div class="flex-1 p-3 flex flex-col justify-between">
                            <div>
                                <h3 class="font-semibold text-gray-900 line-clamp-1">{{ $product->name }}</h3>
                                @if($product->description)
                                    <p class="text-xs text-gray-500 line-clamp-2 mt-1">{{ $product->description }}</p>
                                @endif
                            </div>
                            
                            <div class="flex items-center justify-between mt-2">
                                <span class="font-bold text-gray-900">L. {{ number_format($product->price, 2) }}</span>
                                
                                @if($tenant->is_pro)
                                    <button 
                                        @click="$store.cart.add({{ Js::from([
                                            'id' => $product->id,
                                            'name' => $product->name,
                                            'price' => $product->price,
                                            'image_path' => $product->image_path,
                                        ]) }})"
                                        class="p-2 bg-indigo-50 text-indigo-600 rounded-full hover:bg-indigo-100 active:scale-90 transition-all"
                                        title="Agregar al carrito"
                                    >
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                        </svg>
                                    </button>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- FREE PLAN WARNING --}}
    @if(!$tenant->is_pro)
        <div class="max-w-3xl mx-auto px-4 mt-4 pb-8 text-center">
            <div class="bg-gray-100 rounded-xl p-4 text-sm text-gray-500">
                <p>📋 Este negocio está en <strong>modo catálogo</strong>.</p>
                <p class="mt-1">Los pedidos en línea están deshabilitados.</p>
            </div>
        </div>
    @endif

    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    {{-- FLOATING CART BAR (Only PRO) --}}
    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    @if($tenant->is_pro)
        <div 
            x-cloak
            x-show="$store.cart.count > 0"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="translate-y-full opacity-0"
            x-transition:enter-end="translate-y-0 opacity-100"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="translate-y-0 opacity-100"
            x-transition:leave-end="translate-y-full opacity-0"
            class="fixed bottom-0 left-0 right-0 p-4 bg-white border-t border-gray-200 shadow-[0_-4px_20px_rgba(0,0,0,0.1)] z-50 safe-bottom"
        >
            <div class="max-w-3xl mx-auto flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-500">Total a pagar</p>
                    <p class="text-xl font-bold text-gray-900">L. <span x-text="$store.cart.total"></span></p>
                </div>
                <button 
                    @click="$store.cart.openModal = true"
                    class="bg-indigo-600 text-white px-6 py-3 rounded-xl font-semibold shadow-lg shadow-indigo-500/30 flex items-center gap-2 hover:bg-indigo-700 active:scale-95 transition-all"
                >
                    <span>Ver Pedido</span>
                    <span class="bg-white/20 px-2 py-0.5 rounded-full text-xs" x-text="$store.cart.count"></span>
                </button>
            </div>
        </div>

        {{-- ═══════════════════════════════════════════════════════════════════ --}}
        {{-- CART MODAL (Slide Over) --}}
        {{-- ═══════════════════════════════════════════════════════════════════ --}}
        <div 
            x-cloak
            x-show="$store.cart.openModal"
            class="relative z-50"
            role="dialog" 
            aria-modal="true"
        >
            {{-- Backdrop --}}
            <div 
                x-show="$store.cart.openModal"
                x-transition:enter="ease-in-out duration-300"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="ease-in-out duration-300"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                class="fixed inset-0 bg-black/50 backdrop-blur-sm"
                @click="$store.cart.openModal = false"
            ></div>

            <div class="fixed inset-0 overflow-hidden">
                <div class="absolute inset-0 overflow-hidden">
                    <div class="pointer-events-none fixed inset-y-0 right-0 flex max-w-full pl-10">
                        <div 
                            x-show="$store.cart.openModal"
                            x-transition:enter="transform transition ease-in-out duration-300"
                            x-transition:enter-start="translate-x-full"
                            x-transition:enter-end="translate-x-0"
                            x-transition:leave="transform transition ease-in-out duration-300"
                            x-transition:leave-start="translate-x-0"
                            x-transition:leave-end="translate-x-full"
                            class="pointer-events-auto w-screen max-w-md"
                        >
                            <div class="flex h-full flex-col bg-white shadow-xl">
                                {{-- Header --}}
                                <div class="flex items-center justify-between px-4 py-4 border-b border-gray-100">
                                    <h2 class="text-lg font-bold text-gray-900">Tu Pedido</h2>
                                    <button 
                                        @click="$store.cart.openModal = false" 
                                        class="p-2 text-gray-400 hover:text-gray-600 rounded-full hover:bg-gray-100"
                                    >
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                    </button>
                                </div>

                                {{-- Cart Items --}}
                                <div class="flex-1 overflow-y-auto px-4 py-4">
                                    <template x-if="$store.cart.items.length === 0">
                                        <div class="text-center py-12">
                                            <div class="inline-flex items-center justify-center w-16 h-16 bg-gray-100 rounded-full mb-4">
                                                <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                                                </svg>
                                            </div>
                                            <p class="text-gray-500">Tu carrito está vacío</p>
                                        </div>
                                    </template>

                                    <ul class="divide-y divide-gray-100">
                                        <template x-for="item in $store.cart.items" :key="item.id">
                                            <li class="flex py-4">
                                                {{-- Image --}}
                                                <div class="h-20 w-20 flex-shrink-0 overflow-hidden rounded-lg border border-gray-200 bg-gray-100">
                                                    <template x-if="item.image">
                                                        <img :src="'/storage/' + item.image" class="h-full w-full object-cover">
                                                    </template>
                                                    <template x-if="!item.image">
                                                        <div class="h-full w-full flex items-center justify-center text-gray-300">
                                                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                                            </svg>
                                                        </div>
                                                    </template>
                                                </div>

                                                {{-- Details --}}
                                                <div class="ml-4 flex flex-1 flex-col">
                                                    <div class="flex justify-between">
                                                        <h3 class="font-medium text-gray-900" x-text="item.name"></h3>
                                                        <p class="font-bold text-gray-900">L. <span x-text="(item.price * item.qty).toFixed(2)"></span></p>
                                                    </div>
                                                    
                                                    <div class="flex flex-1 items-end justify-between mt-2">
                                                        {{-- Quantity Controls --}}
                                                        <div class="flex items-center border border-gray-200 rounded-lg">
                                                            <button 
                                                                @click="$store.cart.decrement(item.id)" 
                                                                class="px-3 py-1.5 text-gray-600 hover:bg-gray-100 rounded-l-lg"
                                                            >−</button>
                                                            <span class="px-3 font-medium text-gray-900" x-text="item.qty"></span>
                                                            <button 
                                                                @click="$store.cart.increment(item.id)" 
                                                                class="px-3 py-1.5 text-gray-600 hover:bg-gray-100 rounded-r-lg"
                                                            >+</button>
                                                        </div>

                                                        <button 
                                                            @click="$store.cart.remove(item.id)" 
                                                            class="text-sm font-medium text-red-600 hover:text-red-700"
                                                        >
                                                            Eliminar
                                                        </button>
                                                    </div>
                                                </div>
                                            </li>
                                        </template>
                                    </ul>
                                </div>

                                {{-- Footer --}}
                                <div class="border-t border-gray-100 px-4 py-4 safe-bottom">
                                    <div class="flex justify-between text-lg font-bold text-gray-900 mb-4">
                                        <span>Total</span>
                                        <span>L. <span x-text="$store.cart.total"></span></span>
                                    </div>
                                    <p class="text-xs text-gray-500 mb-4">El envío se coordina por WhatsApp.</p>
                                    
                                    <button 
                                        @click="$store.cart.checkout()"
                                        :disabled="$store.cart.items.length === 0"
                                        class="w-full flex items-center justify-center gap-2 px-6 py-4 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl shadow-lg shadow-emerald-500/30 disabled:opacity-50 disabled:cursor-not-allowed transition-all"
                                    >
                                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/>
                                        </svg>
                                        Enviar Pedido por WhatsApp
                                    </button>

                                    <button 
                                        @click="$store.cart.openModal = false" 
                                        class="w-full mt-3 text-sm font-medium text-indigo-600 hover:text-indigo-700"
                                    >
                                        ← Seguir comprando
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    {{-- ALPINE STORE LOGIC --}}
    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    @push('scripts')
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.store('cart', {
                items: [],
                openModal: false,
                tenantId: '{{ $tenant->id }}',
                tenantName: '{{ $tenant->name }}',
                tenantPhone: '{{ $tenant->phone ?? "50499999999" }}',
                
                init() {
                    const saved = localStorage.getItem(`cart_${this.tenantId}`);
                    if (saved) {
                        try {
                            this.items = JSON.parse(saved);
                        } catch (e) {
                            this.items = [];
                        }
                    }
                },

                save() {
                    localStorage.setItem(`cart_${this.tenantId}`, JSON.stringify(this.items));
                },

                add(product) {
                    const existing = this.items.find(i => i.id === product.id);
                    if (existing) {
                        existing.qty++;
                    } else {
                        this.items.push({
                            id: product.id,
                            name: product.name,
                            price: parseFloat(product.price),
                            image: product.image_path,
                            qty: 1
                        });
                    }
                    this.save();
                },

                remove(id) {
                    this.items = this.items.filter(i => i.id !== id);
                    this.save();
                },

                increment(id) {
                    const item = this.items.find(i => i.id === id);
                    if (item) {
                        item.qty++;
                        this.save();
                    }
                },

                decrement(id) {
                    const item = this.items.find(i => i.id === id);
                    if (item) {
                        if (item.qty > 1) {
                            item.qty--;
                            this.save();
                        } else {
                            this.remove(id);
                        }
                    }
                },

                get count() {
                    return this.items.reduce((sum, item) => sum + item.qty, 0);
                },

                get total() {
                    return this.items.reduce((sum, item) => sum + (item.price * item.qty), 0).toFixed(2);
                },

                checkout() {
                    if (this.items.length === 0) return;

                    let text = `Hola *${this.tenantName}*, quiero hacer el siguiente pedido:\n\n`;
                    
                    this.items.forEach(item => {
                        text += `▪️ ${item.qty}x ${item.name} - L. ${(item.price * item.qty).toFixed(2)}\n`;
                    });
                    
                    text += `\n*Total: L. ${this.total}*`;
                    text += `\n\n📍 Mi dirección: [ESCRIBIR AQUÍ]`;
                    
                    const phone = this.tenantPhone.replace(/\D/g, '');
                    window.open(`https://wa.me/${phone}?text=${encodeURIComponent(text)}`, '_blank');
                    
                    // Clear cart after checkout
                    this.items = [];
                    this.save();
                    this.openModal = false;
                }
            });
        });
    </script>
    @endpush
</div>
