{{--
    BRAND COMPONENT - Conecta HN Text Logo
    
    Simple text-based logo.
    Used in both AdminPanelProvider and AppPanelProvider.
--}}

@props([
    'class' => '',
])

<div {{ $attributes->merge(['class' => 'flex items-center gap-3 ' . $class]) }}>
    <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-gray-950 text-sm font-bold text-white shadow-sm">
        CH
    </div>
    <div class="flex flex-col leading-none">
        <span class="text-[10px] font-semibold uppercase tracking-[0.28em] text-emerald-600 dark:text-emerald-400">
            Vito Business OS
        </span>
        <span class="text-base font-semibold tracking-tight text-gray-900 dark:text-white">
            Conecta HN
        </span>
    </div>
</div>
