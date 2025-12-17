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
            {{-- PASO 2: CREDENCIALES DE USUARIO --}}
            {{-- ═══════════════════════════════════════════════════════════════ --}}
            @if($currentStep === 2)
                <div class="text-center mb-8">
                    <h1 class="text-2xl md:text-3xl font-bold text-gray-900 mb-2">
                        Crea tu cuenta
                    </h1>
                    <p class="text-gray-600">
                        Para administrar <span class="font-semibold text-blue-600">{{ $slug }}</span>
                    </p>
                </div>

                <div class="space-y-5">
                    {{-- Nombre --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Tu nombre completo</label>
                        <input
                            wire:model="name"
                            type="text"
                            placeholder="Juan Pérez"
                            class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                        />
                        @error('name') <p class="mt-1 text-sm text-red-500">{{ $message }}</p> @enderror
                    </div>

                    {{-- Email --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Correo electrónico</label>
                        <input
                            wire:model.live.debounce.500ms="email"
                            type="email"
                            placeholder="tu@email.com"
                            class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                        />
                        @error('email') <p class="mt-1 text-sm text-red-500">{{ $message }}</p> @enderror
                    </div>

                    {{-- Password --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Contraseña</label>
                        <input
                            wire:model="password"
                            type="password"
                            placeholder="Mínimo 8 caracteres"
                            class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                        />
                        @error('password') <p class="mt-1 text-sm text-red-500">{{ $message }}</p> @enderror
                    </div>

                    {{-- Confirm Password --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Confirmar contraseña</label>
                        <input
                            wire:model="password_confirmation"
                            type="password"
                            placeholder="Repite tu contraseña"
                            class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                        />
                    </div>

                    {{-- Navegación --}}
                    <div class="flex gap-4 pt-4">
                        <button
                            wire:click="previousStep"
                            type="button"
                            class="flex-1 py-3 px-6 bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium rounded-xl transition-all"
                        >
                            ← Atrás
                        </button>
                        <button
                            wire:click="nextStep"
                            type="button"
                            class="flex-1 py-3 px-6 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white font-semibold rounded-xl shadow-lg shadow-blue-500/25 transition-all"
                        >
                            Continuar →
                        </button>
                    </div>
                </div>
            @endif

            {{-- ═══════════════════════════════════════════════════════════════ --}}
            {{-- PASO 3: PERFIL DEL NEGOCIO --}}
            {{-- ═══════════════════════════════════════════════════════════════ --}}
            @if($currentStep === 3)
                <div class="text-center mb-8">
                    <h1 class="text-2xl md:text-3xl font-bold text-gray-900 mb-2">
                        Cuéntanos de tu negocio
                    </h1>
                    <p class="text-gray-600">
                        Ayuda a tus clientes a encontrarte
                    </p>
                </div>

                <div class="space-y-5">
                    {{-- Ciudad --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">¿En qué ciudad estás?</label>
                        <select
                            wire:model="city_id"
                            class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                        >
                            <option value="">Selecciona una ciudad</option>
                            @foreach($cities as $city)
                                <option value="{{ $city->id }}">{{ $city->name }}</option>
                            @endforeach
                        </select>
                        @error('city_id') <p class="mt-1 text-sm text-red-500">{{ $message }}</p> @enderror
                    </div>

                    {{-- Categoría --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Categoría de tu negocio</label>
                        <select
                            wire:model="category_id"
                            class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                        >
                            <option value="">Selecciona una categoría</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                            @endforeach
                        </select>
                        @error('category_id') <p class="mt-1 text-sm text-red-500">{{ $message }}</p> @enderror
                    </div>

                    {{-- WhatsApp --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">WhatsApp (opcional)</label>
                        <div class="flex">
                            <span class="inline-flex items-center px-4 bg-gray-100 border border-r-0 border-gray-300 rounded-l-xl text-gray-500">
                                +504
                            </span>
                            <input
                                wire:model="whatsapp"
                                type="tel"
                                placeholder="9999-0000"
                                class="flex-1 px-4 py-3 border border-gray-300 rounded-r-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                            />
                        </div>
                    </div>

                    {{-- Navegación --}}
                    <div class="flex gap-4 pt-4">
                        <button
                            wire:click="previousStep"
                            type="button"
                            class="flex-1 py-3 px-6 bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium rounded-xl transition-all"
                        >
                            ← Atrás
                        </button>
                        <button
                            wire:click="nextStep"
                            type="button"
                            class="flex-1 py-3 px-6 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white font-semibold rounded-xl shadow-lg shadow-blue-500/25 transition-all"
                        >
                            Continuar →
                        </button>
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
                        <input type="radio" wire:model="selectedPlan" value="free" class="sr-only" />
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
                        <input type="radio" wire:model="selectedPlan" value="pro" class="sr-only" />
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
                        class="flex-1 py-3 px-6 bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium rounded-xl transition-all"
                    >
                        ← Atrás
                    </button>
                    <button
                        wire:click="register"
                        wire:loading.attr="disabled"
                        wire:loading.class="opacity-50 cursor-wait"
                        type="button"
                        class="flex-1 py-3 px-6 bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-600 hover:to-teal-600 text-white font-semibold rounded-xl shadow-lg shadow-emerald-500/25 transition-all"
                    >
                        <span wire:loading.remove>
                            @if($selectedPlan === 'pro')
                                Continuar al pago →
                            @else
                                Crear mi negocio →
                            @endif
                        </span>
                        <span wire:loading class="flex items-center justify-center gap-2">
                            <svg class="animate-spin h-5 w-5" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                            </svg>
                            Creando...
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
