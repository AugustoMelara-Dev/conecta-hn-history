<div class="min-h-screen bg-gradient-to-br from-slate-50 via-white to-blue-50">
    {{-- Elementos decorativos de fondo --}}
    <div class="fixed inset-0 overflow-hidden pointer-events-none">
        <div class="absolute -top-40 -right-40 w-80 h-80 bg-blue-200/30 rounded-full blur-3xl"></div>
        <div class="absolute -bottom-40 -left-40 w-80 h-80 bg-indigo-200/30 rounded-full blur-3xl"></div>
    </div>

    {{-- Container Principal --}}
    <div class="relative z-10 flex flex-col items-center justify-center min-h-screen px-4 py-12">
        
        {{-- Logo --}}
        <div class="mb-8">
            <a href="/" wire:navigate class="flex items-center gap-2">
                <div class="w-10 h-10 bg-gradient-to-br from-blue-600 to-indigo-600 rounded-xl flex items-center justify-center">
                    <span class="text-white font-bold text-lg">C</span>
                </div>
                <span class="text-xl font-bold text-gray-900">Conecta<span class="text-blue-600">HN</span></span>
            </a>
        </div>

        {{-- Progress Bar (Pasos) --}}
        <div class="w-full max-w-md mb-8">
            <div class="flex items-center justify-between">
                @foreach(range(1, $totalSteps) as $step)
                    <div class="flex items-center {{ $step < $totalSteps ? 'flex-1' : '' }}">
                        <div 
                            wire:click="goToStep({{ $step }})"
                            class="w-10 h-10 rounded-full flex items-center justify-center text-sm font-semibold transition-all duration-300 cursor-pointer
                                {{ $currentStep >= $step 
                                    ? 'bg-blue-600 text-white' 
                                    : 'bg-gray-200 text-gray-500' }}"
                        >
                            @if($currentStep > $step)
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                            @else
                                {{ $step }}
                            @endif
                        </div>
                        @if($step < $totalSteps)
                            <div class="flex-1 h-1 mx-2 {{ $currentStep > $step ? 'bg-blue-600' : 'bg-gray-200' }} transition-all duration-300"></div>
                        @endif
                    </div>
                @endforeach
            </div>
            <div class="flex justify-between mt-2 text-xs text-gray-500">
                <span>Tu URL</span>
                <span>Cuenta</span>
                <span>Negocio</span>
                <span>Plan</span>
            </div>
        </div>

        {{-- Card Principal --}}
        <div class="w-full max-w-lg bg-white/80 backdrop-blur-sm rounded-3xl shadow-xl shadow-gray-200/50 p-8 md:p-10">
            
            {{-- Global Error Alert --}}
            @if(session('error'))
                <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-xl flex gap-3 text-red-700 text-sm animate-shake">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span class="font-medium">{{ session('error') }}</span>
                </div>
            @endif

            {{-- ═══════════════════════════════════════════════════════════════ --}}
            {{-- PASO 1: IDENTITY CLAIM (El Hero Input) --}}
            {{-- ═══════════════════════════════════════════════════════════════ --}}
            @if($currentStep === 1)
                <div class="text-center mb-8">
                    <h1 class="text-2xl md:text-3xl font-bold text-gray-900 mb-2">
                        Reclama tu dirección
                    </h1>
                    <p class="text-gray-600">
                        Elige la URL única de tu negocio
                    </p>
                </div>

                {{-- Hero Input Container --}}
                <div class="space-y-6">
                    {{-- El Input Hero --}}
                    <div class="relative">
                        <input
                            wire:model.live.debounce.500ms="business_name"
                            type="text"
                            placeholder="Mi Negocio"
                            autofocus
                            autocomplete="off"
                            class="w-full text-3xl md:text-4xl lg:text-5xl font-bold text-center border-0 border-b-4 bg-transparent pb-4 transition-all duration-300 focus:outline-none focus:ring-0 placeholder-gray-300 break-words
                                @if($isAvailable) border-emerald-500 text-emerald-600
                                @elseif($isReserved || $isTaken) border-amber-500 text-amber-600
                                @else border-gray-300 text-gray-900
                                @endif"
                            style="word-break: break-word;"
                        />

                        {{-- Loading Spinner --}}
                        <div wire:loading wire:target="business_name" class="absolute right-0 top-1/2 -translate-y-1/2">
                            <svg class="animate-spin h-6 w-6 text-gray-400" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
                            </svg>
                        </div>
                    </div>

                    {{-- URL Preview --}}
                    @if(strlen($slug) >= 3)
                        <div 
                            class="flex items-center justify-center gap-2 text-lg transition-all duration-300
                                @if($isAvailable) text-emerald-600
                                @elseif($isReserved || $isTaken) text-amber-600
                                @else text-gray-500
                                @endif"
                        >
                            <span class="text-gray-400">conecta.hn/</span>
                            <span class="font-semibold">{{ $slug }}</span>
                            
                            @if($isAvailable)
                                <svg class="w-6 h-6 text-emerald-500 animate-bounce" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            @elseif($isReserved || $isTaken)
                                <svg class="w-6 h-6 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                </svg>
                            @endif
                        </div>

                        {{-- Mensaje de Estado --}}
                        @if($availabilityMessage)
                            <p class="text-center text-sm font-medium
                                @if($isAvailable) text-emerald-600
                                @else text-amber-600
                                @endif">
                                {{ $availabilityMessage }}
                            </p>
                        @endif

                        {{-- Sugerencias Inteligentes --}}
                        @if(count($suggestions) > 0)
                            <div class="space-y-3">
                                <p class="text-center text-sm text-gray-500">
                                    Prueba con estas alternativas:
                                </p>
                                <div class="flex flex-wrap justify-center gap-2">
                                    @foreach($suggestions as $suggestion)
                                        <button
                                            wire:click="selectSuggestion('{{ $suggestion }}')"
                                            type="button"
                                            class="px-4 py-2 bg-blue-50 hover:bg-blue-100 text-blue-600 rounded-full text-sm font-medium transition-all duration-200 hover:scale-105"
                                        >
                                            {{ $suggestion }}
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    @endif

                    {{-- Error de Validación --}}
                    @error('slug')
                        <p class="text-center text-sm text-red-500">{{ $message }}</p>
                    @enderror

                    {{-- Botón Continuar (Solo visible cuando está disponible) --}}
                    <div 
                        x-data="{ show: false }"
                        x-init="$watch('$wire.isAvailable', value => show = value)"
                        x-show="show"
                        x-transition:enter="transition ease-out duration-300"
                        x-transition:enter-start="opacity-0 translate-y-4"
                        x-transition:enter-end="opacity-100 translate-y-0"
                        class="pt-4"
                    >
                        <button
                            wire:click="nextStep"
                            type="button"
                            class="w-full py-4 px-6 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white font-semibold rounded-2xl shadow-lg shadow-blue-500/25 transition-all duration-300 hover:scale-[1.02] active:scale-[0.98]"
                        >
                            ¡Reclamar esta dirección! →
                        </button>
                    </div>
                </div>
            @endif

            {{-- ═══════════════════════════════════════════════════════════════ --}}
            {{-- PASO 2: CREDENCIALES DE USUARIO (Enhanced UX) --}}
            {{-- ═══════════════════════════════════════════════════════════════ --}}
            @if($currentStep === 2)
                <div 
                    x-data="{
                        showPassword: false,
                        showConfirmPassword: false,
                        password: @entangle('password').live,
                        
                        get strength() {
                            let s = 0;
                            const pw = this.password || '';
                            if (pw.length > 5) s++;  // Longitud mínima
                            if (pw.length > 8) s++;  // Longitud ideal
                            if (/[0-9]/.test(pw)) s++;  // Números
                            if (/[^A-Za-z0-9]/.test(pw)) s++;  // Símbolos
                            return Math.min(s, 4);
                        },
                        
                        get strengthLabel() {
                            if (!this.password || this.password.length === 0) return '';
                            if (this.strength === 1) return '🔴 Débil - Agrega más caracteres';
                            if (this.strength === 2) return '🟠 Regular - Agrega un número';
                            if (this.strength === 3) return '🟡 Buena - Agrega un símbolo';
                            if (this.strength >= 4) return '🟢 ¡Excelente!';
                            return '';
                        },
                        
                        get strengthColor() {
                            if (this.strength === 1) return 'bg-red-500';
                            if (this.strength === 2) return 'bg-orange-500';
                            if (this.strength === 3) return 'bg-yellow-500';
                            if (this.strength >= 4) return 'bg-emerald-500';
                            return 'bg-gray-200';
                        }
                    }"
                    x-transition:enter="transition ease-out duration-300"
                    x-transition:enter-start="opacity-0 translate-y-4"
                    x-transition:enter-end="opacity-100 translate-y-0"
                >
                    <div class="text-center mb-8">
                        <h1 class="text-2xl md:text-3xl font-bold text-gray-900 mb-2">
                            Crea tu cuenta
                        </h1>
                        <p class="text-gray-600">
                            Para administrar <span class="font-semibold text-blue-600">{{ $slug }}</span>
                        </p>
                    </div>

                    <div class="space-y-5">
                        {{-- Grid Responsivo: 2 columnas en desktop --}}
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            {{-- Nombre --}}
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Tu nombre completo
                                </label>
                                <input
                                    wire:model="name"
                                    type="text"
                                    placeholder="Juan Pérez"
                                    autofocus
                                    tabindex="1"
                                    class="w-full px-4 py-3.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all shadow-sm focus:shadow-md"
                                />
                                @error('name') <p class="mt-1 text-sm text-red-500">{{ $message }}</p> @enderror
                            </div>

                            {{-- WhatsApp con prefijo Honduras --}}
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    WhatsApp <span class="text-gray-400 font-normal">(opcional)</span>
                                </label>
                                <div class="flex">
                                    <span class="inline-flex items-center gap-2 px-4 bg-gray-100 border border-r-0 border-gray-300 rounded-l-xl text-gray-600 text-sm font-medium">
                                        <span class="text-lg">🇭🇳</span> +504
                                    </span>
                                    <input
                                        wire:model="whatsapp"
                                        type="tel"
                                        placeholder="9999-0000"
                                        tabindex="2"
                                        inputmode="numeric"
                                        maxlength="9"
                                        class="flex-1 px-4 py-3.5 border border-gray-300 rounded-r-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all shadow-sm focus:shadow-md"
                                    />
                                </div>
                            </div>
                        </div>

                        {{-- Email (Fila completa) --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Correo electrónico
                            </label>
                            <div class="relative">
                                <input
                                    wire:model.live.debounce.500ms="email"
                                    type="email"
                                    placeholder="tu@email.com"
                                    tabindex="3"
                                    class="w-full px-4 py-3.5 pr-10 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all shadow-sm focus:shadow-md
                                        @error('email') border-red-300 @enderror"
                                />
                                {{-- Spinner de validación --}}
                                <div wire:loading wire:target="email" class="absolute right-3 top-1/2 -translate-y-1/2">
                                    <svg class="animate-spin h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                    </svg>
                                </div>
                            </div>
                            @error('email') 
                                <p class="mt-1 text-sm text-red-500 flex items-center gap-1">
                                    {{ $message }}
                                    @if($message === 'Este correo ya está registrado.')
                                        <a href="/login" class="underline hover:text-red-700">
                                            ¿Iniciar sesión?
                                        </a>
                                    @endif
                                </p>
                            @enderror
                        </div>

                        {{-- Contraseña (Fila completa con Strength Meter) --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Contraseña
                            </label>
                            <div class="relative">
                                <input
                                    x-model="password"
                                    :type="showPassword ? 'text' : 'password'"
                                    placeholder="Mínimo 8 caracteres"
                                    tabindex="4"
                                    class="w-full px-4 py-3.5 pr-12 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all shadow-sm focus:shadow-md"
                                />
                                {{-- Toggle de Visibilidad --}}
                                <button
                                    type="button"
                                    @click="showPassword = !showPassword"
                                    class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition-colors"
                                >
                                    <svg x-show="!showPassword" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                    <svg x-show="showPassword" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display: none;">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                                    </svg>
                                </button>
                            </div>
                            
                            {{-- Password Strength Meter (Gamified) --}}
                            <div class="mt-3" x-show="password?.length > 0" x-transition>
                                <div class="flex gap-1 mb-2">
                                    <div class="h-1.5 flex-1 rounded-full transition-all duration-300"
                                        :class="strength >= 1 ? strengthColor : 'bg-gray-200'"></div>
                                    <div class="h-1.5 flex-1 rounded-full transition-all duration-300"
                                        :class="strength >= 2 ? strengthColor : 'bg-gray-200'"></div>
                                    <div class="h-1.5 flex-1 rounded-full transition-all duration-300"
                                        :class="strength >= 3 ? strengthColor : 'bg-gray-200'"></div>
                                    <div class="h-1.5 flex-1 rounded-full transition-all duration-300"
                                        :class="strength >= 4 ? strengthColor : 'bg-gray-200'"></div>
                                </div>
                                <p class="text-xs transition-all" 
                                   :class="strength >= 4 ? 'text-emerald-600' : strength >= 3 ? 'text-yellow-600' : 'text-gray-500'"
                                   x-text="strengthLabel"></p>
                            </div>
                            @error('password') <p class="mt-1 text-sm text-red-500">{{ $message }}</p> @enderror
                        </div>

                        {{-- Confirmar Contraseña --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Confirmar contraseña
                            </label>
                            <div class="relative">
                                <input
                                    wire:model="password_confirmation"
                                    :type="showConfirmPassword ? 'text' : 'password'"
                                    placeholder="Repite tu contraseña"
                                    tabindex="5"
                                    class="w-full px-4 py-3.5 pr-12 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all shadow-sm focus:shadow-md"
                                />
                                <button
                                    type="button"
                                    @click="showConfirmPassword = !showConfirmPassword"
                                    class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition-colors"
                                >
                                    <svg x-show="!showConfirmPassword" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                    <svg x-show="showConfirmPassword" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display: none;">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                                    </svg>
                                </button>
                            </div>
                        </div>

                        {{-- Navegación --}}
                        <div class="flex gap-4 pt-4">
                            <button
                                wire:click="previousStep"
                                type="button"
                                tabindex="7"
                                class="flex-1 py-3.5 px-6 bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium rounded-xl transition-all"
                            >
                                ← Atrás
                            </button>
                            <button
                                wire:click="nextStep"
                                wire:loading.attr="disabled"
                                wire:loading.class="opacity-50"
                                type="button"
                                tabindex="6"
                                class="flex-1 py-3.5 px-6 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white font-semibold rounded-xl shadow-lg shadow-blue-500/25 transition-all hover:shadow-xl"
                            >
                                <span wire:loading.remove>Continuar →</span>
                                <span wire:loading class="flex items-center justify-center gap-2">
                                    <svg class="animate-spin h-5 w-5" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                    </svg>
                                    Validando...
                                </span>
                            </button>
                        </div>
                    </div>
                </div>
            @endif

            {{-- ═══════════════════════════════════════════════════════════════ --}}
            {{-- PASO 3: PERFIL DEL NEGOCIO (Visual Category Grid) --}}
            {{-- ═══════════════════════════════════════════════════════════════ --}}
            @if($currentStep === 3)
                @php
                    // 🎨 Mapeo de Iconos por Nombre de Categoría
                    $categoryIcons = [
                        // Comida & Bebidas
                        'Restaurantes' => '🍽️',
                        'Comida' => '🍔',
                        'Cafeterías' => '☕',
                        'Bebidas' => '🥤',
                        'Repostería' => '🍰',
                        'Baleadas' => '🫓',
                        
                        // Moda & Belleza
                        'Ropa' => '👗',
                        'Moda' => '👔',
                        'Zapatos' => '👟',
                        'Belleza' => '💄',
                        'Peluquerías' => '💇',
                        'Spa' => '🧖',
                        'Joyería' => '💍',
                        
                        // Tecnología & Electrónica
                        'Tecnología' => '💻',
                        'Electrónica' => '📱',
                        'Celulares' => '📱',
                        'Computadoras' => '🖥️',
                        
                        // Salud & Bienestar
                        'Salud' => '💊',
                        'Farmacias' => '💊',
                        'Médicos' => '🏥',
                        'Gimnasios' => '🏋️',
                        'Deportes' => '⚽',
                        
                        // Servicios
                        'Servicios' => '🛠️',
                        'Profesionales' => '👨‍💼',
                        'Educación' => '📚',
                        'Transporte' => '🚗',
                        'Hogar' => '🏠',
                        'Mascotas' => '🐶',
                        
                        // Comercio
                        'Tiendas' => '🛒',
                        'Supermercados' => '🏪',
                        'Ferreterías' => '🔧',
                        'Muebles' => '🛋️',
                        
                        // Entretenimiento
                        'Entretenimiento' => '🎉',
                        'Eventos' => '🎊',
                        'Arte' => '🎨',
                        'Música' => '🎵',
                        
                        // Otros
                        'Otros' => '✨',
                    ];
                @endphp

                <div 
                    x-transition:enter="transition ease-out duration-300"
                    x-transition:enter-start="opacity-0 translate-y-4"
                    x-transition:enter-end="opacity-100 translate-y-0"
                >
                    <div class="text-center mb-8">
                        <h1 class="text-2xl md:text-3xl font-bold text-gray-900 mb-2">
                            Define tu identidad
                        </h1>
                        <p class="text-gray-600">
                            ¿Qué tipo de negocio es <span class="font-semibold text-blue-600">{{ $slug }}</span>?
                        </p>
                    </div>

                    <div class="space-y-6">
                        {{-- 📍 Ciudad con Selector Moderno --}}
                        <div>
                            <label class="flex items-center gap-2 text-sm font-semibold text-gray-700 mb-2">
                                <span class="text-lg">📍</span> ¿En qué ciudad estás?
                            </label>
                            <div class="relative">
                                <select
                                    wire:model="city_id"
                                    class="w-full appearance-none px-4 py-3.5 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all cursor-pointer pr-10"
                                >
                                    <option value="">Selecciona tu ciudad</option>
                                    @foreach($cities as $city)
                                        <option value="{{ $city->id }}">{{ $city->name }}</option>
                                    @endforeach
                                </select>
                                {{-- Flecha personalizada --}}
                                <div class="absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none">
                                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                    </svg>
                                </div>
                            </div>
                            @error('city_id') <p class="mt-1 text-sm text-red-500">{{ $message }}</p> @enderror
                        </div>

                        {{-- 🏷️ Categoría - Visual Selection Grid --}}
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-3">
                                Elige la categoría que mejor describe tu negocio
                            </label>
                            
                            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                                @foreach($categories as $category)
                                    @php
                                        $icon = $categoryIcons[$category->name] ?? '✨';
                                        $isSelected = $category_id == $category->id;
                                    @endphp
                                    
                                    <button
                                        type="button"
                                        wire:click="$set('category_id', {{ $category->id }})"
                                        class="relative flex flex-col items-center justify-center h-28 p-3 rounded-2xl border-2 transition-all duration-200 active:scale-95 cursor-pointer
                                            {{ $isSelected 
                                                ? 'border-blue-500 bg-blue-50 shadow-md shadow-blue-500/20' 
                                                : 'border-gray-200 bg-white hover:border-gray-300 hover:bg-gray-50' }}"
                                    >
                                        {{-- Check Badge (Solo cuando está seleccionado) --}}
                                        @if($isSelected)
                                            <div class="absolute top-2 right-2 w-5 h-5 bg-blue-500 rounded-full flex items-center justify-center">
                                                <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                                                </svg>
                                            </div>
                                        @endif
                                        
                                        {{-- Emoji Icon --}}
                                        <span class="text-3xl mb-2">{{ $icon }}</span>
                                        
                                        {{-- Category Name --}}
                                        <span class="text-xs font-medium text-center leading-tight
                                            {{ $isSelected ? 'text-blue-700' : 'text-gray-700' }}">
                                            {{ $category->name }}
                                        </span>
                                    </button>
                                @endforeach
                            </div>
                            
                            @error('category_id') 
                                <p class="mt-2 text-sm text-red-500 text-center">{{ $message }}</p> 
                            @enderror
                        </div>

                        {{-- Navegación --}}
                        <div class="flex gap-4 pt-4">
                            <button
                                wire:click="previousStep"
                                type="button"
                                class="py-3.5 px-6 bg-white border border-gray-200 hover:bg-gray-50 text-gray-700 font-medium rounded-xl transition-all shadow-sm"
                            >
                                ← Atrás
                            </button>
                            <button
                                wire:click="nextStep"
                                wire:loading.attr="disabled"
                                wire:loading.class="opacity-50"
                                type="button"
                                class="flex-1 py-3.5 px-6 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white font-semibold rounded-xl shadow-lg shadow-blue-500/25 transition-all hover:shadow-xl"
                            >
                                <span wire:loading.remove>Continuar →</span>
                                <span wire:loading class="flex items-center justify-center gap-2">
                                    <svg class="animate-spin h-5 w-5" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                    </svg>
                                </span>
                            </button>
                        </div>
                    </div>
                </div>
            @endif

            {{-- ═══════════════════════════════════════════════════════════════ --}}
            {{-- PASO 4: SELECCIÓN DE PLAN --}}
            {{-- ═══════════════════════════════════════════════════════════════ --}}
            @if($currentStep === 4)
                <div class="text-center mb-8">
                    <h1 class="text-2xl md:text-3xl font-bold text-gray-900 mb-2">
                        Elige tu plan
                    </h1>
                    <p class="text-gray-600">
                        Puedes cambiar de plan en cualquier momento
                    </p>
                </div>

                <div class="space-y-4">
                    {{-- Plan FREE --}}
                    <label 
                        class="block p-5 border-2 rounded-2xl cursor-pointer transition-all
                            {{ $selectedPlan === 'free' ? 'border-blue-500 bg-blue-50' : 'border-gray-200 hover:border-gray-300' }}"
                    >
                        <input type="radio" wire:model.live="selectedPlan" value="free" class="sr-only" />
                        <div class="flex items-start gap-4">
                            <div class="w-5 h-5 mt-1 rounded-full border-2 flex items-center justify-center
                                {{ $selectedPlan === 'free' ? 'border-blue-500' : 'border-gray-300' }}">
                                @if($selectedPlan === 'free')
                                    <div class="w-3 h-3 rounded-full bg-blue-500"></div>
                                @endif
                            </div>
                            <div class="flex-1">
                                <div class="flex items-center gap-2 mb-1">
                                    <h3 class="font-bold text-lg text-gray-900">Plan Gratuito</h3>
                                    <span class="px-2 py-0.5 bg-gray-100 text-gray-600 text-xs rounded-full">Gratis</span>
                                </div>
                                <p class="text-gray-600 text-sm mb-3">Perfecto para comenzar</p>
                                <ul class="space-y-1.5 text-sm text-gray-600">
                                    <li class="flex items-center gap-2">
                                        <svg class="w-4 h-4 text-blue-500" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                        </svg>
                                        Hasta {{ $freeProductLimit }} productos
                                    </li>
                                    <li class="flex items-center gap-2">
                                        <svg class="w-4 h-4 text-blue-500" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                        </svg>
                                        Perfil público
                                    </li>
                                    <li class="flex items-center gap-2">
                                        <svg class="w-4 h-4 text-blue-500" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                        </svg>
                                        Estadísticas básicas
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </label>

                    {{-- Plan PRO --}}
                    <label 
                        class="block p-5 border-2 rounded-2xl cursor-pointer transition-all relative overflow-hidden
                            {{ $selectedPlan === 'pro' ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200 hover:border-gray-300' }}"
                    >
                        <div class="absolute top-0 right-0 px-3 py-1 bg-gradient-to-r from-indigo-500 to-purple-500 text-white text-xs font-bold rounded-bl-xl">
                            POPULAR
                        </div>
                        <input type="radio" wire:model.live="selectedPlan" value="pro" class="sr-only" />
                        <div class="flex items-start gap-4">
                            <div class="w-5 h-5 mt-1 rounded-full border-2 flex items-center justify-center
                                {{ $selectedPlan === 'pro' ? 'border-indigo-500' : 'border-gray-300' }}">
                                @if($selectedPlan === 'pro')
                                    <div class="w-3 h-3 rounded-full bg-indigo-500"></div>
                                @endif
                            </div>
                            <div class="flex-1">
                                <div class="flex items-center gap-2 mb-1">
                                    <h3 class="font-bold text-lg text-gray-900">Plan PRO</h3>
                                    <span class="px-2 py-0.5 bg-indigo-100 text-indigo-700 text-xs rounded-full font-semibold">L. {{ number_format($proPlanPrice, 0) }}/mes</span>
                                </div>
                                <p class="text-gray-600 text-sm mb-3">Para negocios serios</p>
                                <ul class="space-y-1.5 text-sm text-gray-600">
                                    <li class="flex items-center gap-2">
                                        <svg class="w-4 h-4 text-indigo-500" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                        </svg>
                                        Hasta {{ $proProductLimit }} productos
                                    </li>
                                    <li class="flex items-center gap-2">
                                        <svg class="w-4 h-4 text-indigo-500" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                        </svg>
                                        Badge de verificado ✓
                                    </li>
                                    <li class="flex items-center gap-2">
                                        <svg class="w-4 h-4 text-indigo-500" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                        </svg>
                                        Analítica avanzada
                                    </li>
                                    <li class="flex items-center gap-2">
                                        <svg class="w-4 h-4 text-indigo-500" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                        </svg>
                                        Prioridad en el directorio
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </label>
                </div>

                {{-- Navegación Final --}}
                <div class="flex gap-4 pt-6">
                    <button
                        wire:click="previousStep"
                        type="button"
                        class="py-3.5 px-6 bg-white border border-gray-200 hover:bg-gray-50 text-gray-700 font-medium rounded-xl transition-all shadow-sm"
                    >
                        ← Atrás
                    </button>
                    <button
                        wire:click="register"
                        wire:loading.attr="disabled"
                        wire:loading.class="opacity-50 cursor-wait"
                        type="button"
                        class="flex-1 py-3.5 px-6 bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-600 hover:to-teal-600 text-white font-bold rounded-xl shadow-lg shadow-emerald-500/25 transition-all hover:shadow-xl hover:scale-[1.02] active:scale-[0.98]"
                    >
                        <span wire:loading.remove>
                            @if($selectedPlan === 'pro')
                                ⚡ Activar Plan PRO →
                            @else
                                🚀 Crear mi Micrositio Ahora
                            @endif
                        </span>
                        <span wire:loading class="flex items-center justify-center gap-2">
                            <svg class="animate-spin h-5 w-5" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                            </svg>
                            Creando tu micrositio...
                        </span>
                    </button>
                </div>
            @endif
        </div>

        {{-- Link a Login --}}
        <p class="mt-8 text-gray-600">
            ¿Ya tienes cuenta? 
            <a href="/login" wire:navigate class="text-blue-600 hover:text-blue-700 font-medium">
                Inicia sesión
            </a>
        </p>

        {{-- Disabled Registration Message --}}
        @if(!$registrationsEnabled)
            <div class="mt-4 px-4 py-3 bg-amber-50 border border-amber-200 rounded-xl text-amber-700 text-sm text-center">
                ⚠️ Los registros están temporalmente deshabilitados.
            </div>
        @endif
    </div>
</div>
