<x-filament-panels::page>
    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    {{-- ESTADO 1: PAGO PENDIENTE DE REVISIÓN --}}
    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    @if($isPending)
    <div class="max-w-2xl mx-auto">
        <div class="bg-white dark:bg-gray-800 rounded-3xl shadow-lg overflow-hidden">
            {{-- Header con animación --}}
            <div class="bg-gradient-to-br from-amber-400 via-amber-500 to-orange-500 py-10 px-8 text-center relative overflow-hidden">
                {{-- Animación de fondo --}}
                <div class="absolute inset-0 opacity-20">
                    <div class="absolute top-0 left-0 w-full h-full" style="background: repeating-linear-gradient(45deg, transparent, transparent 10px, rgba(255,255,255,0.1) 10px, rgba(255,255,255,0.1) 20px);"></div>
                </div>
                
                <div class="relative">
                    {{-- Icono animado --}}
                    <div class="inline-flex items-center justify-center w-20 h-20 bg-white/20 rounded-full mb-4 backdrop-blur-sm">
                        <svg class="w-10 h-10 text-white animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    
                    <h2 class="text-2xl font-bold text-white mb-2">
                        ✋ Tu pago está en revisión
                    </h2>
                    <p class="text-white/90">
                        Referencia: <span class="font-mono font-bold">{{ $pendingPayment->bank_reference ?? 'N/A' }}</span>
                    </p>
                </div>
            </div>

            {{-- Contenido --}}
            <div class="p-8">
                <div class="bg-gray-50 dark:bg-gray-900 rounded-2xl p-6 mb-6">
                    <div class="flex items-start gap-4">
                        <div class="p-2 bg-blue-100 dark:bg-blue-900/30 rounded-lg">
                            <x-heroicon-o-shield-check class="w-6 h-6 text-blue-600 dark:text-blue-400" />
                        </div>
                        <div>
                            <h3 class="font-semibold text-gray-900 dark:text-white mb-2">¿Qué sigue?</h3>
                            <p class="text-gray-600 dark:text-gray-400 text-sm leading-relaxed">
                                Hemos recibido tu comprobante de pago. Nuestro equipo está validando la transacción 
                                con el banco. Este proceso generalmente toma <strong>menos de 2 horas</strong> en días hábiles.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="space-y-4">
                    <div class="flex items-center gap-3 text-sm">
                        <span class="w-6 h-6 rounded-full bg-green-100 dark:bg-green-900/30 flex items-center justify-center">
                            <x-heroicon-s-check class="w-4 h-4 text-green-600 dark:text-green-400" />
                        </span>
                        <span class="text-gray-600 dark:text-gray-400">Comprobante recibido</span>
                    </div>
                    <div class="flex items-center gap-3 text-sm">
                        <span class="w-6 h-6 rounded-full bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center animate-pulse">
                            <x-heroicon-s-clock class="w-4 h-4 text-amber-600 dark:text-amber-400" />
                        </span>
                        <span class="text-gray-900 dark:text-white font-medium">Validando con el banco</span>
                    </div>
                    <div class="flex items-center gap-3 text-sm">
                        <span class="w-6 h-6 rounded-full bg-gray-100 dark:bg-gray-700 flex items-center justify-center">
                            <x-heroicon-o-sparkles class="w-4 h-4 text-gray-400" />
                        </span>
                        <span class="text-gray-400">Activar Plan PRO</span>
                    </div>
                </div>

                <p class="text-center text-sm text-gray-500 dark:text-gray-400 mt-6">
                    Te notificaremos por email cuando tu plan esté activo.
                </p>
            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    {{-- ESTADO 2: USUARIO PRO ACTIVO --}}
    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    @elseif($isPro)
    <div class="space-y-6">
        {{-- Banner de Estado PRO --}}
        <div class="bg-gradient-to-br from-emerald-500 via-green-500 to-teal-500 rounded-2xl p-6 text-white relative overflow-hidden">
            {{-- Decoración --}}
            <div class="absolute top-0 right-0 w-64 h-64 bg-white/10 rounded-full -translate-y-32 translate-x-32"></div>
            <div class="absolute bottom-0 left-0 w-48 h-48 bg-white/5 rounded-full translate-y-24 -translate-x-24"></div>
            
            <div class="relative flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <div class="p-3 bg-white/20 rounded-xl backdrop-blur-sm">
                        <x-heroicon-s-shield-check class="w-8 h-8 text-white" />
                    </div>
                    <div>
                        <h2 class="text-2xl font-bold">PLAN EMPRESARIO PRO</h2>
                        <p class="text-white/80">Acceso completo a todas las herramientas</p>
                    </div>
                </div>
                
                @if($expiresAt)
                <div class="bg-white/20 backdrop-blur-sm rounded-xl px-5 py-3">
                    <div class="text-sm text-white/80">Válido hasta</div>
                    <div class="text-xl font-bold">{{ $expiresAt }}</div>
                    <div class="text-sm">
                        @if($daysRemaining > 7)
                            <span class="text-white/80">{{ $daysRemaining }} días restantes</span>
                        @elseif($daysRemaining > 0)
                            <span class="text-amber-200 font-medium">⚠️ {{ $daysRemaining }} días restantes</span>
                        @else
                            <span class="text-red-200 font-medium">⏰ Vence hoy</span>
                        @endif
                    </div>
                </div>
                @endif
            </div>
        </div>

        {{-- Alerta de renovación si quedan pocos días --}}
        @if($daysRemaining <= 7 && $daysRemaining > 0)
        <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-xl p-4">
            <div class="flex items-start gap-3">
                <x-heroicon-o-exclamation-triangle class="w-6 h-6 text-amber-600 dark:text-amber-400 flex-shrink-0" />
                <div>
                    <h3 class="font-semibold text-amber-800 dark:text-amber-300">Tu suscripción expira pronto</h3>
                    <p class="text-sm text-amber-700 dark:text-amber-400 mt-1">
                        Para mantener el acceso a todas las herramientas PRO, renueva tu suscripción antes del {{ $expiresAt }}.
                    </p>
                </div>
            </div>
        </div>
        @endif

        {{-- Historial de Pagos --}}
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Historial de Pagos</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400">Todos tus pagos y recibos</p>
            </div>
            <div class="p-6">
                {{ $this->table }}
            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    {{-- ESTADO 3: USUARIO FREE - WIZARD DE PAGO --}}
    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    @else
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Columna Principal: Wizard --}}
        <div class="lg:col-span-2">
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700 bg-gradient-to-r from-violet-500 to-purple-600">
                    <h3 class="text-lg font-bold text-white">Activar Plan PRO</h3>
                    <p class="text-sm text-white/80">Sigue los pasos para completar tu pago</p>
                </div>
                <div class="p-6">
                    {{ $this->form }}
                </div>
            </div>
        </div>

        {{-- Columna Lateral: Beneficios --}}
        <div class="space-y-6">
            {{-- Resumen del Plan --}}
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm p-6">
                <div class="text-center mb-6">
                    <div class="inline-flex items-center justify-center w-16 h-16 bg-gradient-to-br from-amber-400 to-orange-500 rounded-2xl mb-4">
                        <x-heroicon-s-star class="w-8 h-8 text-white" />
                    </div>
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">Plan Empresario PRO</h3>
                    <div class="mt-2">
                        <span class="text-4xl font-bold text-gray-900 dark:text-white">{{ $formattedPrice }}</span>
                        <span class="text-gray-500 dark:text-gray-400">/mes</span>
                    </div>
                </div>

                <div class="space-y-3">
                    <div class="flex items-center gap-3 text-sm">
                        <x-heroicon-s-check-circle class="w-5 h-5 text-green-500" />
                        <span class="text-gray-700 dark:text-gray-300">Productos ilimitados</span>
                    </div>
                    <div class="flex items-center gap-3 text-sm">
                        <x-heroicon-s-check-circle class="w-5 h-5 text-green-500" />
                        <span class="text-gray-700 dark:text-gray-300">Cartelería QR profesional</span>
                    </div>
                    <div class="flex items-center gap-3 text-sm">
                        <x-heroicon-s-check-circle class="w-5 h-5 text-green-500" />
                        <span class="text-gray-700 dark:text-gray-300">WhatsApp personalizable</span>
                    </div>
                    <div class="flex items-center gap-3 text-sm">
                        <x-heroicon-s-check-circle class="w-5 h-5 text-green-500" />
                        <span class="text-gray-700 dark:text-gray-300">Banner y colores de marca</span>
                    </div>
                    <div class="flex items-center gap-3 text-sm">
                        <x-heroicon-s-check-circle class="w-5 h-5 text-green-500" />
                        <span class="text-gray-700 dark:text-gray-300">Mapa GPS integrado</span>
                    </div>
                    <div class="flex items-center gap-3 text-sm">
                        <x-heroicon-s-check-circle class="w-5 h-5 text-green-500" />
                        <span class="text-gray-700 dark:text-gray-300">Horarios detallados</span>
                    </div>
                    <div class="flex items-center gap-3 text-sm">
                        <x-heroicon-s-check-circle class="w-5 h-5 text-green-500" />
                        <span class="text-gray-700 dark:text-gray-300">Redes sociales enlazadas</span>
                    </div>
                    <div class="flex items-center gap-3 text-sm">
                        <x-heroicon-s-check-circle class="w-5 h-5 text-green-500" />
                        <span class="text-gray-700 dark:text-gray-300">Soporte prioritario</span>
                    </div>
                </div>
            </div>

            {{-- Garantía --}}
            <div class="bg-green-50 dark:bg-green-900/20 rounded-2xl p-5">
                <div class="flex items-start gap-3">
                    <x-heroicon-o-shield-check class="w-6 h-6 text-green-600 dark:text-green-400 flex-shrink-0" />
                    <div>
                        <h4 class="font-semibold text-green-800 dark:text-green-300">100% Seguro</h4>
                        <p class="text-sm text-green-700 dark:text-green-400 mt-1">
                            Pago por transferencia bancaria directa. Tu información está protegida.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif
</x-filament-panels::page>
