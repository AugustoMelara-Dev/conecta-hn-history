{{--
    Schedule Settings - Bifurcated UI
    
    FREE: Simple text input with upsell
    PRO: Granular 7-day matrix with toggles
--}}

<div class="min-h-screen bg-gray-50">
    {{-- HEADER --}}
    <header class="bg-white border-b border-gray-200 sticky top-0 z-40">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <div class="flex items-center gap-3">
                    <a href="{{ route('tenant.settings.profile') }}" wire:navigate aria-label="Volver al perfil del negocio" class="text-gray-500 hover:text-gray-700">
                        <svg class="w-5 h-5" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                        </svg>
                    </a>
                    <h1 class="text-xl font-bold text-gray-900">Horarios</h1>
                </div>
                <button
                    wire:click="save"
                    wire:loading.attr="disabled"
                    aria-label="Guardar horarios del negocio"
                    class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-xl shadow-lg shadow-blue-500/25 transition-all"
                >
                    <span wire:loading.remove>Guardar</span>
                    <span wire:loading>Guardando...</span>
                </button>
            </div>
        </div>
    </header>

    <main class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        @if(!$this->isPro)
            {{-- ═══════════════════════════════════════════════════════════ --}}
            {{-- FREE: Simple Text Schedule --}}
            {{-- ═══════════════════════════════════════════════════════════ --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-6">
                <div class="mb-4">
                    <label for="settings-simple-schedule" class="block text-sm font-medium text-gray-700 mb-1">Horario visible</label>
                    <input
                        id="settings-simple-schedule"
                        wire:model="simpleSchedule"
                        type="text"
                        aria-describedby="settings-simple-schedule-help @error('simpleSchedule') settings-simple-schedule-error @enderror"
                        aria-invalid="@error('simpleSchedule') true @else false @enderror"
                        placeholder="Ej: Lun-Vie 8am a 5pm, Sáb 9am a 1pm"
                        class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    />
                    <p id="settings-simple-schedule-help" class="mt-2 text-sm text-gray-500">Este texto aparecerá en tu tienda tal como lo escribas.</p>
                    @error('simpleSchedule') <p id="settings-simple-schedule-error" class="mt-1 text-sm text-red-500" role="alert">{{ $message }}</p> @enderror
                </div>
            </div>

            {{-- Upsell Card --}}
            <div class="bg-gradient-to-br from-amber-50 to-yellow-50 rounded-xl border-2 border-amber-200 p-6">
                <div class="flex items-start gap-4">
                    <div class="p-3 bg-amber-100 rounded-xl">
                        <svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                        </svg>
                    </div>
                    <div class="flex-1">
                        <h3 class="font-bold text-gray-900 mb-2">Automatiza tu estado con PRO</h3>
                        <p class="text-sm text-gray-600 mb-4">
                            Con PRO puedes configurar horarios por día para mostrar un estado de abierto o cerrado según la información que registres.
                        </p>

                        {{-- Preview Badge --}}
                        <div class="bg-white rounded-lg p-4 mb-4 border border-amber-100">
                            <div class="flex items-center gap-3">
                                <span class="relative flex h-3 w-3">
                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                                    <span class="relative inline-flex rounded-full h-3 w-3 bg-green-500"></span>
                                </span>
                                <span class="text-sm font-medium text-green-600">Abierto ahora</span>
                                <span class="text-xs text-gray-400">• Cierra a las 5:00 PM</span>
                            </div>
                            <p class="text-xs text-gray-500 mt-2">Ejemplo visual de cómo se mostraría en tu tienda</p>
                        </div>

                        <a 
                            href="{{ route('tenant.billing.index') }}" 
                            wire:navigate
                            aria-label="Ver opciones PRO para horarios del negocio"
                            class="inline-flex items-center gap-2 px-4 py-2 bg-amber-500 hover:bg-amber-600 text-white font-medium rounded-lg transition-colors"
                        >
                            Ver opciones PRO
                        </a>
                    </div>
                </div>
            </div>

        @else
            {{-- ═══════════════════════════════════════════════════════════ --}}
            {{-- PRO: Granular Schedule Matrix --}}
            {{-- ═══════════════════════════════════════════════════════════ --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <div class="mb-6">
                    <h2 class="font-semibold text-gray-900">Horarios por día</h2>
                    <p class="text-sm text-gray-500">Configura el horario de cada día. El estado público se mostrará según estos datos.</p>
                </div>

                <div class="space-y-3">
                    @foreach($this->dayLabels as $day => $label)
                        <div class="flex items-center gap-4 p-4 bg-gray-50 rounded-xl {{ !($structuredSchedule[$day]['isOpen'] ?? false) ? 'opacity-60' : '' }}">
                            {{-- Day Label --}}
                            <div class="w-28 font-medium text-gray-900">{{ $label }}</div>

                            {{-- Toggle --}}
                            <button
                                type="button"
                                wire:click="$set('structuredSchedule.{{ $day }}.isOpen', {{ ($structuredSchedule[$day]['isOpen'] ?? false) ? 'false' : 'true' }})"
                                role="switch"
                                aria-checked="{{ ($structuredSchedule[$day]['isOpen'] ?? false) ? 'true' : 'false' }}"
                                aria-label="{{ ($structuredSchedule[$day]['isOpen'] ?? false) ? 'Marcar '.$label.' como cerrado' : 'Marcar '.$label.' como abierto' }}"
                                class="relative w-12 h-7 rounded-full transition-colors {{ ($structuredSchedule[$day]['isOpen'] ?? false) ? 'bg-emerald-500' : 'bg-gray-300' }}"
                            >
                                <span class="absolute top-1 {{ ($structuredSchedule[$day]['isOpen'] ?? false) ? 'right-1' : 'left-1' }} w-5 h-5 bg-white rounded-full shadow transition-all"></span>
                            </button>

                            {{-- Status Text --}}
                            <span class="w-20 text-sm {{ ($structuredSchedule[$day]['isOpen'] ?? false) ? 'text-emerald-600' : 'text-gray-500' }}">
                                {{ ($structuredSchedule[$day]['isOpen'] ?? false) ? 'Abierto' : 'Cerrado' }}
                            </span>

                            {{-- Time Inputs --}}
                            <div class="flex items-center gap-2 ml-auto">
                                <input
                                    type="time"
                                    wire:model="structuredSchedule.{{ $day }}.open"
                                    aria-label="Hora de apertura para {{ $label }}"
                                    {{ !($structuredSchedule[$day]['isOpen'] ?? false) ? 'disabled' : '' }}
                                    class="px-3 py-2 bg-white border border-gray-200 rounded-lg text-sm disabled:opacity-40 disabled:cursor-not-allowed"
                                />
                                <span class="text-gray-400">—</span>
                                <input
                                    type="time"
                                    wire:model="structuredSchedule.{{ $day }}.close"
                                    aria-label="Hora de cierre para {{ $label }}"
                                    {{ !($structuredSchedule[$day]['isOpen'] ?? false) ? 'disabled' : '' }}
                                    class="px-3 py-2 bg-white border border-gray-200 rounded-lg text-sm disabled:opacity-40 disabled:cursor-not-allowed"
                                />
                            </div>
                        </div>

                        @error("structuredSchedule.{$day}")
                            <p class="text-sm text-red-500 ml-4">{{ $message }}</p>
                        @enderror
                    @endforeach
                </div>

                {{-- Info Banner --}}
                <div class="mt-6 p-4 bg-blue-50 rounded-lg border border-blue-100">
                    <div class="flex items-center gap-2">
                        <span class="relative flex h-3 w-3">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-3 w-3 bg-green-500"></span>
                        </span>
                        <span class="text-sm text-blue-800">
                            <strong>Horario publicado:</strong> El estado se mostrará con base en los horarios que guardes aquí.
                        </span>
                    </div>
                </div>
            </div>
        @endif
    </main>
</div>
