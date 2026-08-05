<x-filament-panels::page>
    <div data-testid="marketing-page" class="flex flex-col gap-6">
        @if(!$isPro)
            <x-filament::section data-testid="marketing-free-paywall">
                <div class="mx-auto flex max-w-3xl flex-col gap-8 lg:flex-row lg:items-center lg:justify-between">
                    <div class="flex max-w-xl flex-col gap-4">
                        <x-filament::badge color="warning" size="lg">
                            Plan PRO
                        </x-filament::badge>

                        <div class="flex flex-col gap-3">
                            <h2 class="text-2xl font-semibold tracking-tight text-gray-950 dark:text-white">
                                Herramientas para vender con QR y WhatsApp
                            </h2>
                            <p class="text-sm leading-6 text-gray-600 dark:text-gray-400">
                                Activa Marketing para crear carteles QR, personalizar mensajes de WhatsApp y ordenar las secciones que ve el cliente en tu menu publico.
                            </p>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-3">
                            <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
                                <x-heroicon-o-qr-code class="mb-3 size-6 text-primary-600 dark:text-primary-400" />
                                <h3 class="text-sm font-semibold text-gray-950 dark:text-white">Carteles QR</h3>
                                <p class="mt-1 text-xs leading-5 text-gray-500 dark:text-gray-400">Listos para imprimir y poner en el local.</p>
                            </div>
                            <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
                                <x-heroicon-o-chat-bubble-left-right class="mb-3 size-6 text-primary-600 dark:text-primary-400" />
                                <h3 class="text-sm font-semibold text-gray-950 dark:text-white">WhatsApp</h3>
                                <p class="mt-1 text-xs leading-5 text-gray-500 dark:text-gray-400">Mensajes con producto y precio incluidos.</p>
                            </div>
                            <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
                                <x-heroicon-o-folder class="mb-3 size-6 text-primary-600 dark:text-primary-400" />
                                <h3 class="text-sm font-semibold text-gray-950 dark:text-white">Secciones</h3>
                                <p class="mt-1 text-xs leading-5 text-gray-500 dark:text-gray-400">Orden del catalogo sin editar codigo.</p>
                            </div>
                        </div>
                    </div>

                    <div class="flex w-full max-w-sm flex-col gap-4 rounded-2xl bg-gray-50 p-6 ring-1 ring-gray-200 dark:bg-white/5 dark:ring-white/10">
                        <div>
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Desde</p>
                            <p class="mt-1 text-3xl font-semibold text-gray-950 dark:text-white">{{ $formattedProPrice }}/mes</p>
                        </div>

                        <x-filament::button
                            tag="a"
                            size="lg"
                            icon="heroicon-o-arrow-up-right"
                            href="{{ route('filament.app.pages.manage-billing', ['tenant' => filament()->getTenant()->slug]) }}"
                        >
                            Ver planes
                        </x-filament::button>

                        <p class="text-xs leading-5 text-gray-500 dark:text-gray-400">
                            Puedes revisar el plan antes de enviar cualquier comprobante de pago.
                        </p>
                    </div>
                </div>
            </x-filament::section>
        @else
            <section data-testid="marketing-pro-tools" class="grid gap-6 xl:grid-cols-[minmax(0,1.1fr)_minmax(360px,0.9fr)]">
                <x-filament::section>
                    <x-slot name="heading">
                        WhatsApp del negocio
                    </x-slot>

                    <x-slot name="description">
                        Ajusta el mensaje que recibe el cliente cuando pregunta por un producto.
                    </x-slot>

                    <div class="flex flex-col gap-6">
                        {{ $this->form }}

                        <div data-testid="marketing-whatsapp-preview" class="rounded-2xl bg-gray-50 p-4 ring-1 ring-gray-200 dark:bg-white/5 dark:ring-white/10">
                            <div class="mb-3 flex items-center justify-between gap-3">
                                <div>
                                    <p class="text-sm font-medium text-gray-950 dark:text-white">Vista previa</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Ejemplo del mensaje antes de abrir WhatsApp.</p>
                                </div>
                                <x-filament::badge color="success">Activo</x-filament::badge>
                            </div>

                            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200 dark:bg-gray-950 dark:ring-white/10">
                                <p class="whitespace-pre-line text-sm leading-6 text-gray-700 dark:text-gray-300">{{ $messagePreview }}</p>
                                <p class="mt-3 text-right text-xs text-gray-400">{{ now()->format('g:i A') }}</p>
                            </div>
                        </div>
                    </div>
                </x-filament::section>

                <x-filament::section data-testid="marketing-qr-card">
                    <x-slot name="heading">
                        Cartel QR
                    </x-slot>

                    <x-slot name="description">
                        Descarga un PDF imprimible con el enlace de tu negocio.
                    </x-slot>

                    <div class="flex flex-col gap-5">
                        <div class="flex justify-center rounded-2xl bg-gray-50 p-6 ring-1 ring-gray-200 dark:bg-white/5 dark:ring-white/10">
                            @if($qrCodeSvg)
                                <div class="size-[250px]" aria-label="Codigo QR del negocio">
                                    {!! $qrCodeSvg !!}
                                </div>
                            @else
                                <div class="flex size-[250px] items-center justify-center rounded-xl border border-dashed border-gray-300 dark:border-white/20">
                                    <x-heroicon-o-qr-code class="size-16 text-gray-400" />
                                </div>
                            @endif
                        </div>

                        <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
                            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Enlace publico</p>
                            <a href="{{ $businessUrl }}" target="_blank" rel="noreferrer" class="mt-1 block truncate text-sm font-medium text-primary-600 hover:underline dark:text-primary-400">
                                {{ $businessUrl }}
                            </a>
                        </div>

                        <ul class="grid gap-2 text-sm text-gray-600 dark:text-gray-400">
                            <li class="flex items-center gap-2">
                                <x-heroicon-o-check-circle class="size-5 text-success-600 dark:text-success-400" />
                                QR grande para mostrador o mesas.
                            </li>
                            <li class="flex items-center gap-2">
                                <x-heroicon-o-check-circle class="size-5 text-success-600 dark:text-success-400" />
                                Nombre del negocio y enlace en texto.
                            </li>
                            <li class="flex items-center gap-2">
                                <x-heroicon-o-check-circle class="size-5 text-success-600 dark:text-success-400" />
                                Tamano carta, listo para imprimir.
                            </li>
                        </ul>

                        <x-filament::button
                            type="button"
                            size="lg"
                            icon="heroicon-o-arrow-down-tray"
                            wire:click="downloadPoster"
                            data-testid="marketing-download-poster"
                        >
                            Descargar cartel PDF
                        </x-filament::button>
                    </div>
                </x-filament::section>
            </section>

            <x-filament::section data-testid="marketing-menu-sections">
                <x-slot name="heading">
                    Secciones del menu
                </x-slot>

                <x-slot name="description">
                    Ordena categorias, edita nombres en linea y controla que se muestra en el menu publico.
                </x-slot>

                {{ $this->table }}
            </x-filament::section>

            <x-filament::section data-testid="marketing-usage-tips">
                <div class="flex flex-col gap-4 md:flex-row md:items-start">
                    <div class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-warning-50 ring-1 ring-warning-200 dark:bg-warning-400/10 dark:ring-warning-400/20">
                        <x-heroicon-o-light-bulb class="size-6 text-warning-600 dark:text-warning-300" />
                    </div>

                    <div class="flex flex-col gap-3">
                        <div>
                            <h3 class="text-base font-semibold text-gray-950 dark:text-white">Ideas rapidas para usar Marketing</h3>
                            <p class="mt-1 text-sm leading-6 text-gray-600 dark:text-gray-400">
                                Coloca el QR donde el cliente espera, usa {producto} para evitar preguntas repetidas y reordena secciones segun lo que mas vendes.
                            </p>
                        </div>

                        <div class="grid gap-3 md:grid-cols-3">
                            <div class="rounded-xl border border-gray-200 p-4 text-sm text-gray-600 dark:border-white/10 dark:text-gray-400">
                                Mostrador: el cliente puede revisar el menu antes de ordenar.
                            </div>
                            <div class="rounded-xl border border-gray-200 p-4 text-sm text-gray-600 dark:border-white/10 dark:text-gray-400">
                                Mesas: QR visible para ver fotos y precios sin esperar.
                            </div>
                            <div class="rounded-xl border border-gray-200 p-4 text-sm text-gray-600 dark:border-white/10 dark:text-gray-400">
                                WhatsApp: menos ida y vuelta, mas contexto en cada mensaje.
                            </div>
                        </div>
                    </div>
                </div>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
