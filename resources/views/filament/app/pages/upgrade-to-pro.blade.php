<x-filament-panels::page>
    <div class="biz-stack" data-testid="upgrade-page">
        <section class="biz-card-accent">
            <div class="biz-stack">
                <div class="biz-button-row">
                    <x-filament::badge color="warning" size="lg">
                        Plan PRO
                    </x-filament::badge>
                    <x-filament::badge color="gray" size="lg">
                        {{ $price }} / {{ $period }}
                    </x-filament::badge>
                </div>

                <div>
                    <h1 class="biz-title">Activa mas herramientas para tu negocio</h1>
                    <p class="biz-muted">
                        PRO suma mas catalogo, reservas avanzadas, reportes/campanas e IA operativa activable
                        para negocios que ya validaron demanda con Free.
                    </p>
                </div>

                <div class="biz-button-row">
                    <a
                        href="https://wa.me/50495000000?text={{ urlencode('Hola, quiero actualizar mi negocio ' . ($tenant->name ?? '') . ' a PRO en Dovra AI') }}"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="biz-button"
                        data-testid="upgrade-whatsapp-cta"
                    >
                        Contactar para activar PRO
                    </a>
                    <a href="{{ route('filament.app.pages.manage-billing', ['tenant' => $tenant?->slug]) }}" class="biz-button-secondary">
                        Ver suscripcion y pagos
                    </a>
                </div>
            </div>
        </section>

        <section class="biz-card">
            <div class="biz-stack">
                <div>
                    <h2 class="biz-heading">Lo que desbloquea PRO</h2>
                    <p class="biz-muted">Beneficios pensados para vender y operar con mas orden, no para decorar el panel.</p>
                </div>

                <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    @foreach($features as $feature)
                        <article class="biz-card">
                            <div class="biz-stack">
                                <x-dynamic-component :component="$feature['icon']" class="h-6 w-6 text-primary-600 dark:text-primary-400" />
                                <div>
                                    <h3 class="biz-heading">{{ $feature['title'] }}</h3>
                                    <p class="biz-muted">{{ $feature['description'] }}</p>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="biz-card" data-testid="upgrade-comparison">
            <div class="biz-stack">
                <div>
                    <h2 class="biz-heading">Comparacion de planes</h2>
                    <p class="biz-muted">Una referencia rapida para decidir si el cambio ya tiene sentido.</p>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-gray-700">
                                <th class="px-4 py-3 text-left font-semibold text-gray-900 dark:text-white">Capacidad</th>
                                <th class="px-4 py-3 text-center font-semibold text-gray-600 dark:text-gray-300">Base</th>
                                <th class="px-4 py-3 text-center font-semibold text-primary-700 dark:text-primary-300">PRO</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            <tr>
                                <td class="px-4 py-3 text-gray-700 dark:text-gray-300">Productos publicados</td>
                                <td class="px-4 py-3 text-center text-gray-600 dark:text-gray-400">Hasta 10</td>
                                <td class="px-4 py-3 text-center font-semibold text-gray-900 dark:text-white">Catalogo ampliado</td>
                            </tr>
                            <tr>
                                <td class="px-4 py-3 text-gray-700 dark:text-gray-300">WhatsApp, llamada y mapa</td>
                                <td class="px-4 py-3 text-center">
                                    <x-filament::badge color="success">Incluido</x-filament::badge>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <x-filament::badge color="success">Incluido</x-filament::badge>
                                </td>
                            </tr>
                            <tr>
                                <td class="px-4 py-3 text-gray-700 dark:text-gray-300">Marca personalizada</td>
                                <td class="px-4 py-3 text-center">
                                    <x-filament::badge color="gray">Basica</x-filament::badge>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <x-filament::badge color="success">Incluida</x-filament::badge>
                                </td>
                            </tr>
                            <tr>
                                <td class="px-4 py-3 text-gray-700 dark:text-gray-300">Reservas</td>
                                <td class="px-4 py-3 text-center text-gray-600 dark:text-gray-400">Basicas/legacy segun rubro</td>
                                <td class="px-4 py-3 text-center font-semibold text-gray-900 dark:text-white">Avanzadas segun capacidad</td>
                            </tr>
                            <tr>
                                <td class="px-4 py-3 text-gray-700 dark:text-gray-300">Reportes y campanas</td>
                                <td class="px-4 py-3 text-center text-gray-600 dark:text-gray-400">Inicial</td>
                                <td class="px-4 py-3 text-center font-semibold text-gray-900 dark:text-white">Mas senales y herramientas</td>
                            </tr>
                            <tr>
                                <td class="px-4 py-3 text-gray-700 dark:text-gray-300">Agente IA privado</td>
                                <td class="px-4 py-3 text-center text-gray-600 dark:text-gray-400">No incluido</td>
                                <td class="px-4 py-3 text-center font-semibold text-gray-900 dark:text-white">Activable por tenant</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>
</x-filament-panels::page>
