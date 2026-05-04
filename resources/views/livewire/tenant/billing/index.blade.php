{{--
    Legacy Billing Workspace - compatibility UI only.
    The official billing source of truth is the Filament app billing panel.
--}}

<div class="min-h-screen bg-gray-50 pb-12">
    <header class="sticky top-0 z-40 border-b border-gray-200 bg-white/95 backdrop-blur">
        <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
            <div class="flex min-h-16 flex-col justify-center gap-3 py-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-wide text-blue-700">Facturacion</p>
                    <h1 class="text-xl font-bold text-gray-900">Suscripcion y pagos</h1>
                </div>

                @if($this->isPro)
                    <span class="inline-flex w-fit items-center rounded-full bg-emerald-100 px-3 py-1 text-sm font-bold text-emerald-700">Plan PRO</span>
                @else
                    <span class="inline-flex w-fit items-center rounded-full bg-gray-100 px-3 py-1 text-sm font-bold text-gray-600">Plan gratuito</span>
                @endif
            </div>
        </div>
    </header>

    <x-ui.admin-nav />

    <main class="mx-auto max-w-5xl px-4 py-6 sm:px-6 sm:py-8 lg:px-8">
        <section class="mb-6 rounded-2xl border border-blue-100 bg-blue-50 p-4 text-blue-950 sm:p-5" role="status" aria-live="polite">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="max-w-3xl">
                    <h2 class="text-base font-bold text-blue-950">El billing oficial esta en el panel principal</h2>
                    <p class="mt-1 text-sm text-blue-900">
                        Esta pantalla legacy se mantiene como referencia de compatibilidad. Para activar plan, reportar pagos, revisar vencimientos o descargar comprobantes, usa el panel oficial.
                    </p>
                </div>
                <a
                    href="{{ route('tenant.billing.index') }}"
                    class="inline-flex items-center justify-center rounded-xl bg-blue-700 px-4 py-2.5 text-sm font-bold text-white shadow-sm transition-colors hover:bg-blue-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-700"
                    aria-label="Abrir el panel oficial de suscripcion y pagos"
                >
                    Abrir billing oficial
                </a>
            </div>
        </section>

        @if(!$this->isPro)
            <section class="overflow-hidden rounded-2xl border border-indigo-100 bg-white shadow-sm" aria-labelledby="billing-upgrade-title">
                <div class="bg-gradient-to-r from-indigo-700 to-blue-700 px-5 py-8 text-white sm:px-8 sm:py-10">
                    <p class="text-sm font-semibold uppercase tracking-wide text-indigo-100">Plan disponible</p>
                    <h2 id="billing-upgrade-title" class="mt-2 text-2xl font-extrabold sm:text-3xl">Activa funciones comerciales cuando tu negocio las necesite</h2>
                    <p class="mt-3 max-w-2xl text-sm text-indigo-100 sm:text-base">
                        El plan PRO agrega mas capacidad operativa. La activacion y revision del pago se gestionan desde el billing oficial para evitar pasos duplicados.
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2">
                    <div class="border-b border-gray-100 p-5 sm:p-8 md:border-b-0 md:border-r">
                        <h3 class="text-lg font-bold text-gray-700">Tu plan actual</h3>
                        <p class="mt-1 text-sm text-gray-500">Funciones base disponibles hoy.</p>
                        <ul class="mt-6 space-y-4">
                            <li class="flex gap-3 text-gray-700">
                                <span class="mt-1 h-2.5 w-2.5 rounded-full bg-emerald-500" aria-hidden="true"></span>
                                Catalogo basico para iniciar tu presencia digital.
                            </li>
                            <li class="flex gap-3 text-gray-700">
                                <span class="mt-1 h-2.5 w-2.5 rounded-full bg-emerald-500" aria-hidden="true"></span>
                                Link de tienda para compartir con clientes.
                            </li>
                            <li class="flex gap-3 text-gray-500">
                                <span class="mt-1 h-2.5 w-2.5 rounded-full bg-gray-300" aria-hidden="true"></span>
                                Algunas funciones avanzadas dependen de plan y permisos.
                            </li>
                        </ul>
                    </div>

                    <div class="bg-gradient-to-br from-gray-50 to-indigo-50 p-5 sm:p-8">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <h3 class="text-2xl font-bold text-gray-900">Plan PRO</h3>
                            <span class="inline-flex w-fit rounded-full bg-indigo-100 px-3 py-1 text-sm font-bold text-indigo-700">Revision manual</span>
                        </div>
                        <div class="mt-5">
                            <span class="text-4xl font-extrabold text-gray-900">L. 1,500</span>
                            <span class="text-gray-500">/ mes</span>
                        </div>

                        <ul class="mt-6 space-y-4 text-sm font-medium text-gray-800 sm:text-base">
                            <li class="flex gap-3"><span class="text-indigo-600" aria-hidden="true">✓</span> Mayor capacidad de productos, segun configuracion vigente.</li>
                            <li class="flex gap-3"><span class="text-indigo-600" aria-hidden="true">✓</span> Flujo de pedidos y checkout asistido por WhatsApp.</li>
                            <li class="flex gap-3"><span class="text-indigo-600" aria-hidden="true">✓</span> Herramientas operativas adicionales segun permisos del negocio.</li>
                            <li class="flex gap-3"><span class="text-indigo-600" aria-hidden="true">✓</span> Seguimiento de pagos desde el panel oficial.</li>
                        </ul>

                        <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                            <a
                                href="{{ route('tenant.billing.index') }}"
                                class="inline-flex flex-1 items-center justify-center rounded-xl bg-indigo-700 px-5 py-3 text-sm font-bold text-white shadow-sm transition-colors hover:bg-indigo-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-700"
                                aria-label="Abrir billing oficial para activar el plan PRO"
                            >
                                Activar desde billing oficial
                            </a>
                            <button
                                type="button"
                                wire:click="$set('showPaymentModal', true)"
                                class="inline-flex flex-1 items-center justify-center rounded-xl border border-indigo-200 bg-white px-5 py-3 text-sm font-bold text-indigo-700 transition-colors hover:bg-indigo-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-700"
                                aria-label="Ver ayuda para reportar una transferencia manual"
                            >
                                Ver ayuda de pago
                            </button>
                        </div>
                    </div>
                </div>
            </section>
        @else
            <section class="mb-8 rounded-xl border border-gray-100 bg-white p-5 shadow-sm sm:p-6" aria-labelledby="billing-status-title">
                <div class="flex flex-col gap-6 md:flex-row md:items-center md:justify-between">
                    <div class="flex items-start gap-4">
                        <div class="flex h-14 w-14 flex-shrink-0 items-center justify-center rounded-full
                            {{ $this->statusColor === 'green' ? 'bg-green-100' : '' }}
                            {{ $this->statusColor === 'yellow' ? 'bg-yellow-100' : '' }}
                            {{ $this->statusColor === 'red' ? 'bg-red-100' : '' }}
                            {{ $this->statusColor === 'gray' ? 'bg-gray-100' : '' }}
                        " aria-hidden="true">
                            @if($this->statusColor === 'green')
                                <svg class="h-7 w-7 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            @elseif($this->statusColor === 'yellow')
                                <svg class="h-7 w-7 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            @elseif($this->statusColor === 'red')
                                <svg class="h-7 w-7 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                            @else
                                <svg class="h-7 w-7 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            @endif
                        </div>
                        <div>
                            <h2 id="billing-status-title" class="text-lg font-bold text-gray-900">Estado de la suscripcion</h2>
                            <p class="mt-1 text-gray-600">{{ $this->statusMessage }}</p>
                        </div>
                    </div>

                    <a
                        href="{{ route('tenant.billing.index') }}"
                        class="inline-flex items-center justify-center rounded-xl bg-blue-700 px-5 py-3 text-sm font-bold text-white shadow-sm transition-colors hover:bg-blue-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-700"
                        aria-label="Abrir billing oficial para gestionar suscripcion y pagos"
                    >
                        Gestionar en billing oficial
                    </a>
                </div>

                @if($this->hasPendingPayment)
                    <div class="mt-4 rounded-lg border border-blue-100 bg-blue-50 p-3" role="status" aria-live="polite">
                        <p class="text-sm text-blue-900">
                            <strong>Pago en revision:</strong> hay un comprobante pendiente. Revisa el estado final desde el billing oficial.
                        </p>
                    </div>
                @endif
            </section>

            <section class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm" aria-labelledby="billing-history-title">
                <div class="border-b border-gray-100 px-5 py-4 sm:px-6">
                    <h3 id="billing-history-title" class="font-bold text-gray-900">Historial de pagos</h3>
                    <p class="mt-1 text-sm text-gray-500">Resumen de referencia. Las acciones y comprobantes viven en el panel oficial.</p>
                </div>
                <div class="overflow-x-auto" role="region" aria-label="Tabla de historial de pagos" tabindex="0">
                    <table class="min-w-full divide-y divide-gray-200">
                        <caption class="sr-only">Historial de pagos de la suscripcion</caption>
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Fecha</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Referencia</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Banco</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Monto</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Estado</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            @forelse($this->paymentHistory as $payment)
                                <tr>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">{{ $payment->created_at->format('d/m/Y h:i A') }}</td>
                                    <td class="whitespace-nowrap px-6 py-4 font-mono text-sm text-gray-900">{{ $payment->reference_number }}</td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-900">{{ $this->bankOptions[$payment->bank] ?? $payment->bank }}</td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm font-bold text-gray-900">L. {{ number_format($payment->amount, 2) }}</td>
                                    <td class="whitespace-nowrap px-6 py-4">
                                        @if($payment->status === 'approved')
                                            <span class="inline-flex rounded-full bg-green-100 px-2 py-1 text-xs font-bold text-green-800">Aprobado</span>
                                        @elseif($payment->status === 'rejected')
                                            <span class="inline-flex rounded-full bg-red-100 px-2 py-1 text-xs font-bold text-red-800">Rechazado</span>
                                        @else
                                            <span class="inline-flex rounded-full bg-yellow-100 px-2 py-1 text-xs font-bold text-yellow-800">Pendiente</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-6 py-10 text-center">
                                        <div class="mx-auto max-w-md">
                                            <p class="font-semibold text-gray-900">No hay pagos registrados en esta vista.</p>
                                            <p class="mt-1 text-sm text-gray-500">Si ya reportaste un pago, revisa el estado actualizado desde el billing oficial.</p>
                                            <a
                                                href="{{ route('tenant.billing.index') }}"
                                                class="mt-4 inline-flex items-center justify-center rounded-xl border border-blue-200 bg-blue-50 px-4 py-2 text-sm font-bold text-blue-700 transition-colors hover:bg-blue-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-700"
                                                aria-label="Abrir billing oficial para revisar pagos"
                                            >
                                                Revisar billing oficial
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        @if($showPaymentModal)
            <div
                class="fixed inset-0 z-50 overflow-y-auto"
                x-data="{ open: true }"
                x-trap.inert.noscroll="open"
                @keydown.escape.window="open && $wire.set('showPaymentModal', false)"
                role="dialog"
                aria-modal="true"
                aria-labelledby="billing-payment-modal-title"
                aria-describedby="billing-payment-modal-description"
            >
                <div class="fixed inset-0 bg-gray-900/75 transition-opacity" wire:click="$set('showPaymentModal', false)" aria-hidden="true"></div>
                <div class="flex min-h-full items-center justify-center p-4">
                    <div class="relative w-full max-w-lg rounded-2xl bg-white p-5 shadow-xl sm:p-6" tabindex="-1" x-ref="paymentModalPanel" x-init="$nextTick(() => $refs.paymentModalPanel.focus())">
                        <div class="mb-6 flex items-start justify-between gap-4">
                            <div>
                                <h3 id="billing-payment-modal-title" class="text-xl font-bold text-gray-900">Ayuda para reportar pago</h3>
                                <p id="billing-payment-modal-description" class="mt-1 text-sm text-gray-500">
                                    Usa esta ayuda solo como respaldo. Al enviar, el flujo legacy debe enviarte al billing oficial, que es donde se valida el pago.
                                </p>
                            </div>
                            <button
                                type="button"
                                wire:click="$set('showPaymentModal', false)"
                                class="rounded-full p-2 text-gray-500 transition-colors hover:bg-gray-100 hover:text-gray-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-700"
                                aria-label="Cerrar ayuda para reportar pago"
                            >
                                <span aria-hidden="true">x</span>
                            </button>
                        </div>

                        <div class="mb-6 rounded-xl border border-blue-100 bg-blue-50 p-4">
                            <p class="text-xs font-bold uppercase tracking-wide text-blue-800">Datos de referencia</p>
                            <div class="mt-3 grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
                                <div>
                                    <span class="block font-bold text-gray-900">BAC Credomatic</span>
                                    <span class="font-mono text-gray-600">777-123456-9</span>
                                </div>
                                <div>
                                    <span class="block font-bold text-gray-900">Banco Ficohsa</span>
                                    <span class="font-mono text-gray-600">200-998877-1</span>
                                </div>
                            </div>
                            <p class="mt-3 text-xs font-medium text-blue-700">Beneficiario: Vito Business OS S.A. de C.V.</p>
                        </div>

                        <div class="space-y-4">
                            <div>
                                <label for="billing-bank" class="mb-1 block text-sm font-medium text-gray-700">Banco de origen *</label>
                                <select id="billing-bank" wire:model="bank" aria-invalid="@error('bank') true @else false @enderror" aria-describedby="@error('bank') billing-bank-error @enderror" class="w-full rounded-xl border-gray-200 bg-gray-50 focus:ring-2 focus:ring-blue-500">
                                    <option value="">Selecciona un banco</option>
                                    @foreach($this->bankOptions as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('bank') <span id="billing-bank-error" class="text-xs text-red-600" role="alert">{{ $message }}</span> @enderror
                            </div>

                            <div>
                                <label for="billing-reference-number" class="mb-1 block text-sm font-medium text-gray-700">Numero de referencia *</label>
                                <input id="billing-reference-number" wire:model="reference_number" type="text" aria-invalid="@error('reference_number') true @else false @enderror" aria-describedby="@error('reference_number') billing-reference-number-error @enderror" class="w-full rounded-xl border-gray-200 bg-gray-50 focus:ring-2 focus:ring-blue-500" placeholder="Ej: 12345678">
                                @error('reference_number') <span id="billing-reference-number-error" class="text-xs text-red-600" role="alert">{{ $message }}</span> @enderror
                            </div>

                            <div>
                                <label for="billing-transfer-amount" class="mb-1 block text-sm font-medium text-gray-700">Monto transferido (L.) *</label>
                                <input id="billing-transfer-amount" wire:model="amount" type="number" step="0.01" aria-invalid="@error('amount') true @else false @enderror" aria-describedby="@error('amount') billing-transfer-amount-error @enderror" class="w-full rounded-xl border-gray-200 bg-gray-50 focus:ring-2 focus:ring-blue-500">
                                @error('amount') <span id="billing-transfer-amount-error" class="text-xs text-red-600" role="alert">{{ $message }}</span> @enderror
                            </div>

                            <div>
                                <label for="billing-proof-photo" class="mb-1 block text-sm font-medium text-gray-700">Foto del comprobante *</label>
                                <input id="billing-proof-photo" wire:model="proof_photo" type="file" accept="image/*" aria-invalid="@error('proof_photo') true @else false @enderror" aria-describedby="@error('proof_photo') billing-proof-photo-error @enderror" class="block w-full text-sm text-gray-500 file:mr-4 file:rounded-full file:border-0 file:bg-blue-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-blue-700 hover:file:bg-blue-100">
                                @error('proof_photo') <span id="billing-proof-photo-error" class="text-xs text-red-600" role="alert">{{ $message }}</span> @enderror

                                @if($proof_photo)
                                    <div class="mt-2">
                                        <img src="{{ $proof_photo->temporaryUrl() }}" alt="Vista previa del comprobante cargado" class="h-24 rounded-lg border border-gray-200">
                                    </div>
                                @endif
                            </div>
                        </div>

                        <div class="mt-8 flex flex-col-reverse gap-3 sm:flex-row">
                            <button type="button" wire:click="$set('showPaymentModal', false)" class="flex-1 rounded-xl py-3 font-medium text-gray-700 transition-colors hover:bg-gray-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gray-500">
                                Cancelar
                            </button>
                            <button
                                type="button"
                                wire:click="reportPayment"
                                wire:loading.attr="disabled"
                                class="flex-1 rounded-xl bg-blue-700 py-3 font-bold text-white shadow-sm transition-colors hover:bg-blue-800 disabled:opacity-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-700"
                                aria-label="Enviar reporte de pago al flujo oficial"
                            >
                                <span wire:loading.remove>Enviar al flujo oficial</span>
                                <span wire:loading>Enviando...</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </main>
</div>
