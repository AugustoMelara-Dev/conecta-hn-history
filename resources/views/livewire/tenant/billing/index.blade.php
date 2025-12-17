{{--
    Billing Index - Subscription Management
    
    Polymorphic UI:
    - FREE: Aggressive upsell landing
    - PRO: Subscription dashboard with payment history
--}}

<div class="min-h-screen bg-gray-50 pb-12">
    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    {{-- HEADER --}}
    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    <header class="bg-white border-b border-gray-200 sticky top-0 z-40">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <h1 class="text-xl font-bold text-gray-900">💳 Facturación</h1>
                @if($this->isPro)
                    <span class="px-3 py-1 bg-emerald-100 text-emerald-700 text-sm font-bold rounded-full">PLAN PRO</span>
                @else
                    <span class="px-3 py-1 bg-gray-100 text-gray-600 text-sm font-bold rounded-full">PLAN GRATUITO</span>
                @endif
            </div>
        </div>
    </header>

    {{-- Admin Navigation --}}
    <x-ui.admin-nav />

    <main class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        @if(!$this->isPro)
            {{-- ═══════════════════════════════════════════════════════════ --}}
            {{-- UPSELL LANDING (FREE USER) --}}
            {{-- ═══════════════════════════════════════════════════════════ --}}
            <div class="bg-white rounded-2xl shadow-xl overflow-hidden border border-indigo-100">
                <div class="bg-gradient-to-r from-indigo-600 to-purple-600 px-8 py-12 text-center text-white">
                    <h2 class="text-3xl font-extrabold mb-4">Lleva tu Negocio al Siguiente Nivel</h2>
                    <p class="text-indigo-100 text-lg max-w-2xl mx-auto">
                        Desbloquea el poder del E-commerce real. Automatiza tus pedidos y profesionaliza tu imagen.
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-0">
                    {{-- FREE Column --}}
                    <div class="p-8 border-b md:border-b-0 md:border-r border-gray-100">
                        <h3 class="text-lg font-bold text-gray-500 mb-6">Plan Actual (Gratis)</h3>
                        <ul class="space-y-4">
                            <li class="flex items-center text-gray-600">
                                <svg class="w-5 h-5 text-green-500 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                Catálogo Básico (10 productos)
                            </li>
                            <li class="flex items-center text-gray-600">
                                <svg class="w-5 h-5 text-green-500 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                Link de Tienda
                            </li>
                            <li class="flex items-center text-gray-400">
                                <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                Pedidos por WhatsApp
                            </li>
                            <li class="flex items-center text-gray-400">
                                <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                Gestión de Pedidos (Kanban)
                            </li>
                            <li class="flex items-center text-gray-400">
                                <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                Reportes Avanzados
                            </li>
                        </ul>
                    </div>

                    {{-- PRO Column --}}
                    <div class="p-8 bg-gradient-to-br from-gray-50 to-indigo-50">
                        <div class="flex justify-between items-center mb-6">
                            <h3 class="text-2xl font-bold text-gray-900">Plan PRO</h3>
                            <span class="px-3 py-1 bg-indigo-100 text-indigo-700 font-bold rounded-full text-sm">RECOMENDADO</span>
                        </div>
                        <div class="mb-8">
                            <span class="text-4xl font-extrabold text-gray-900">L. 1,500</span>
                            <span class="text-gray-500">/ mes</span>
                        </div>

                        <ul class="space-y-4 mb-8">
                            <li class="flex items-center text-gray-800 font-medium">
                                <svg class="w-5 h-5 text-indigo-500 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                Productos Ilimitados
                            </li>
                            <li class="flex items-center text-gray-800 font-medium">
                                <svg class="w-5 h-5 text-indigo-500 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                Carrito + WhatsApp Checkout
                            </li>
                            <li class="flex items-center text-gray-800 font-medium">
                                <svg class="w-5 h-5 text-indigo-500 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                Tablero de Pedidos (Kanban)
                            </li>
                            <li class="flex items-center text-gray-800 font-medium">
                                <svg class="w-5 h-5 text-indigo-500 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                Reportes y Analytics
                            </li>
                            <li class="flex items-center text-gray-800 font-medium">
                                <svg class="w-5 h-5 text-indigo-500 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                Soporte Prioritario
                            </li>
                        </ul>

                        <button
                            wire:click="$set('showPaymentModal', true)"
                            class="w-full py-4 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl shadow-lg hover:shadow-xl transition-all transform hover:-translate-y-0.5"
                        >
                            🚀 Contratar Plan PRO
                        </button>
                    </div>
                </div>
            </div>

        @else
            {{-- ═══════════════════════════════════════════════════════════ --}}
            {{-- SUBSCRIPTION MANAGER (PRO USER) --}}
            {{-- ═══════════════════════════════════════════════════════════ --}}

            {{-- Status Card --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-8">
                <div class="flex flex-col md:flex-row items-center justify-between gap-6">
                    <div class="flex items-center gap-4">
                        <div class="w-16 h-16 rounded-full flex items-center justify-center
                            {{ $this->statusColor === 'green' ? 'bg-green-100' : '' }}
                            {{ $this->statusColor === 'yellow' ? 'bg-yellow-100' : '' }}
                            {{ $this->statusColor === 'red' ? 'bg-red-100' : '' }}
                            {{ $this->statusColor === 'gray' ? 'bg-gray-100' : '' }}
                        ">
                            @if($this->statusColor === 'green')
                                <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            @elseif($this->statusColor === 'yellow')
                                <svg class="w-8 h-8 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            @elseif($this->statusColor === 'red')
                                <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                            @else
                                <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            @endif
                        </div>
                        <div>
                            <h2 class="text-lg font-bold text-gray-900">Estado de la Suscripción</h2>
                            <p class="text-gray-600">{{ $this->statusMessage }}</p>
                        </div>
                    </div>

                    <button
                        wire:click="$set('showPaymentModal', true)"
                        class="px-6 py-3 bg-blue-600 text-white font-bold rounded-xl hover:bg-blue-700 shadow-md transition-colors flex items-center gap-2"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        Reportar Pago
                    </button>
                </div>

                @if($this->hasPendingPayment)
                    <div class="mt-4 p-3 bg-blue-50 rounded-lg border border-blue-100">
                        <p class="text-sm text-blue-800">
                            📋 <strong>Pago en revisión:</strong> Tienes un pago pendiente de verificación. Te notificaremos cuando sea aprobado.
                        </p>
                    </div>
                @endif
            </div>

            {{-- Payment History --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100">
                    <h3 class="font-bold text-gray-900">Historial de Pagos</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Referencia</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Banco</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Monto</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($this->paymentHistory as $payment)
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ $payment->created_at->format('d/m/Y h:i A') }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-mono text-gray-900">
                                        {{ $payment->reference_number }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ $this->bankOptions[$payment->bank] ?? $payment->bank }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-900">
                                        L. {{ number_format($payment->amount, 2) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @if($payment->status === 'approved')
                                            <span class="px-2 py-1 text-xs font-bold rounded-full bg-green-100 text-green-800">APROBADO</span>
                                        @elseif($payment->status === 'rejected')
                                            <span class="px-2 py-1 text-xs font-bold rounded-full bg-red-100 text-red-800">RECHAZADO</span>
                                        @else
                                            <span class="px-2 py-1 text-xs font-bold rounded-full bg-yellow-100 text-yellow-800">PENDIENTE</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-6 py-8 text-center text-gray-500">
                                        No hay pagos registrados aún.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        {{-- ═══════════════════════════════════════════════════════════ --}}
        {{-- PAYMENT REPORT MODAL --}}
        {{-- ═══════════════════════════════════════════════════════════ --}}
        @if($showPaymentModal)
            <div class="fixed inset-0 z-50 overflow-y-auto">
                <div class="fixed inset-0 bg-gray-900/75 transition-opacity" wire:click="$set('showPaymentModal', false)"></div>
                <div class="flex min-h-full items-center justify-center p-4">
                    <div class="relative bg-white rounded-2xl shadow-xl max-w-lg w-full p-6">
                        <div class="mb-6">
                            <h3 class="text-xl font-bold text-gray-900">💸 Reportar Transferencia</h3>
                            <p class="text-sm text-gray-500 mt-1">Realiza la transferencia y luego completa este formulario.</p>
                        </div>

                        {{-- Bank Instructions --}}
                        <div class="bg-blue-50 p-4 rounded-xl mb-6 border border-blue-100">
                            <p class="text-xs font-bold text-blue-800 uppercase mb-3">Cuentas Bancarias:</p>
                            <div class="grid grid-cols-2 gap-4 text-sm">
                                <div>
                                    <span class="font-bold text-gray-900 block">BAC Credomatic</span>
                                    <span class="font-mono text-gray-600">777-123456-9</span>
                                </div>
                                <div>
                                    <span class="font-bold text-gray-900 block">Banco Ficohsa</span>
                                    <span class="font-mono text-gray-600">200-998877-1</span>
                                </div>
                            </div>
                            <p class="text-xs text-blue-600 mt-3 font-medium">Beneficiario: Conecta HN S.A. de C.V.</p>
                        </div>

                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Banco de Origen *</label>
                                <select wire:model="bank" class="w-full rounded-xl border-gray-200 bg-gray-50 focus:ring-2 focus:ring-blue-500">
                                    <option value="">Selecciona un banco</option>
                                    @foreach($this->bankOptions as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('bank') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Número de Referencia *</label>
                                <input wire:model="reference_number" type="text" class="w-full rounded-xl border-gray-200 bg-gray-50 focus:ring-2 focus:ring-blue-500" placeholder="Ej: 12345678">
                                @error('reference_number') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Monto Transferido (L.) *</label>
                                <input wire:model="amount" type="number" step="0.01" class="w-full rounded-xl border-gray-200 bg-gray-50 focus:ring-2 focus:ring-blue-500">
                                @error('amount') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Foto del Comprobante *</label>
                                <input wire:model="proof_photo" type="file" accept="image/*" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                                @error('proof_photo') <span class="text-xs text-red-500">{{ $message }}</span> @enderror

                                @if($proof_photo)
                                    <div class="mt-2">
                                        <img src="{{ $proof_photo->temporaryUrl() }}" alt="Preview" class="h-24 rounded-lg border border-gray-200">
                                    </div>
                                @endif
                            </div>
                        </div>

                        <div class="mt-8 flex gap-3">
                            <button wire:click="$set('showPaymentModal', false)" class="flex-1 py-3 text-gray-700 font-medium hover:bg-gray-100 rounded-xl transition-colors">
                                Cancelar
                            </button>
                            <button
                                wire:click="reportPayment"
                                wire:loading.attr="disabled"
                                class="flex-1 py-3 bg-blue-600 text-white font-bold rounded-xl hover:bg-blue-700 shadow-lg disabled:opacity-50 transition-all"
                            >
                                <span wire:loading.remove>Enviar Reporte</span>
                                <span wire:loading>Enviando...</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </main>
</div>
