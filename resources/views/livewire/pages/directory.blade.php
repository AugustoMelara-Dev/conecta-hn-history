{{--
    DIRECTORY - Super App Experience
    
    Premium marketplace with app-like navigation.
    - App Navbar with Logo, Search, User Profile
    - Horizontal Category Tabs
    - Clean Card Design with Verification Badges
--}}

<div 
    class="min-h-screen bg-gray-50"
    x-data="{ 
        filtersOpen: @entangle('showFiltersDrawer'),
        userMenuOpen: false,
        scrolled: false
    }"
    @scroll.window="scrolled = window.scrollY > 10"
    @click.away="userMenuOpen = false"
>
    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    {{-- SUPER APP NAVBAR --}}
    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    <header 
        class="sticky top-0 z-50 transition-all duration-300 border-b"
        :class="scrolled ? 'bg-white shadow-sm border-gray-200' : 'bg-white border-gray-100'"
    >
        <div class="max-w-7xl mx-auto px-4 lg:px-6">
            <div class="flex items-center gap-4 lg:gap-6 h-16">
                
                {{-- LOGO + LOCATION (Left Zone) --}}
                <div class="flex items-center gap-4 flex-shrink-0">
                    {{-- Logo --}}
                    <a href="/" class="flex items-center gap-2 group">
                        <div class="w-9 h-9 bg-gradient-to-br from-indigo-600 to-violet-600 rounded-xl flex items-center justify-center shadow-lg shadow-indigo-500/20 group-hover:shadow-indigo-500/40 transition-shadow">
                            <span class="text-white font-bold text-lg">C</span>
                        </div>
                        <span class="hidden md:block font-bold text-xl text-gray-900">
                            Conecta<span class="text-indigo-600">HN</span>
                        </span>
                    </a>
                    
                    {{-- Location Picker --}}
                    <button 
                        @click="filtersOpen = true"
                        class="hidden sm:flex items-center gap-2 px-3 py-2 rounded-lg hover:bg-gray-100 transition-colors text-sm"
                    >
                        <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                        </svg>
                        <div class="text-left">
                            <span class="text-xs text-gray-500 block leading-none">Ubicación</span>
                            <span class="font-medium text-gray-900">{{ $selectedCityName ?? 'Toda Honduras' }}</span>
                        </div>
                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>
                </div>
                
                {{-- SEARCH BAR (Center Zone - Grows) --}}
                <div class="flex-1 max-w-xl">
                    <div class="relative">
                        <input 
                            type="text"
                            wire:model.live.debounce.300ms="search"
                            placeholder="Buscar negocios, productos..."
                            class="w-full h-11 pl-11 pr-4 
                                   bg-gray-100 border-0 
                                   rounded-xl text-gray-900
                                   placeholder:text-gray-400
                                   focus:ring-2 focus:ring-indigo-500/30 focus:bg-white
                                   transition-all"
                        >
                        <svg class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                        
                        {{-- Clear Button --}}
                        @if($search)
                        <button 
                            wire:click="$set('search', '')"
                            class="absolute right-3 top-1/2 -translate-y-1/2 p-1 hover:bg-gray-200 rounded-full transition-colors"
                        >
                            <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                        @endif
                    </div>
                </div>
                
                {{-- USER PROFILE (Right Zone) --}}
                <div class="flex items-center gap-3 flex-shrink-0">
                    @auth
                        {{-- User Menu Dropdown --}}
                        <div class="relative" x-data="{ open: false }">
                            <button 
                                @click="open = !open"
                                class="flex items-center gap-2 px-3 py-2 rounded-xl hover:bg-gray-100 transition-colors"
                            >
                                {{-- Avatar --}}
                                <div class="w-9 h-9 rounded-full bg-gradient-to-br from-indigo-500 to-violet-500 flex items-center justify-center text-white font-bold text-sm ring-2 ring-white shadow">
                                    @if(auth()->user()->avatar)
                                        <img src="{{ auth()->user()->avatar }}" alt="" class="w-full h-full rounded-full object-cover">
                                    @else
                                        {{ substr(auth()->user()->name, 0, 1) }}
                                    @endif
                                </div>
                                <div class="hidden md:block text-left">
                                    <span class="block text-sm font-semibold text-gray-900 truncate max-w-[120px]">
                                        {{ auth()->user()->name }}
                                    </span>
                                </div>
                                <svg class="w-4 h-4 text-gray-400 hidden md:block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </button>
                            
                            {{-- Dropdown Menu --}}
                            <div 
                                x-show="open"
                                x-cloak
                                x-transition:enter="transition ease-out duration-200"
                                x-transition:enter-start="opacity-0 scale-95"
                                x-transition:enter-end="opacity-100 scale-100"
                                x-transition:leave="transition ease-in duration-150"
                                x-transition:leave-start="opacity-100 scale-100"
                                x-transition:leave-end="opacity-0 scale-95"
                                @click.away="open = false"
                                class="absolute right-0 mt-2 w-56 bg-white rounded-xl shadow-xl ring-1 ring-gray-200 py-2 z-50"
                            >
                                {{-- User Info Header --}}
                                <div class="px-4 py-3 border-b border-gray-100">
                                    <p class="text-sm font-semibold text-gray-900">{{ auth()->user()->name }}</p>
                                    <p class="text-xs text-gray-500 truncate">{{ auth()->user()->email }}</p>
                                </div>
                                
                                {{-- Menu Items --}}
                                <div class="py-2">
                                    @if(auth()->user()->tenant)
                                        <a 
                                            href="/app/{{ auth()->user()->tenant->slug }}" 
                                            class="flex items-center gap-3 px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 transition-colors"
                                        >
                                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                            </svg>
                                            Mi Panel de Negocio
                                        </a>
                                    @endif
                                    
                                    @if(auth()->user()->hasRole('super_admin'))
                                        <a 
                                            href="/admin" 
                                            class="flex items-center gap-3 px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 transition-colors"
                                        >
                                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                            </svg>
                                            Panel Admin
                                        </a>
                                    @endif
                                </div>
                                
                                {{-- Logout --}}
                                <div class="border-t border-gray-100 pt-2">
                                    <form method="POST" action="{{ route('logout') }}">
                                        @csrf
                                        <button 
                                            type="submit"
                                            class="flex items-center gap-3 w-full px-4 py-2.5 text-sm text-red-600 hover:bg-red-50 transition-colors"
                                        >
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                                            </svg>
                                            Cerrar Sesión
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @else
                        {{-- Login Button for Guests --}}
                        <a 
                            href="/login" 
                            class="flex items-center gap-2 px-4 py-2.5 rounded-xl bg-gray-900 text-white text-sm font-semibold hover:bg-gray-800 transition-colors shadow-lg shadow-gray-900/10"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"></path>
                            </svg>
                            <span class="hidden sm:inline">Iniciar Sesión</span>
                        </a>
                    @endauth
                </div>
            </div>
        </div>
        
        {{-- ═══════════════════════════════════════════════════════════════════ --}}
        {{-- CATEGORY TABS (Horizontal Scroll) --}}
        {{-- ═══════════════════════════════════════════════════════════════════ --}}
        <div class="border-t border-gray-100 bg-white">
            <div class="max-w-7xl mx-auto px-4 lg:px-6">
                <div class="flex items-center gap-2 py-3 overflow-x-auto scrollbar-hide">
                    {{-- "Ver Todos" Tab --}}
                    <button 
                        wire:click="$set('category', '')"
                        class="flex-shrink-0 flex items-center gap-2 px-4 py-2 rounded-full text-sm font-medium whitespace-nowrap transition-all
                               {{ empty($category) 
                                   ? 'bg-gray-900 text-white' 
                                   : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path>
                        </svg>
                        Ver Todos
                    </button>
                    
                    {{-- Dynamic Category Tabs --}}
                    @foreach($categories as $cat)
                    <button 
                        wire:click="$set('category', '{{ $cat->slug }}')"
                        class="flex-shrink-0 flex items-center gap-2 px-4 py-2.5 rounded-full text-sm font-medium whitespace-nowrap transition-all border
                               {{ $category === $cat->slug 
                                   ? 'bg-gray-900 text-white border-gray-900 shadow-lg shadow-gray-900/20' 
                                   : 'bg-white text-gray-700 border-gray-200 hover:bg-gray-50 hover:border-gray-300' }}"
                    >
                        @switch($cat->icon)
                            @case('car')
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 19H5a2 2 0 01-2-2v-5a2 2 0 012-2h3.9a2 2 0 011.694 1.11L12 14h6a2 2 0 002-2V7a2 2 0 00-2-2h-3"></path><path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                @break
                            @case('utensils')
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
                                @break
                            @case('laptop')
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                                @break
                            @case('activity')
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path></svg>
                                @break
                            @case('home')
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                                @break
                            @case('shirt')
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>
                                @break
                            @case('briefcase')
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                                @break
                            @default
                                <span class="text-xs uppercase bg-gray-200 px-1 rounded font-bold">{{ substr($cat->name, 0, 1) }}</span>
                        @endswitch
                        {{ $cat->name }}
                    </button>
                    @endforeach
                    
                    {{-- Open Now Toggle --}}
                    <button 
                        wire:click="toggleOpenNow"
                        class="flex-shrink-0 flex items-center gap-2 px-4 py-2 rounded-full text-sm font-medium whitespace-nowrap transition-all ml-auto
                               {{ $isOpen 
                                   ? 'bg-emerald-500 text-white' 
                                   : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}"
                    >
                        <span class="w-2 h-2 rounded-full {{ $isOpen ? 'bg-white animate-pulse' : 'bg-emerald-500' }}"></span>
                        Abierto Ahora
                    </button>
                </div>
            </div>
        </div>
    </header>

    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    {{-- MAIN CONTENT --}}
    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    <main class="max-w-7xl mx-auto px-4 lg:px-6 py-8">
        
        {{-- Page Header --}}
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">
                    @if($search)
                        Resultados para "{{ $search }}"
                    @elseif($category)
                        {{ $selectedCategoryName ?? 'Categoría' }}
                    @else
                        Descubre lo mejor de Honduras
                    @endif
                </h1>
                <p class="text-sm text-gray-500 mt-1">
                    <span class="text-indigo-600 font-semibold">{{ $tenants->total() }}</span> negocios encontrados
                </p>
            </div>
            
            {{-- Sort Dropdown --}}
            <div class="relative" x-data="{ open: false }">
                <button 
                    @click="open = !open"
                    class="flex items-center gap-2 px-4 py-2 rounded-lg border border-gray-200 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors"
                >
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h13M3 8h9m-9 4h6m4 0l4-4m0 0l4 4m-4-4v12"></path>
                    </svg>
                    {{ match($sort) {
                        'newest' => 'Más recientes',
                        'rating' => 'Mejor valorados',
                        default => 'Más relevantes'
                    } }}
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                    </svg>
                </button>
                
                <div 
                    x-show="open"
                    x-cloak
                    @click.away="open = false"
                    class="absolute right-0 mt-2 w-48 bg-white rounded-xl shadow-xl ring-1 ring-gray-200 py-2 z-40"
                >
                    <button wire:click="$set('sort', 'relevance')" @click="open = false" class="block w-full px-4 py-2 text-left text-sm {{ $sort === 'relevance' ? 'text-indigo-600 bg-indigo-50' : 'text-gray-700 hover:bg-gray-50' }}">
                        Más relevantes
                    </button>
                    <button wire:click="$set('sort', 'newest')" @click="open = false" class="block w-full px-4 py-2 text-left text-sm {{ $sort === 'newest' ? 'text-indigo-600 bg-indigo-50' : 'text-gray-700 hover:bg-gray-50' }}">
                        Más recientes
                    </button>
                    <button wire:click="$set('sort', 'rating')" @click="open = false" class="block w-full px-4 py-2 text-left text-sm {{ $sort === 'rating' ? 'text-indigo-600 bg-indigo-50' : 'text-gray-700 hover:bg-gray-50' }}">
                        Mejor valorados
                    </button>
                </div>
            </div>
        </div>

        {{-- ═══════════════════════════════════════════════════════════════════ --}}
        {{-- BUSINESS CARDS GRID --}}
        {{-- ═══════════════════════════════════════════════════════════════════ --}}
        @if($tenants->count() > 0)
        <div 
            class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5"
            wire:loading.class="opacity-50"
            wire:target="search, category, city, isOpen, sort"
        >
            @foreach($tenants as $tenant)
                @php
                    $isBusinessOpen = $this->isBusinessOpenNow($tenant);
                @endphp
                <a 
                    href="{{ route('tenant.public.show', ['tenant' => $tenant->slug]) }}"
                    wire:key="tenant-{{ $tenant->id }}"
                    class="group bg-white rounded-2xl border border-gray-200 hover:border-gray-300 overflow-hidden
                           hover:shadow-xl hover:shadow-gray-200/50 transition-all duration-300"
                >
                    {{-- Card Header with Badges --}}
                    <div class="relative p-4 pb-0">
                        {{-- PRO Badge --}}
                        @if($tenant->is_pro)
                        <div class="absolute top-4 right-4">
                            <span class="inline-flex items-center gap-1 px-2 py-1 text-xs font-bold text-emerald-700 bg-emerald-100 rounded-full">
                                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                </svg>
                                VERIFICADO
                            </span>
                        </div>
                        @endif
                        
                        {{-- Logo/Icon --}}
                        <div class="w-16 h-16 rounded-xl bg-gray-100 flex items-center justify-center mb-4 overflow-hidden">
                            @if($tenant->logo_path)
                                <img 
                                    src="{{ asset('storage/' . $tenant->logo_path) }}" 
                                    alt="{{ $tenant->name }}"
                                    class="w-full h-full object-cover"
                                    loading="lazy"
                                >
                            @else
                                <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                </svg>
                            @endif
                        </div>
                        
                        {{-- Category Pill --}}
                        @if($tenant->category)
                        <span 
                            class="inline-block px-2.5 py-1 text-xs font-semibold rounded-lg mb-3"
                            style="background-color: {{ $tenant->category->color ?? '#f97316' }}20; color: {{ $tenant->category->color ?? '#f97316' }}"
                        >
                            {{ $tenant->category->name }}
                        </span>
                        @endif
                    </div>
                    
                    {{-- Card Body --}}
                    <div class="px-4 pb-4">
                        {{-- Business Name --}}
                        <h3 class="font-bold text-gray-900 text-lg group-hover:text-indigo-600 transition-colors flex items-center gap-2">
                            {{ $tenant->name }}
                            @if($tenant->is_pro)
                            <svg class="w-5 h-5 text-emerald-500" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                            </svg>
                            @endif
                        </h3>
                        
                        {{-- Description --}}
                        <p class="text-sm text-gray-500 mt-1 line-clamp-2">
                            {{ $tenant->description ?? 'Sin descripción disponible.' }}
                        </p>
                    </div>
                    
                    {{-- Card Footer --}}
                    <div class="px-4 py-3 border-t border-gray-100 flex items-center justify-between">
                        {{-- Location --}}
                        @if($tenant->city)
                        <span class="flex items-center gap-1.5 text-sm text-gray-500">
                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                            </svg>
                            {{ $tenant->city->name }}
                        </span>
                        @endif
                        
                        {{-- Rating --}}
                        <span class="flex items-center gap-1 text-sm font-semibold text-amber-600">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path>
                            </svg>
                            {{ $tenant->rating ? number_format($tenant->rating, 1) : '4.8' }}
                        </span>
                    </div>
                </a>
            @endforeach
        </div>
        
        {{-- Infinite Scroll Trigger --}}
        @if($tenants->hasMorePages())
        <div 
            x-data
            x-intersect.full="$wire.loadMore()"
            class="py-8"
        >
            <div wire:loading wire:target="loadMore" class="flex justify-center">
                <div class="flex items-center gap-2 text-gray-500">
                    <svg class="animate-spin h-5 w-5" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span class="text-sm">Cargando más negocios...</span>
                </div>
            </div>
        </div>
        @endif
        
        @else
        {{-- Empty State --}}
        <div class="text-center py-20">
            <div class="w-20 h-20 mx-auto bg-gray-100 rounded-full flex items-center justify-center mb-6">
                <svg class="w-10 h-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                </svg>
            </div>
            <h3 class="text-xl font-bold text-gray-900 mb-2">No encontramos negocios</h3>
            <p class="text-gray-500 max-w-sm mx-auto">
                Intenta ajustar tus filtros o buscar algo diferente.
            </p>
            <button 
                wire:click="clearAllFilters"
                class="mt-6 px-6 py-3 bg-indigo-600 text-white font-semibold rounded-xl hover:bg-indigo-500 transition-colors"
            >
                Limpiar filtros
            </button>
        </div>
        @endif
    </main>
    
    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    {{-- MOBILE FILTERS DRAWER (Bottom Sheet) --}}
    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    <div 
        x-show="filtersOpen"
        x-cloak
        class="fixed inset-0 z-50 lg:hidden"
    >
        {{-- Backdrop --}}
        <div 
            class="absolute inset-0 bg-black/50"
            @click="filtersOpen = false"
        ></div>
        
        {{-- Drawer --}}
        <div 
            x-show="filtersOpen"
            x-transition:enter="transform transition ease-out duration-300"
            x-transition:enter-start="translate-y-full"
            x-transition:enter-end="translate-y-0"
            x-transition:leave="transform transition ease-in duration-200"
            x-transition:leave-start="translate-y-0"
            x-transition:leave-end="translate-y-full"
            class="absolute bottom-0 left-0 right-0 bg-white rounded-t-3xl max-h-[80vh] overflow-y-auto"
        >
            {{-- Handle --}}
            <div class="sticky top-0 bg-white pt-3 pb-4 border-b border-gray-100 px-6">
                <div class="w-12 h-1.5 bg-gray-300 rounded-full mx-auto"></div>
                <h3 class="text-lg font-bold text-gray-900 mt-4">Filtros</h3>
            </div>
            
            <div class="p-6 space-y-6">
                {{-- City Filter --}}
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-3">Ciudad</label>
                    <select 
                        wire:model.live="city"
                        class="w-full px-4 py-3 bg-gray-100 border-0 rounded-xl focus:ring-2 focus:ring-indigo-500"
                    >
                        <option value="">Todas las ciudades</option>
                        @foreach($cities as $cityOption)
                            <option value="{{ $cityOption->id }}">{{ $cityOption->name }}</option>
                        @endforeach
                    </select>
                </div>
                
                {{-- Category Filter --}}
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-3">Categoría</label>
                    <div class="flex flex-wrap gap-2">
                        <button 
                            wire:click="$set('category', '')"
                            class="px-4 py-2 rounded-full text-sm font-medium {{ empty($category) ? 'bg-gray-900 text-white' : 'bg-gray-100 text-gray-700' }}"
                        >
                            Todas
                        </button>
                        @foreach($categories as $cat)
                        <button 
                            wire:click="$set('category', '{{ $cat->slug }}')"
                            class="px-4 py-2 rounded-full text-sm font-medium {{ $category === $cat->slug ? 'bg-gray-900 text-white' : 'bg-gray-100 text-gray-700' }}"
                        >
                            {{ $cat->name }}
                        </button>
                        @endforeach
                    </div>
                </div>
            </div>
            
            {{-- Apply Button --}}
            <div class="sticky bottom-0 p-6 bg-white border-t border-gray-100">
                <button 
                    @click="filtersOpen = false"
                    class="w-full py-4 bg-gray-900 text-white font-semibold rounded-xl"
                >
                    Ver {{ $tenants->total() }} resultados
                </button>
            </div>
        </div>
    </div>
</div>
