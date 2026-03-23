<x-filament-panels::page>
    @include('filament.components.panel-critical-styles')

    <div class="space-y-6">
        <x-filament::section>
            <div class="grid gap-6 xl:grid-cols-[1.1fr_0.9fr]">
                <div class="space-y-4">
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500 dark:text-zinc-400">Superadmin</p>
                    <div class="space-y-2">
                        <h1 class="text-3xl font-black tracking-tight text-zinc-950 dark:text-white">Facturacion de la plataforma</h1>
                        <p class="max-w-3xl text-sm leading-6 text-zinc-600 dark:text-zinc-400">
                            Desde aqui tienes una lectura rapida del flujo manual de pagos, el precio vigente de PRO y el estado general de negocios Base vs PRO.
                        </p>
                    </div>
                </div>

                <div class="rounded-2xl border border-emerald-500/20 bg-emerald-500/10 p-5">
                    <p class="text-xs font-semibold uppercase tracking-[0.22em] text-emerald-300">Plan actual</p>
                    <p class="mt-3 text-4xl font-black text-white">{{ $proPlanPrice }}</p>
                    <p class="mt-2 text-sm leading-6 text-emerald-100/80">
                        Precio de referencia del plan PRO mensual visible para los negocios.
                    </p>

                    <div class="mt-5 flex flex-wrap gap-3">
                        <a href="{{ route('filament.admin.resources.payments.index') }}" class="inline-flex items-center justify-center rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-zinc-950 transition hover:bg-zinc-100">
                            Revisar pagos
                        </a>
                        <a href="{{ route('filament.admin.pages.manage-general-settings') }}" class="inline-flex items-center justify-center rounded-xl border border-white/20 bg-white/5 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-white/10">
                            Ajustar configuracion
                        </a>
                    </div>
                </div>
            </div>
        </x-filament::section>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
            <x-filament::section>
                <div class="text-center">
                    <p class="text-xs font-semibold uppercase tracking-[0.22em] text-zinc-500 dark:text-zinc-400">Pendientes</p>
                    <p class="mt-3 text-4xl font-black text-amber-500">{{ $pendingPayments }}</p>
                    <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">Comprobantes por revisar</p>
                </div>
            </x-filament::section>

            <x-filament::section>
                <div class="text-center">
                    <p class="text-xs font-semibold uppercase tracking-[0.22em] text-zinc-500 dark:text-zinc-400">Aprobados</p>
                    <p class="mt-3 text-4xl font-black text-emerald-500">{{ $approvedPayments }}</p>
                    <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">Pagos validados</p>
                </div>
            </x-filament::section>

            <x-filament::section>
                <div class="text-center">
                    <p class="text-xs font-semibold uppercase tracking-[0.22em] text-zinc-500 dark:text-zinc-400">Rechazados</p>
                    <p class="mt-3 text-4xl font-black text-rose-500">{{ $rejectedPayments }}</p>
                    <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">Casos con observacion</p>
                </div>
            </x-filament::section>

            <x-filament::section>
                <div class="text-center">
                    <p class="text-xs font-semibold uppercase tracking-[0.22em] text-zinc-500 dark:text-zinc-400">Negocios PRO</p>
                    <p class="mt-3 text-4xl font-black text-zinc-950 dark:text-white">{{ $proBusinesses }}</p>
                    <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">Cuentas premium</p>
                </div>
            </x-filament::section>

            <x-filament::section>
                <div class="text-center">
                    <p class="text-xs font-semibold uppercase tracking-[0.22em] text-zinc-500 dark:text-zinc-400">Negocios Base</p>
                    <p class="mt-3 text-4xl font-black text-zinc-950 dark:text-white">{{ $baseBusinesses }}</p>
                    <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">Cuentas esenciales</p>
                </div>
            </x-filament::section>
        </div>

        <div class="grid gap-6 xl:grid-cols-[1fr_0.9fr]">
            <x-filament::section>
                <div class="space-y-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500 dark:text-zinc-400">Revision operativa</p>
                        <h2 class="mt-2 text-2xl font-bold text-zinc-950 dark:text-white">Ultimos pagos reportados</h2>
                    </div>

                    @if ($recentPayments !== [])
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead>
                                    <tr class="border-b border-black/5 text-left text-zinc-500 dark:border-white/10 dark:text-zinc-400">
                                        <th class="px-3 py-3">Negocio</th>
                                        <th class="px-3 py-3">Referencia</th>
                                        <th class="px-3 py-3">Monto</th>
                                        <th class="px-3 py-3">Estado</th>
                                        <th class="px-3 py-3">Fecha</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($recentPayments as $payment)
                                        <tr class="border-b border-black/5 dark:border-white/5">
                                            <td class="px-3 py-3 font-medium text-zinc-950 dark:text-white">{{ $payment['business'] }}</td>
                                            <td class="px-3 py-3 text-zinc-500 dark:text-zinc-400">{{ $payment['reference'] }}</td>
                                            <td class="px-3 py-3 text-zinc-950 dark:text-white">{{ $payment['amount'] }}</td>
                                            <td class="px-3 py-3">
                                                <x-filament::badge color="{{ $payment['status'] === 'Aprobado' ? 'success' : ($payment['status'] === 'Pendiente' ? 'warning' : 'danger') }}">
                                                    {{ $payment['status'] }}
                                                </x-filament::badge>
                                            </td>
                                            <td class="px-3 py-3 text-zinc-500 dark:text-zinc-400">{{ $payment['reported_at'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        @include('filament.app.components.table-empty-state', [
                            'heading' => 'Todavia no hay pagos reportados',
                            'description' => 'Cuando un negocio suba un comprobante o el equipo registre una revision, aparecera aqui.',
                        ])
                    @endif
                </div>
            </x-filament::section>

            <x-filament::section>
                <div class="space-y-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500 dark:text-zinc-400">Canales</p>
                        <h2 class="mt-2 text-2xl font-bold text-zinc-950 dark:text-white">Soporte y seguimiento</h2>
                    </div>

                    <div class="space-y-3">
                        <div class="rounded-2xl border border-black/5 bg-zinc-50 px-4 py-3 dark:border-white/10 dark:bg-white/[0.03]">
                            <p class="text-xs uppercase tracking-[0.22em] text-zinc-500 dark:text-zinc-400">WhatsApp soporte</p>
                            <p class="mt-2 text-lg font-semibold text-zinc-950 dark:text-white">{{ $supportWhatsApp }}</p>
                        </div>

                        <div class="rounded-2xl border border-black/5 bg-zinc-50 px-4 py-3 dark:border-white/10 dark:bg-white/[0.03]">
                            <p class="text-xs uppercase tracking-[0.22em] text-zinc-500 dark:text-zinc-400">Correo soporte</p>
                            <p class="mt-2 text-lg font-semibold text-zinc-950 dark:text-white">{{ $supportEmail }}</p>
                        </div>

                        <div class="rounded-2xl border border-black/5 bg-zinc-50 p-4 dark:border-white/10 dark:bg-white/[0.03]">
                            <p class="text-sm font-semibold text-zinc-950 dark:text-white">Flujo recomendado</p>
                            <ol class="mt-3 space-y-2 text-sm leading-6 text-zinc-600 dark:text-zinc-400">
                                <li>1. Revisar pagos pendientes desde la cola de pagos.</li>
                                <li>2. Aprobar o rechazar con nota clara para el negocio.</li>
                                <li>3. Ajustar precio o instrucciones globales desde configuracion general.</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </x-filament::section>
        </div>
    </div>
</x-filament-panels::page>
