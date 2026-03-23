<x-filament-panels::page>
    @include('filament.components.panel-critical-styles')

    <div class="space-y-6">
        <x-filament::section>
            <div class="grid gap-6 xl:grid-cols-[1.15fr_0.85fr]">
                <div class="space-y-4">
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500 dark:text-zinc-400">Superadmin</p>
                    <div class="space-y-2">
                        <h1 class="text-3xl font-black tracking-tight text-zinc-950 dark:text-white">Centro de control de la plataforma</h1>
                        <p class="max-w-3xl text-sm leading-6 text-zinc-600 dark:text-zinc-400">
                            Supervisa negocios, pagos pendientes y crecimiento general sin salir del panel principal.
                        </p>
                    </div>
                </div>

                <div class="rounded-2xl border border-sky-500/20 bg-sky-500/10 p-5">
                    <p class="text-xs font-semibold uppercase tracking-[0.22em] text-sky-300">Atencion inmediata</p>
                    <p class="mt-3 text-4xl font-black text-white">{{ $pendingPaymentsCount }}</p>
                    <p class="mt-2 text-sm leading-6 text-sky-100/80">
                        pagos pendientes por revisar antes de activar o rechazar solicitudes de PRO.
                    </p>

                    <div class="mt-5 flex flex-wrap gap-3">
                        <a href="{{ route('filament.admin.resources.payments.index') }}" class="inline-flex items-center justify-center rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-zinc-950 transition hover:bg-zinc-100">
                            Ir a pagos
                        </a>
                        <a href="{{ route('filament.admin.resources.tenants.index') }}" class="inline-flex items-center justify-center rounded-xl border border-white/20 bg-white/5 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-white/10">
                            Ver negocios
                        </a>
                    </div>
                </div>
            </div>
        </x-filament::section>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <x-filament::section>
                <div class="text-center">
                    <p class="text-xs font-semibold uppercase tracking-[0.22em] text-zinc-500 dark:text-zinc-400">Negocios</p>
                    <p class="mt-3 text-4xl font-black text-zinc-950 dark:text-white">{{ $tenantsCount }}</p>
                    <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">Total registrados</p>
                </div>
            </x-filament::section>

            <x-filament::section>
                <div class="text-center">
                    <p class="text-xs font-semibold uppercase tracking-[0.22em] text-zinc-500 dark:text-zinc-400">Negocios PRO</p>
                    <p class="mt-3 text-4xl font-black text-emerald-500">{{ $proTenantsCount }}</p>
                    <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">Con funciones premium</p>
                </div>
            </x-filament::section>

            <x-filament::section>
                <div class="text-center">
                    <p class="text-xs font-semibold uppercase tracking-[0.22em] text-zinc-500 dark:text-zinc-400">Usuarios</p>
                    <p class="mt-3 text-4xl font-black text-zinc-950 dark:text-white">{{ $usersCount }}</p>
                    <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">Cuentas totales</p>
                </div>
            </x-filament::section>

            <x-filament::section>
                <div class="text-center">
                    <p class="text-xs font-semibold uppercase tracking-[0.22em] text-zinc-500 dark:text-zinc-400">Pagos pendientes</p>
                    <p class="mt-3 text-4xl font-black text-amber-500">{{ $pendingPaymentsCount }}</p>
                    <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">Requieren revision</p>
                </div>
            </x-filament::section>
        </div>

        <div class="grid gap-6 xl:grid-cols-[1fr_0.9fr]">
            <x-filament::section>
                <div class="space-y-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500 dark:text-zinc-400">Actividad</p>
                        <h2 class="mt-2 text-2xl font-bold text-zinc-950 dark:text-white">Ultimos negocios creados</h2>
                    </div>

                    @if ($recentBusinesses !== [])
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead>
                                    <tr class="border-b border-black/5 text-left text-zinc-500 dark:border-white/10 dark:text-zinc-400">
                                        <th class="px-3 py-3">Negocio</th>
                                        <th class="px-3 py-3">Propietario</th>
                                        <th class="px-3 py-3">Plan</th>
                                        <th class="px-3 py-3">Fecha</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($recentBusinesses as $business)
                                        <tr class="border-b border-black/5 dark:border-white/5">
                                            <td class="px-3 py-3 font-medium text-zinc-950 dark:text-white">{{ $business['business'] }}</td>
                                            <td class="px-3 py-3 text-zinc-500 dark:text-zinc-400">{{ $business['owner'] }}</td>
                                            <td class="px-3 py-3">
                                                <x-filament::badge color="{{ $business['status'] === 'PRO' ? 'success' : 'gray' }}">
                                                    {{ $business['status'] }}
                                                </x-filament::badge>
                                            </td>
                                            <td class="px-3 py-3 text-zinc-500 dark:text-zinc-400">{{ $business['created_at'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        @include('filament.app.components.table-empty-state', [
                            'heading' => 'Todavia no hay negocios registrados',
                            'description' => 'Cuando se cree el primer negocio desde el flujo publico o el panel admin, aparecera aqui.',
                        ])
                    @endif
                </div>
            </x-filament::section>

            <x-filament::section>
                <div class="space-y-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500 dark:text-zinc-400">Accesos rapidos</p>
                        <h2 class="mt-2 text-2xl font-bold text-zinc-950 dark:text-white">Gestion operativa</h2>
                    </div>

                    <div class="space-y-3">
                        <a href="{{ route('filament.admin.resources.tenants.index') }}" class="block rounded-2xl border border-black/5 bg-zinc-50 px-4 py-4 transition hover:border-emerald-500/30 hover:bg-white dark:border-white/10 dark:bg-white/[0.03] dark:hover:bg-white/[0.05]">
                            <p class="text-base font-semibold text-zinc-950 dark:text-white">Administrar negocios</p>
                            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Alta, revision y estado comercial de cada cuenta.</p>
                        </a>

                        <a href="{{ route('filament.admin.resources.users.index') }}" class="block rounded-2xl border border-black/5 bg-zinc-50 px-4 py-4 transition hover:border-emerald-500/30 hover:bg-white dark:border-white/10 dark:bg-white/[0.03] dark:hover:bg-white/[0.05]">
                            <p class="text-base font-semibold text-zinc-950 dark:text-white">Administrar usuarios</p>
                            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Controla propietarios, superadmins y acceso general.</p>
                        </a>

                        <a href="{{ route('filament.admin.pages.manage-general-settings') }}" class="block rounded-2xl border border-black/5 bg-zinc-50 px-4 py-4 transition hover:border-emerald-500/30 hover:bg-white dark:border-white/10 dark:bg-white/[0.03] dark:hover:bg-white/[0.05]">
                            <p class="text-base font-semibold text-zinc-950 dark:text-white">Configuracion global</p>
                            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Precio PRO, soporte, pagos y parametros del sistema.</p>
                        </a>
                    </div>
                </div>
            </x-filament::section>
        </div>
    </div>
</x-filament-panels::page>
