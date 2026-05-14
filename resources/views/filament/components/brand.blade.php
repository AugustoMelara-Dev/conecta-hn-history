{{--
    BRAND COMPONENT - Vito Business OS Text Logo
    
    Simple text-based logo.
    Used in both AdminPanelProvider and AppPanelProvider.
--}}

@props([
    'class' => '',
])

<div {{ $attributes->merge(['class' => 'flex items-center gap-1 ' . $class]) }}>
    <span class="text-xl tracking-tight font-semibold">
        <span class="text-gray-700 dark:text-gray-200">Vito</span><span class="font-bold text-blue-600 dark:text-blue-400"> OS</span>
    </span>
</div>
