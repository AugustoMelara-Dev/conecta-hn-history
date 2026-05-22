<x-filament-panels::page>
    <div class="grid md:grid-cols-2 gap-6">
        {{-- Left Panel: Current Plan Status --}}
        <div class="space-y-6">
            {{-- Status Card --}}
            <x-filament::section>
                <x-slot name="heading">
                    <div class="flex items-center gap-2">
                        <x-heroicon-o-star class="w-5 h-5 text-primary-500" />
                        Tu Plan Actual
                    </div>
                </x-slot>

                @php
                    $data = $this->getSubscriptionData();
                @endphp

                <div class="space-y-4">
                    {{-- Plan Badge --}}
                    <div class="flex items-center justify-between">
                        <span class="text-gray-500 dark:text-gray-400">Plan:</span>
                        <x-filament::badge 
                            :color="$data['is_pro'] ? 'success' : 'gray'"
                            size="lg"
                        >
                            {{ $data['plan'] }}
                        </x-filament::badge>
                    </div>

                    {{-- Status with Semantic Badge --}}
                    <div class="flex items-center justify-between">
                        <span class="text-gray-500 dark:text-gray-400">Estado:</span>
                        @if($data['is_past_due'] ?? false)
                            {{-- CRITICAL: Past Due - Red Alert --}}
                            <x-filament::badge color="danger" size="lg" class="animate-pulse">
                                <x-heroicon-o-exclamation-triangle class="w-4 h-4 mr-1" />
                                VENCIDO - Servicio Suspendido
                            </x-filament::badge>
                        @elseif($data['is_trial'] ?? false)
                            {{-- TRIAL: Amber/Warning --}}
                            <x-filament::badge color="warning" size="lg">
                                <x-heroicon-o-clock class="w-4 h-4 mr-1" />
                                Periodo de Prueba
                            </x-filament::badge>
                        @elseif($data['is_pro'])
                            {{-- ACTIVE PRO: Green/Success --}}
                            <x-filament::badge color="success" size="lg">
                                <x-heroicon-o-check-badge class="w-4 h-4 mr-1" />
                                Suscripción Activa
                            </x-filament::badge>
                        @else
                            {{-- SEMILLA: Gray/Neutral --}}
                            <x-filament::badge color="gray" size="lg">
                                {{ $data['status'] }}
                            </x-filament::badge>
                        @endif
                    </div>

                    {{-- Expiry Date --}}
                    @if($data['expires_at'])
                        <div class="flex items-center justify-between">
                            <span class="text-gray-500 dark:text-gray-400">Vence:</span>
                            <span class="font-semibold {{ $data['is_expiring_soon'] ?? false ? 'text-danger-600' : '' }}">
                                {{ $data['expires_at'] }}
                            </span>
                        </div>

                        {{-- Days remaining --}}
                        @if($data['days_left'] !== null)
                            <div class="p-4 rounded-lg {{ $data['days_left'] <= 7 ? 'bg-danger-50 dark:bg-danger-950 border border-danger-200' : 'bg-primary-50 dark:bg-primary-950 border border-primary-200' }}">
                                <div class="text-center">
                                    <div class="text-3xl font-bold {{ $data['days_left'] <= 7 ? 'text-danger-600' : 'text-primary-600' }}">
                                        {{ $data['days_left'] }}
                                    </div>
                                    <div class="text-sm {{ $data['days_left'] <= 7 ? 'text-danger-600' : 'text-primary-600' }}">
                                        días restantes
                                    </div>
                                </div>
                            </div>
                        @endif
                    @else
                        <div class="p-4 rounded-lg bg-success-50 dark:bg-success-950 border border-success-200">
                            <div class="text-center text-success-700">
                                <span class="mx-auto mb-1 block text-4xl font-semibold leading-none" aria-hidden="true">&infin;</span>
                                <span class="font-medium">Acceso Perpetuo</span>
                            </div>
                        </div>
                    @endif
                </div>
            </x-filament::section>

            {{-- Features Card (for Semilla users) --}}
            @if(!$data['is_pro'])
                <x-filament::section>
                    <x-slot name="heading">
                        <div class="flex items-center gap-2">
                            <x-heroicon-o-sparkles class="w-5 h-5 text-warning-500" />
                            Beneficios Pro
                        </div>
                    </x-slot>

                    <ul class="space-y-2 text-sm">
                        <li class="flex items-center gap-2">
                            <x-heroicon-o-check-circle class="w-5 h-5 text-success-500" />
                            <span>Carrito de compras completo</span>
                        </li>
                        <li class="flex items-center gap-2">
                            <x-heroicon-o-check-circle class="w-5 h-5 text-success-500" />
                            <span>Personalización de marca (colores)</span>
                        </li>
                        <li class="flex items-center gap-2">
                            <x-heroicon-o-check-circle class="w-5 h-5 text-success-500" />
                            <span>Catalogo ampliado</span>
                        </li>
                        <li class="flex items-center gap-2">
                            <x-heroicon-o-check-circle class="w-5 h-5 text-success-500" />
                            <span>Imagen de portada personalizada</span>
                        </li>
                        <li class="flex items-center gap-2">
                            <x-heroicon-o-check-circle class="w-5 h-5 text-success-500" />
                            <span>Sin marca "Powered by Conecta"</span>
                        </li>
                    </ul>
                </x-filament::section>
            @endif
        </div>

        {{-- Right Panel: Upgrade & Payment --}}
        <div class="space-y-6">
            {{-- Pricing Card --}}
            <x-filament::section>
                <x-slot name="heading">
                    <div class="flex items-center gap-2">
                        <x-heroicon-o-credit-card class="w-5 h-5 text-primary-500" />
                        {{ $data['is_pro'] ? 'Renovar Plan' : 'Actualizar a Pro' }}
                    </div>
                </x-slot>

                @php
                    $pricing = $this->getPricing();
                @endphp

                <div class="grid grid-cols-2 gap-4 mb-6">
                    {{-- Monthly --}}
                    <div class="p-4 rounded-xl border-2 border-gray-200 dark:border-gray-700 hover:border-primary-500 transition-colors cursor-pointer">
                        <div class="text-center">
                            <div class="text-2xl font-bold text-gray-900 dark:text-white">
                                L{{ number_format($pricing['monthly']['price']) }}
                            </div>
                            <div class="text-sm text-gray-500">/mes</div>
                        </div>
                    </div>

                    {{-- Annual --}}
                    <div class="p-4 rounded-xl border-2 border-primary-500 bg-primary-50 dark:bg-primary-950 relative">
                        <div class="absolute -top-2 left-1/2 -translate-x-1/2">
                            <x-filament::badge color="warning" size="sm">
                                {{ $pricing['annual']['discount'] }}
                            </x-filament::badge>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-primary-600">
                                L{{ number_format($pricing['annual']['price']) }}
                            </div>
                            <div class="text-sm text-primary-600">/año</div>
                        </div>
                    </div>
                </div>
            </x-filament::section>

            {{-- Bank Transfer Instructions --}}
            <x-filament::section>
                <x-slot name="heading">
                    <div class="flex items-center gap-2">
                        <x-heroicon-o-building-library class="w-5 h-5 text-primary-500" />
                        Instrucciones de Pago
                    </div>
                </x-slot>

                @php
                    $bank = $this->getBankInfo();
                @endphp

                <div class="space-y-3 text-sm">
                    <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                        <span class="text-gray-500">Banco:</span>
                        <span class="font-semibold">{{ $bank['bank_name'] }}</span>
                    </div>
                    <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                        <span class="text-gray-500">Tipo:</span>
                        <span class="font-semibold">{{ $bank['account_type'] }}</span>
                    </div>
                    <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                        <span class="text-gray-500">Cuenta:</span>
                        <span class="font-mono font-bold text-primary-600">{{ $bank['account_number'] }}</span>
                    </div>
                    <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                        <span class="text-gray-500">Nombre:</span>
                        <span class="font-semibold">{{ $bank['account_holder'] }}</span>
                    </div>
                </div>

                <div class="mt-6 space-y-3">
                    <a 
                        href="{{ $this->getWhatsAppPaymentUrl() }}"
                        target="_blank"
                        class="flex items-center justify-center gap-2 w-full px-4 py-3 bg-success-600 hover:bg-success-500 text-white font-semibold rounded-xl transition-colors"
                    >
                        <x-heroicon-o-chat-bubble-left-ellipsis class="w-5 h-5" />
                        Enviar Comprobante por WhatsApp
                    </a>

                    <p class="text-xs text-center text-gray-500">
                        Después de realizar tu transferencia, envíanos el comprobante y activaremos tu plan en menos de 24 horas.
                    </p>
                </div>
            </x-filament::section>
        </div>
    </div>
</x-filament-panels::page>
