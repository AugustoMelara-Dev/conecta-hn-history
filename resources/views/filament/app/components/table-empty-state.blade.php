@php
    $heading = $heading ?? 'Sin registros';
    $description = $description ?? null;
    $badge = $badge ?? null;
    $buttonLabel = $buttonLabel ?? null;
    $buttonUrl = $buttonUrl ?? null;
@endphp

@include('filament.components.panel-critical-styles')

<div
    class="biz-empty-state"
    style="
        border: 1px dashed rgba(255, 255, 255, 0.14);
        border-radius: 1.5rem;
        background: rgba(255, 255, 255, 0.03);
        padding: 1.5rem;
        text-align: center;
    "
>
    @if ($badge)
        <div
            class="biz-badge biz-badge-success"
            style="
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 0.5rem;
                border-radius: 999px;
                padding: 0.4rem 0.8rem;
                border: 1px solid rgba(16, 185, 129, 0.24);
                background: rgba(16, 185, 129, 0.12);
                color: #6ee7b7;
                font-size: 0.72rem;
                font-weight: 700;
                letter-spacing: 0.12em;
                text-transform: uppercase;
            "
        >
            {{ $badge }}
        </div>
    @endif

    <h3
        class="biz-heading"
        style="margin-top: 1rem; color: #ffffff; font-size: 1.1rem; font-weight: 800; line-height: 1.2;"
    >
        {{ $heading }}
    </h3>

    @if ($description)
        <p
            class="biz-muted"
            style="margin: 0.75rem auto 0; max-width: 36rem; color: #a1a1aa; line-height: 1.65;"
        >
            {{ $description }}
        </p>
    @endif

    @if ($buttonLabel && $buttonUrl)
        <div class="biz-button-row" style="justify-content: center; margin-top: 1.25rem;">
            <a
                href="{{ $buttonUrl }}"
                class="biz-button"
                style="
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    min-height: 2.9rem;
                    border-radius: 0.9rem;
                    background: #10b981;
                    color: #ffffff;
                    font-size: 0.92rem;
                    font-weight: 700;
                    padding: 0.8rem 1.1rem;
                    text-decoration: none;
                "
            >
                {{ $buttonLabel }}
            </a>
        </div>
    @endif
</div>
