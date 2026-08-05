<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Agenda semanal
        </x-slot>

        <x-slot name="description">
            {{ $weekLabel }}
        </x-slot>

        <x-slot name="headerEnd">
            <div class="flex items-center gap-2">
                <x-filament::button color="gray" size="sm" wire:click="previousWeek">
                    Anterior
                </x-filament::button>
                <x-filament::button color="gray" size="sm" wire:click="currentWeek">
                    Hoy
                </x-filament::button>
                <x-filament::button color="gray" size="sm" wire:click="nextWeek">
                    Siguiente
                </x-filament::button>
            </div>
        </x-slot>

        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-7">
            @foreach ($days as $day)
                <section class="min-h-36 rounded-xl border border-gray-200 bg-white p-3 dark:border-white/10 dark:bg-white/5">
                    <h3 class="text-sm font-semibold text-gray-950 dark:text-white">
                        {{ $day['label'] }}
                    </h3>

                    <div class="mt-3 space-y-2">
                        @forelse ($day['events'] as $event)
                            <a
                                href="{{ $event['url'] }}"
                                class="block rounded-lg border-l-4 bg-gray-50 px-3 py-2 text-xs text-gray-700 transition hover:bg-gray-100 dark:bg-white/5 dark:text-gray-200 dark:hover:bg-white/10"
                                style="border-left-color: {{ $event['backgroundColor'] }}"
                            >
                                <span class="block font-semibold text-gray-950 dark:text-white">
                                    {{ \Illuminate\Support\Carbon::parse($event['start'])->format('H:i') }}
                                </span>
                                <span class="mt-1 block">{{ $event['title'] }}</span>
                            </a>
                        @empty
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                Sin citas
                            </p>
                        @endforelse
                    </div>
                </section>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
