<x-filament-widgets::widget>
    @if (! $isComplete)
        <x-filament::section>
            <div class="space-y-5">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div class="space-y-1">
                        <h3 class="text-lg font-semibold text-gray-950 dark:text-white">
                            Completa tu perfil
                        </h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            {{ count($missingItems) }} elementos pendientes de {{ max($totalChecks, 1) }}.
                        </p>
                    </div>

                    <div class="inline-flex items-center justify-center rounded-full bg-primary-50 px-4 py-2 text-sm font-semibold text-primary-600 dark:bg-primary-500/10 dark:text-primary-400">
                        {{ $percentage }}%
                    </div>
                </div>

                <div class="h-2 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-800">
                    <div
                        class="h-full rounded-full bg-primary-600 transition-all duration-300"
                        style="width: {{ max(0, min($percentage, 100)) }}%"
                    ></div>
                </div>

                @if (count($missingItems) > 0)
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                        @foreach (array_slice($missingItems, 0, 4) as $item)
                            <a
                                href="{{ $item['url'] }}"
                                class="flex items-start gap-3 rounded-xl border border-gray-200 bg-white p-4 transition hover:border-primary-200 hover:bg-gray-50 dark:border-white/10 dark:bg-gray-900 dark:hover:border-primary-500/30 dark:hover:bg-white/[0.03]"
                            >
                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gray-100 text-gray-600 dark:bg-white/5 dark:text-gray-300">
                                    <x-dynamic-component :component="$item['icon']" class="h-5 w-5" />
                                </div>

                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-semibold text-gray-950 dark:text-white">
                                        {{ $item['field'] }}
                                    </p>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">
                                        {{ $item['action'] }}
                                    </p>
                                    <p class="mt-1 text-xs text-primary-600 dark:text-primary-400">
                                        +{{ round(100 / max($totalChecks, 1)) }}% al completar
                                    </p>
                                </div>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        </x-filament::section>
    @else
        <x-filament::section>
            <div class="flex items-center gap-4 p-1">
                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-emerald-100 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400">
                    <x-heroicon-o-check class="h-6 w-6" />
                </div>
                <div>
                    <h3 class="font-semibold text-gray-950 dark:text-white">Perfil completo</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Tu negocio ya está listo para recibir clientes.
                    </p>
                </div>
            </div>
        </x-filament::section>
    @endif
</x-filament-widgets::widget>
