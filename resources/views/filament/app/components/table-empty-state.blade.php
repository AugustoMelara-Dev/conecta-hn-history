@php
    $heading = $heading ?? 'Sin registros';
    $description = $description ?? null;
    $badge = $badge ?? null;
    $buttonLabel = $buttonLabel ?? null;
    $buttonUrl = $buttonUrl ?? null;
@endphp

<div class="biz-empty-state" role="status" aria-live="polite">
    <div class="biz-stack biz-stack-center">
        @if ($badge)
            <div class="biz-button-row biz-row-center">
                <span class="biz-badge biz-badge-success">{{ $badge }}</span>
            </div>
        @endif

        <h3 class="biz-heading">{{ $heading }}</h3>

        @if ($description)
            <p class="biz-muted">{{ $description }}</p>
        @endif

        @if ($buttonLabel && $buttonUrl)
            <div class="biz-button-row biz-row-center">
                <a href="{{ $buttonUrl }}" class="biz-button">{{ $buttonLabel }}</a>
            </div>
        @endif
    </div>
</div>
