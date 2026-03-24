@once
    @php
        $criticalStyles = '';
        $criticalStylesPath = resource_path('css/filament/shared/critical-inline.css');

        if (is_file($criticalStylesPath)) {
            $criticalStyles = file_get_contents($criticalStylesPath) ?: '';
        }
    @endphp

    @if ($criticalStyles !== '')
        <style data-filament-critical-theme="true">
            {!! $criticalStyles !!}
        </style>
    @endif
@endonce
