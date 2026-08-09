<x-filament-widgets::widget>
    <x-filament::section data-testid="commercial-knowledge-health">
        <x-slot name="heading">Preparación comercial</x-slot>
        <x-slot name="description">Datos públicos que la plataforma puede usar para responder y convertir consultas sin inventar información.</x-slot>

        <div class="space-y-4">
            <div class="flex items-center justify-between gap-4">
                <span class="text-sm text-gray-600 dark:text-gray-300">Puntaje verificable</span>
                <span class="text-2xl font-semibold {{ $publiclyResolvable ? 'text-success-600' : 'text-warning-600' }}">{{ $score }}/100</span>
            </div>

            @if ($issues === [])
                <p class="text-sm text-success-700 dark:text-success-400">La información comercial mínima está completa y vigente.</p>
            @else
                <ul class="divide-y divide-gray-200 dark:divide-white/10">
                    @foreach ($issues as $issue)
                        <li class="flex items-center justify-between gap-4 py-3">
                            <span class="text-sm text-gray-700 dark:text-gray-200">{{ $issue['label'] }}</span>
                            @if ($issue['action_url'] !== '#')
                                <x-filament::link :href="$issue['action_url']">Resolver</x-filament::link>
                            @else
                                <span class="text-xs text-danger-600">No disponible</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
