{{--
    BRAND COMPONENT - Conecta HN Vector Logo
    
    Scalable SVG-based logo with typography.
    Used in both AdminPanelProvider and AppPanelProvider.
    
    Props:
    - $variant: 'full' (icon + text) | 'icon' (icon only)
    - $class: Additional CSS classes
--}}

@props([
    'variant' => 'full',
    'class' => '',
])

<div {{ $attributes->merge(['class' => 'flex items-center gap-2 ' . $class]) }}>
    {{-- ICONO SVG: Nodo de Conexión --}}
    <svg 
        class="w-8 h-8 flex-shrink-0" 
        viewBox="0 0 40 40" 
        fill="none" 
        xmlns="http://www.w3.org/2000/svg"
    >
        {{-- Círculo central --}}
        <circle cx="20" cy="20" r="8" fill="url(#gradient-primary)" />
        
        {{-- Nodos de conexión --}}
        <circle cx="8" cy="12" r="4" fill="currentColor" class="text-blue-400" />
        <circle cx="32" cy="12" r="4" fill="currentColor" class="text-blue-400" />
        <circle cx="8" cy="28" r="4" fill="currentColor" class="text-indigo-400" />
        <circle cx="32" cy="28" r="4" fill="currentColor" class="text-indigo-400" />
        
        {{-- Líneas de conexión --}}
        <line x1="12" y1="14" x2="16" y2="18" stroke="currentColor" stroke-width="2" class="text-blue-300" />
        <line x1="28" y1="14" x2="24" y2="18" stroke="currentColor" stroke-width="2" class="text-blue-300" />
        <line x1="12" y1="26" x2="16" y2="22" stroke="currentColor" stroke-width="2" class="text-indigo-300" />
        <line x1="28" y1="26" x2="24" y2="22" stroke="currentColor" stroke-width="2" class="text-indigo-300" />
        
        {{-- Gradiente --}}
        <defs>
            <linearGradient id="gradient-primary" x1="12" y1="12" x2="28" y2="28" gradientUnits="userSpaceOnUse">
                <stop offset="0%" stop-color="#3B82F6" />
                <stop offset="100%" stop-color="#6366F1" />
            </linearGradient>
        </defs>
    </svg>
    
    {{-- LOGOTIPO TIPOGRÁFICO --}}
    @if($variant === 'full')
    <span class="text-xl tracking-tight">
        <span class="font-normal text-gray-700 dark:text-gray-200">Conecta</span><span class="font-bold text-blue-600 dark:text-blue-400">HN</span>
    </span>
    @endif
</div>
