@php
    $heading = $heading ?? 'Sin registros';
    $description = $description ?? null;
    $badge = $badge ?? null;
    $buttonLabel = $buttonLabel ?? null;
    $buttonUrl = $buttonUrl ?? null;
@endphp

<div class="biz-empty-state">
    <div class="biz-stack" style="text-align: center;">
        @if ($badge)
            <div style="display: flex; justify-content: center;">
                <span class="biz-badge biz-badge-success">{{ $badge }}</span>
            </div>
        @endif

        <h3 class="biz-heading">{{ $heading }}</h3>

        @if ($description)
            <p class="biz-muted">{{ $description }}</p>
        @endif

        @if ($buttonLabel && $buttonUrl)
            <div class="biz-button-row" style="justify-content: center;">
                <a href="{{ $buttonUrl }}" class="biz-button">{{ $buttonLabel }}</a>
            </div>
        @endif
    </div>
</div>
