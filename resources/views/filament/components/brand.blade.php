{{--
    BRAND COMPONENT - Conecta HN Text Logo
    
    Simple text-based logo.
    Used in both AdminPanelProvider and AppPanelProvider.
--}}

@props([
    'class' => '',
])

<div {{ $attributes->merge(['class' => 'biz-brand ' . $class]) }}>
    <span class="biz-brand-mark">CH</span>

    <span class="biz-brand-copy">
        <span class="biz-brand-kicker">Vito Business OS</span>
        <span class="biz-brand-name">Conecta HN</span>
    </span>
</div>
