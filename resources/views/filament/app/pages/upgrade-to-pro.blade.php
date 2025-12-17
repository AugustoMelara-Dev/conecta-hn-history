<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Hero Section --}}
        <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-amber-500 via-orange-500 to-red-500 p-8 text-white">
            <div class="absolute -right-10 -top-10 opacity-20">
                <x-heroicon-o-star class="w-48 h-48" />
            </div>
            <div class="relative">
                <h1 class="text-3xl font-bold mb-3">🚀 Desbloquea el Potencial Completo</h1>
                <p class="text-xl opacity-90 max-w-2xl">
                    Únete a cientos de negocios hondureños que han aumentado sus ventas con Conecta HN PRO.
                </p>
            </div>
        </div>

        {{-- Pricing Card --}}
        <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-xl border-2 border-amber-400 overflow-hidden">
            <div class="bg-gradient-to-r from-amber-50 to-orange-50 dark:from-amber-950 dark:to-orange-950 p-6 text-center border-b border-amber-200 dark:border-amber-800">
                <span class="inline-block px-4 py-1 bg-amber-500 text-white text-sm font-semibold rounded-full mb-3">
                    Plan PRO
                </span>
                <div class="flex items-baseline justify-center gap-1">
                    <span class="text-5xl font-bold text-gray-900 dark:text-white">{{ $price }}</span>
                    <span class="text-gray-600 dark:text-gray-400">/{{ $period }}</span>
                </div>
                <p class="text-sm text-gray-600 dark:text-gray-400 mt-2">Facturación mensual • Cancela cuando quieras</p>
            </div>

            <div class="p-6">
                {{-- Features Grid --}}
                <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3 mb-8">
                    @foreach($features as $feature)
                    <div class="flex items-start gap-3 p-4 bg-gray-50 dark:bg-gray-800 rounded-xl">
                        <div class="flex-shrink-0 w-10 h-10 bg-amber-100 dark:bg-amber-900 rounded-lg flex items-center justify-center">
                            <x-dynamic-component :component="$feature['icon']" class="w-5 h-5 text-amber-600 dark:text-amber-400" />
                        </div>
                        <div>
                            <h3 class="font-semibold text-gray-900 dark:text-white">{{ $feature['title'] }}</h3>
                            <p class="text-sm text-gray-600 dark:text-gray-400">{{ $feature['description'] }}</p>
                        </div>
                    </div>
                    @endforeach
                </div>

                {{-- CTA --}}
                <div class="text-center space-y-4">
                    <a href="https://wa.me/50495000000?text={{ urlencode('Hola! Quiero actualizar mi negocio ' . ($tenant->name ?? '') . ' a PRO en Conecta HN') }}" 
                       target="_blank"
                       class="inline-flex items-center justify-center gap-3 px-8 py-4 text-lg font-bold text-white bg-gradient-to-r from-green-500 to-green-600 rounded-xl shadow-lg hover:shadow-xl hover:from-green-600 hover:to-green-700 transition-all duration-200 transform hover:scale-105">
                        <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                        </svg>
                        Contactar para Activar PRO
                    </a>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Te responderemos en menos de 24 horas hábiles
                    </p>
                </div>
            </div>
        </div>

        {{-- Comparison Table --}}
        <div class="bg-white dark:bg-gray-900 rounded-xl shadow-lg overflow-hidden">
            <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                <h2 class="text-xl font-bold text-gray-900 dark:text-white">Comparación de Planes</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th class="px-6 py-4 text-left text-sm font-semibold text-gray-900 dark:text-white">Característica</th>
                            <th class="px-6 py-4 text-center text-sm font-semibold text-gray-500 dark:text-gray-400">Gratis</th>
                            <th class="px-6 py-4 text-center text-sm font-semibold text-amber-600 dark:text-amber-400">PRO</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        <tr>
                            <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">Límite de Productos</td>
                            <td class="px-6 py-4 text-center text-sm text-gray-700 dark:text-gray-300">10</td>
                            <td class="px-6 py-4 text-center text-sm font-semibold text-green-600">Ilimitados</td>
                        </tr>
                        <tr>
                            <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">Botón de WhatsApp</td>
                            <td class="px-6 py-4 text-center">
                                <x-heroicon-o-x-mark class="w-5 h-5 text-red-500 mx-auto" />
                            </td>
                            <td class="px-6 py-4 text-center">
                                <x-heroicon-o-check class="w-5 h-5 text-green-500 mx-auto" />
                            </td>
                        </tr>
                        <tr>
                            <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">Personalización de Marca</td>
                            <td class="px-6 py-4 text-center">
                                <x-heroicon-o-x-mark class="w-5 h-5 text-red-500 mx-auto" />
                            </td>
                            <td class="px-6 py-4 text-center">
                                <x-heroicon-o-check class="w-5 h-5 text-green-500 mx-auto" />
                            </td>
                        </tr>
                        <tr>
                            <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">Ver Lista de Seguidores</td>
                            <td class="px-6 py-4 text-center">
                                <x-heroicon-o-x-mark class="w-5 h-5 text-red-500 mx-auto" />
                            </td>
                            <td class="px-6 py-4 text-center">
                                <x-heroicon-o-check class="w-5 h-5 text-green-500 mx-auto" />
                            </td>
                        </tr>
                        <tr>
                            <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">Campañas de Marketing</td>
                            <td class="px-6 py-4 text-center">
                                <x-heroicon-o-x-mark class="w-5 h-5 text-red-500 mx-auto" />
                            </td>
                            <td class="px-6 py-4 text-center">
                                <x-heroicon-o-check class="w-5 h-5 text-green-500 mx-auto" />
                            </td>
                        </tr>
                        <tr>
                            <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">Estadísticas Avanzadas</td>
                            <td class="px-6 py-4 text-center">
                                <x-heroicon-o-x-mark class="w-5 h-5 text-red-500 mx-auto" />
                            </td>
                            <td class="px-6 py-4 text-center">
                                <x-heroicon-o-check class="w-5 h-5 text-green-500 mx-auto" />
                            </td>
                        </tr>
                        <tr>
                            <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">Soporte Prioritario</td>
                            <td class="px-6 py-4 text-center">
                                <x-heroicon-o-x-mark class="w-5 h-5 text-red-500 mx-auto" />
                            </td>
                            <td class="px-6 py-4 text-center">
                                <x-heroicon-o-check class="w-5 h-5 text-green-500 mx-auto" />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-filament-panels::page>
