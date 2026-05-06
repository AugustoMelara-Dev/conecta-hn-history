{{--
    MICROSITIO / TIENDA VIRTUAL
    
    Diseño Inmersivo "Super App"
    - Hero Image con Overlay
    - Navegación Sticky por Tabs
    - Grid de Productos Premium
    - Carrito Flotante Optimizado para Móvil
--}}

<div 
    class="min-h-screen bg-gray-50 pb-28 md:pb-12"
    x-data="{ 
        activeTab: 'products',
        scrolled: false,
        showCart: false
    }"
    @scroll.window="scrolled = window.scrollY > 50"
>
    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    {{-- HERO SECTION & HEADER --}}
    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    <div class="relative w-full">
        {{-- Cover Image --}}
        <div class="h-48 md:h-64 w-full bg-gray-900 overflow-hidden relative">
            @if($tenant->banner_path)
                <img src="{{ Storage::url($tenant->banner_path) }}" class="w-full h-full object-cover opacity-90" alt="Cover">
            @else
                <div class="w-full h-full bg-gradient-to-br from-indigo-900 via-slate-800 to-black"></div>
            @endif
            <div class="absolute inset-0 bg-gradient-to-t from-black/60 to-transparent"></div>
        </div>

        {{-- Top Nav (Back Button) --}}
        <div class="absolute top-0 left-0 right-0 p-4 flex items-center justify-between z-10">
            <a href="{{ route('pages.directory') }}" class="p-2 bg-black/30 backdrop-blur-md rounded-full text-white hover:bg-black/50 transition-all">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
            </a>
            @if($tenant->is_pro)
                <div class="px-3 py-1 bg-amber-400/90 backdrop-blur-md rounded-full text-amber-900 text-xs font-bold flex items-center gap-1 shadow-lg">
                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
                    VERIFICADO
                </div>
            @endif
        </div>

        {{-- Floating Profile Card --}}
        <div class="px-4 -mt-12 relative z-20">
            <div class="bg-white rounded-2xl shadow-xl p-4 flex items-start gap-4">
                {{-- Logo --}}
                <div class="w-20 h-20 rounded-2xl bg-white p-1 shadow-sm -mt-10 overflow-hidden ring-4 ring-white">
                    @if($tenant->logo_path)
                        <img src="{{ Storage::url($tenant->logo_path) }}" class="w-full h-full object-cover rounded-xl" alt="Logo">
                    @else
                        <div class="w-full h-full bg-indigo-600 rounded-xl flex items-center justify-center text-white font-bold text-2xl">
                            {{ substr($tenant->name, 0, 1) }}
                        </div>
                    @endif
                </div>

                <div class="flex-1 pt-1">
                    <h1 class="text-xl font-bold text-gray-900 leading-tight">{{ $tenant->name }}</h1>
                    @if($tenant->category)
                        <span class="inline-block mt-1 text-xs font-semibold px-2 py-0.5 rounded bg-gray-100 text-gray-600">
                            {{ $tenant->category->name }}
                        </span>
                    @endif
                    <div class="flex items-center gap-1 mt-2 text-sm text-gray-500">
                        <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        @if($this->isBusinessOpenNow($tenant))
                            <span class="text-emerald-600 font-medium">Abierto Ahora</span>
                        @else
                            <span class="text-red-500 font-medium">Cerrado</span>
                        @endif
                        <span class="mx-1">•</span>
                        <span class="text-amber-500 font-bold flex items-center gap-0.5">
                            <svg class="w-3 h-3 fill-current" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path></svg>
                            4.8
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    {{-- STICKY TABS --}}
    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    <div class="sticky top-0 bg-gray-50/95 backdrop-blur-sm z-40 border-b border-gray-200 mt-4">
        <div class="flex items-center justify-around max-w-3xl mx-auto">
            <button 
                @click="activeTab = 'products'" 
                class="flex-1 py-3 text-sm font-semibold border-b-2 transition-colors duration-300"
                :class="activeTab === 'products' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
            >
                Uds ({{ $products->count() }})
            </button>
            <button 
                @click="activeTab = 'info'" 
                class="flex-1 py-3 text-sm font-semibold border-b-2 transition-colors duration-300"
                :class="activeTab === 'info' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
            >
                Información
            </button>
            <button 
                @click="activeTab = 'location'" 
                class="flex-1 py-3 text-sm font-semibold border-b-2 transition-colors duration-300"
                :class="activeTab === 'location' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
            >
                Ubicación
            </button>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    {{-- CONTENT AREA --}}
    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    <div class="max-w-3xl mx-auto px-4 py-6">
        
        {{-- PRODUCTS TAB --}}
        <div x-show="activeTab === 'products'" x-transition.opacity.duration.300ms>
            @if($products->isEmpty())
                <div class="text-center py-12 bg-white rounded-2xl shadow-sm border border-gray-100">
                    <div class="inline-flex items-center justify-center w-20 h-20 bg-gray-50 rounded-full mb-4">
                        <svg class="w-10 h-10 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                    </div>
                    <p class="text-gray-500 font-medium">Catálogo vacío por ahora.</p>
                </div>
            @else
                <div class="grid grid-cols-2 gap-4">
                    @foreach($products as $product)
                        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden group hover:shadow-md transition-shadow">
                            <div class="aspect-square bg-gray-100 relative overflow-hidden">
                                @if($product->image_path)
                                    <img src="{{ Storage::url($product->image_path) }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                                @else
                                    <div class="w-full h-full flex items-center justify-center text-gray-300 bg-gray-50">
                                        <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    </div>
                                @endif
                                
                                @if($tenant->is_pro)
                                    <button 
                                        @click="$store.cart.add({{ Js::from(['id' => $product->id, 'name' => $product->name, 'price' => $product->price, 'image' => $product->image_path]) }});"
                                        class="absolute bottom-2 right-2 w-9 h-9 bg-white rounded-full shadow-lg flex items-center justify-center text-indigo-600 hover:bg-indigo-600 hover:text-white transition-colors"
                                    >
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                                    </button>
                                @endif
                            </div>
                            <div class="p-3">
                                <h3 class="font-semibold text-gray-900 text-sm line-clamp-2 leading-snug">{{ $product->name }}</h3>
                                <div class="mt-2 flex items-center justify-between">
                                    <span class="font-bold text-gray-900">L. {{ number_format($product->price, 2) }}</span>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- INFO TAB --}}
        <div x-show="activeTab === 'info'" x-transition.opacity.duration.300ms style="display: none;">
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 space-y-6">
                <div>
                    <h3 class="font-bold text-gray-900 mb-2">Sobre Nosotros</h3>
                    <p class="text-gray-600 text-sm leading-relaxed">{{ $tenant->description ?? 'Sin descripción disponible.' }}</p>
                </div>
                
                @if($tenant->address)
                <div>
                    <h3 class="font-bold text-gray-900 mb-2">Dirección</h3>
                    <div class="flex items-start gap-3">
                        <svg class="w-5 h-5 text-gray-400 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                        <p class="text-gray-600 text-sm">{{ $tenant->address }}</p>
                    </div>
                </div>
                @endif

                @if($tenant->phone || $tenant->email || $tenant->website_url)
                <div>
                    <h3 class="font-bold text-gray-900 mb-2">Contacto</h3>
                    <div class="space-y-3">
                        @if($tenant->phone)
                            <a href="tel:{{ $tenant->phone }}" class="flex items-center gap-3 text-sm text-gray-600 hover:text-indigo-600">
                                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
                                {{ $tenant->phone }}
                            </a>
                        @endif
                        @if($tenant->email)
                            <a href="mailto:{{ $tenant->email }}" class="flex items-center gap-3 text-sm text-gray-600 hover:text-indigo-600">
                                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                                {{ $tenant->email }}
                            </a>
                        @endif
                    </div>
                </div>
                @endif
            </div>
        </div>

        {{-- LOCATION TAB --}}
        <div x-show="activeTab === 'location'" x-transition.opacity.duration.300ms style="display: none;">
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden h-64 md:h-96 relative">
                 <iframe 
                    width="100%" 
                    height="100%" 
                    frameborder="0" 
                    scrolling="no" 
                    marginheight="0" 
                    marginwidth="0" 
                    src="https://maps.google.com/maps?q={{ urlencode($tenant->address ?? $tenant->city?->name ?? 'Honduras') }}&t=&z=15&ie=UTF8&iwloc=&output=embed"
                ></iframe>
            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    {{-- FLOATING CART (PRO ONLY) --}}
    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    @if($tenant->is_pro)
        <div 
            x-cloak
            x-show="$store.cart.count > 0"
            x-transition.slide.up
            class="fixed bottom-4 left-4 right-4 z-50 max-w-3xl mx-auto"
        >
            <button 
                @click="$store.cart.openModal = true"
                class="w-full bg-gray-900 text-white p-4 rounded-2xl shadow-2xl flex items-center justify-between hover:scale-[1.02] active:scale-95 transition-all"
            >
                <div class="flex items-center gap-3">
                    <div class="bg-emerald-500 text-white w-8 h-8 rounded-full flex items-center justify-center font-bold text-sm">
                        <span x-text="$store.cart.count"></span>
                    </div>
                    <div class="text-left">
                        <p class="text-xs text-gray-400 font-medium">Total a pagar</p>
                        <p class="font-bold">L. <span x-text="$store.cart.total"></span></p>
                    </div>
                </div>
                <div class="flex items-center gap-2 font-semibold text-emerald-400">
                    Ver Carrito
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"></path></svg>
                </div>
            </button>
        </div>

        {{-- Cart Modal Same as before but consistent design --}}
        {{-- For brevity, I'll reuse the existing modal logic but cleaner --}}
        <!-- CART MODAL -->
        <div x-show="$store.cart.openModal" style="display: none;" class="relative z-[60]" aria-labelledby="slide-over-title" role="dialog" aria-modal="true">
            <div x-show="$store.cart.openModal" x-transition.opacity class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm"></div>
            <div class="fixed inset-0 overflow-hidden">
                <div class="absolute inset-0 overflow-hidden">
                    <div class="pointer-events-none fixed inset-y-0 right-0 flex max-w-full pl-10">
                        <div x-show="$store.cart.openModal" 
                             x-transition:enter="transform transition ease-in-out duration-300 sm:duration-500" 
                             x-transition:enter-start="translate-x-full" 
                             x-transition:enter-end="translate-x-0" 
                             x-transition:leave="transform transition ease-in-out duration-300 sm:duration-500" 
                             x-transition:leave-start="translate-x-0" 
                             x-transition:leave-end="translate-x-full" 
                             class="pointer-events-auto w-screen max-w-md">
                            
                            <div class="flex h-full flex-col overflow-y-scroll bg-white shadow-xl">
                                <div class="px-6 py-6 border-b border-gray-100 flex items-center justify-between bg-gray-50/50">
                                    <h2 class="text-xl font-bold text-gray-900" id="slide-over-title">Tu Pedido</h2>
                                    <button @click="$store.cart.openModal = false" class="text-gray-400 hover:text-gray-500">
                                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                                    </button>
                                </div>

                                <div class="relative mt-6 flex-1 px-4 sm:px-6">
                                     <template x-if="$store.cart.items.length === 0">
                                        <div class="flex flex-col items-center justify-center h-64 text-center">
                                            <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                                                <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>
                                            </div>
                                            <p class="text-gray-500">Tu carrito está vacío.</p>
                                        </div>
                                    </template>
                                    
                                    <ul role="list" class="-my-6 divide-y divide-gray-200">
                                        <template x-for="item in $store.cart.items" :key="item.id">
                                            <li class="flex py-6">
                                                <div class="h-20 w-20 flex-shrink-0 overflow-hidden rounded-xl border border-gray-200 bg-gray-50">
                                                    <template x-if="item.image">
                                                        <img :src="'/storage/' + item.image" class="h-full w-full object-cover object-center">
                                                    </template>
                                                    <template x-if="!item.image">
                                                        <div class="h-full w-full flex items-center justify-center text-gray-300">
                                                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                                        </div>
                                                    </template>
                                                </div>
                                                <div class="ml-4 flex flex-1 flex-col">
                                                    <div>
                                                        <div class="flex justify-between text-base font-medium text-gray-900">
                                                            <h3 x-text="item.name"></h3>
                                                            <p class="ml-4">L. <span x-text="(item.price * item.qty).toFixed(2)"></span></p>
                                                        </div>
                                                    </div>
                                                    <div class="flex flex-1 items-end justify-between text-sm">
                                                        <div class="flex items-center border rounded-lg bg-gray-50">
                                                            <button @click="$store.cart.decrement(item.id)" class="px-3 py-1 text-gray-600 hover:bg-gray-200 rounded-l-lg">-</button>
                                                            <span class="px-2 font-medium" x-text="item.qty"></span>
                                                            <button @click="$store.cart.increment(item.id)" class="px-3 py-1 text-gray-600 hover:bg-gray-200 rounded-r-lg">+</button>
                                                        </div>
                                                        <button @click="$store.cart.remove(item.id)" type="button" class="font-medium text-red-500 hover:text-red-600 bg-red-50 px-3 py-1 rounded-lg">Eliminar</button>
                                                    </div>
                                                </div>
                                            </li>
                                        </template>
                                    </ul>
                                </div>

                                <div class="border-t border-gray-200 px-4 py-6 sm:px-6">
                                    <div class="flex justify-between text-lg font-bold text-gray-900 mb-4">
                                        <p>Total</p>
                                        <p>L. <span x-text="$store.cart.total"></span></p>
                                    </div>
                                    <button 
                                        @click="$store.cart.checkout()"
                                        :disabled="$store.cart.items.length === 0"
                                        class="flex w-full items-center justify-center rounded-xl border border-transparent bg-emerald-600 px-6 py-4 text-base font-medium text-white shadow-xl hover:bg-emerald-700 disabled:opacity-50 disabled:cursor-not-allowed transition-all gap-2"
                                    >
                                        <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/></svg>
                                        Pedir por WhatsApp
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
                tenantName: '{{ addslashes($tenant->name) }}',
                tenantPhone: '{{ $tenant->phone ?? "50499999999" }}',
                
                init() {
                    const saved = localStorage.getItem(`cart_${this.tenantId}`);
                    if (saved) {
                        try { this.items = JSON.parse(saved); } catch (e) { this.items = []; }
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
                            image: product.image,
                            qty: 1
                        });
                    }
                    this.save();
                    // Optional feedback
                    // alert('Producto agregado'); 
                },

                remove(id) {
                    this.items = this.items.filter(i => i.id !== id);
                    this.save();
                },

                increment(id) {
                    const item = this.items.find(i => i.id === id);
                    if (item) { item.qty++; this.save(); }
                },

                decrement(id) {
                    const item = this.items.find(i => i.id === id);
                    if (item) {
                        if (item.qty > 1) { item.qty--; this.save(); }
                        else { this.remove(id); }
                    }
                },

                get count() { return this.items.reduce((sum, item) => sum + item.qty, 0); },
                get total() { return this.items.reduce((sum, item) => sum + (item.price * item.qty), 0).toFixed(2); },

                checkout() {
                    if (this.items.length === 0) return;
                    let text = `Hola *${this.tenantName}*, quiero hacer el siguiente pedido:\n\n`;
                    this.items.forEach(item => { text += `▪️ ${item.qty}x ${item.name} - L. ${(item.price * item.qty).toFixed(2)}\n`; });
                    text += `\n*Total: L. ${this.total}*`;
                    text += `\n\n📍 Mi dirección: [ESCRIBIR AQUÍ]`;
                    const phone = this.tenantPhone.replace(/\D/g, '');
                    window.open(`https://wa.me/${phone}?text=${encodeURIComponent(text)}`, '_blank');
                    this.items = [];
                    this.save();
                    this.openModal = false;
                }
            });
        });
    </script>
    @endpush
</div>
