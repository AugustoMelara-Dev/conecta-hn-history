<div class="biz-empty-state">
    <div class="biz-stack">
        @isset($icon)
            <x-dynamic-component :component="$icon" class="mx-auto size-10 text-gray-400 dark:text-gray-500" />
        @else
            <x-heroicon-o-inbox class="mx-auto size-10 text-gray-400 dark:text-gray-500" />
        @endisset

        <div>
            <p class="biz-heading">{{ $heading ?? 'Sin registros' }}</p>
            <p class="biz-muted">{{ $description ?? 'Cuando exista informacion, aparecera aqui.' }}</p>
        </div>
    </div>
</div>
