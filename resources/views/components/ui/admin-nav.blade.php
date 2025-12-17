{{--
    Admin Nav - Tenant Panel Navigation
    
    Centralized navigation for all tenant admin modules.
    Mobile: Bottom sheet style
    Desktop: Horizontal bar below header
--}}

@php
    $navItems = [
        [
            'route' => 'tenant.products.index',
            'label' => 'Productos',
            'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>',
            'pattern' => 'tenant.products.*',
        ],
        [
            'route' => 'tenant.orders.manage',
            'label' => 'Pedidos',
            'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>',
            'pattern' => 'tenant.orders.*',
        ],
        [
            'route' => 'tenant.reports.index',
            'label' => 'Reportes',
            'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>',
            'pattern' => 'tenant.reports.*',
        ],
        [
            'route' => 'tenant.marketing.index',
            'label' => 'Marketing',
            'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/>',
            'pattern' => 'tenant.marketing.*',
        ],
        [
            'route' => 'tenant.billing.index',
            'label' => 'Facturación',
            'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>',
            'pattern' => 'tenant.billing.*',
        ],
        [
            'route' => 'tenant.settings.profile',
            'label' => 'Configuración',
            'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>',
            'pattern' => 'tenant.settings.*',
        ],
    ];
@endphp

<nav class="bg-white border-b border-gray-200 -mt-px">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center gap-1 overflow-x-auto scrollbar-hide py-2">
            @foreach($navItems as $item)
                @php
                    $isActive = request()->routeIs($item['pattern']);
                @endphp
                <a
                    href="{{ route($item['route']) }}"
                    wire:navigate
                    class="flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg whitespace-nowrap transition-colors
                        {{ $isActive 
                            ? 'bg-blue-50 text-blue-700' 
                            : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900' }}"
                >
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        {!! $item['icon'] !!}
                    </svg>
                    {{ $item['label'] }}
                </a>
            @endforeach
        </div>
    </div>
</nav>
