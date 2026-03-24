@php
    $heading = $this->getHeading();
    $subheading = $this->getSubheading();
    $hasLogo = $this->hasLogo();
@endphp

<div class="fi-simple-page biz-auth-page">
    {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::SIMPLE_PAGE_START, scopes: $this->getRenderHookScopes()) }}

    <div class="fi-simple-page-content biz-auth-card">
        @if (filled($heading) || $hasLogo || filled($subheading))
            <header class="biz-auth-header">
                @if ($hasLogo)
                    <div class="biz-auth-logo">
                        <x-filament-panels::logo />
                    </div>
                @endif

                @if (filled($heading))
                    <h1 class="biz-auth-heading">
                        {{ $heading }}
                    </h1>
                @endif

                @if (filled($subheading))
                    <p class="biz-auth-subheading">
                        {{ $subheading }}
                    </p>
                @endif
            </header>
        @endif

        <div class="biz-auth-content">
            {{ $this->content }}
        </div>
    </div>

    @if (! $this instanceof \Filament\Tables\Contracts\HasTable)
        <x-filament-actions::modals />
    @endif

    {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::SIMPLE_PAGE_END, scopes: $this->getRenderHookScopes()) }}
</div>
