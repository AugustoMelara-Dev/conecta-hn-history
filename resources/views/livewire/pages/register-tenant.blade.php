{{--
    REGISTER-TENANT - Wizard de Registro Multi-Paso
    
    3 Pasos:
    1. Credenciales (Nombre, Email, Password)
    2. Perfil del Negocio (Nombre, Slug, Ciudad, Categoría)
    3. Selección de Plan (Free vs PRO)
--}}

<div class="min-h-screen bg-gradient-to-br from-blue-50 via-white to-indigo-50 py-12 px-4">
    <div class="max-w-2xl mx-auto">
        {{-- Logo y Título --}}
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-gray-900">
                Conecta<span class="text-blue-600">HN</span>
            </h1>
            <p class="text-gray-500 mt-2">Registra tu negocio en minutos</p>
        </div>

        {{-- Gatekeeper: Lista de Espera --}}
        @if(!$registrationsEnabled)
        <div class="bg-white rounded-3xl shadow-xl p-8 text-center">
            <div class="w-20 h-20 mx-auto bg-amber-100 rounded-full flex items-center justify-center mb-6">
                <svg class="w-10 h-10 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
            <h2 class="text-2xl font-bold text-gray-900 mb-4">Lista de Espera</h2>
            <p class="text-gray-600 mb-6">
                Estamos trabajando duro para crecer. Los registros están temporalmente pausados.
                Déjanos tu correo y te avisaremos cuando haya espacio.
            </p>
            <a href="/" class="inline-flex items-center gap-2 px-6 py-3 bg-blue-600 text-white rounded-full font-medium hover:bg-blue-700 transition-colors">
                Volver al Inicio
            </a>
        </div>
        @else

        {{-- Progress Bar --}}
        <div class="mb-8">
            <div class="flex items-center justify-between relative">
                {{-- Línea de fondo --}}
                <div class="absolute top-1/2 left-0 right-0 h-1 bg-gray-200 -translate-y-1/2 z-0"></div>
                
                {{-- Línea de progreso --}}
                <div 
                    class="absolute top-1/2 left-0 h-1 bg-blue-600 -translate-y-1/2 z-10 transition-all duration-500"
                    style="width: {{ ($currentStep - 1) / ($totalSteps - 1) * 100 }}%"
                ></div>
                
                {{-- Steps --}}
                @foreach([1 => 'Cuenta', 2 => 'Negocio', 3 => 'Plan'] as $step => $label)
                <button 
                    wire:click="goToStep({{ $step }})"
                    @class([
                        'relative z-20 flex flex-col items-center gap-2 transition-all',
                        'cursor-pointer' => $step <= $currentStep,
                        'cursor-default' => $step > $currentStep,
                    ])
                >
                    <div @class([
                        'w-10 h-10 rounded-full flex items-center justify-center font-bold text-sm transition-all duration-300',
                        'bg-blue-600 text-white shadow-lg' => $step <= $currentStep,
                        'bg-gray-200 text-gray-400' => $step > $currentStep,
                    ])>
                        @if($step < $currentStep)
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        @else
                        {{ $step }}
                        @endif
                    </div>
                    <span @class([
                        'text-xs font-medium transition-colors',
                        'text-blue-600' => $step <= $currentStep,
                        'text-gray-400' => $step > $currentStep,
                    ])>{{ $label }}</span>
                </button>
                @endforeach
            </div>
        </div>

        {{-- Card Principal --}}
        <div class="bg-white rounded-3xl shadow-xl overflow-hidden">
            {{-- Flash Messages --}}
            @if(session('error'))
            <div class="bg-red-50 border-l-4 border-red-500 p-4">
                <p class="text-red-700">{{ session('error') }}</p>
            </div>
            @endif

            <form wire:submit="register" class="p-8">
                {{-- ═══════════════════════════════════════════════════════════ --}}
                {{-- PASO 1: CREDENCIALES --}}
                {{-- ═══════════════════════════════════════════════════════════ --}}
                <div x-show="{{ $currentStep }} === 1" x-transition>
                    <h2 class="text-2xl font-bold text-gray-900 mb-2">Crea tu cuenta</h2>
                    <p class="text-gray-500 mb-6">Estos serán tus datos de acceso</p>

                    <div class="space-y-5">
                        {{-- Nombre --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Tu nombre completo</label>
                            <input 
                                type="text"
                                wire:model="name"
                                placeholder="Juan Pérez"
                                class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-shadow @error('name') border-red-500 @enderror"
                            >
                            @error('name')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
                        </div>

                        {{-- Email --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Correo electrónico</label>
                            <input 
                                type="email"
                                wire:model.blur="email"
                                placeholder="tu@email.com"
                                class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-shadow @error('email') border-red-500 @enderror"
                            >
                            @error('email')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
                        </div>

                        {{-- Password --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Contraseña</label>
                            <input 
                                type="password"
                                wire:model="password"
                                placeholder="Mínimo 8 caracteres"
                                class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-shadow @error('password') border-red-500 @enderror"
                            >
                            @error('password')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
                        </div>

                        {{-- Confirmar Password --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Confirmar contraseña</label>
                            <input 
                                type="password"
                                wire:model="password_confirmation"
                                placeholder="Repite tu contraseña"
                                class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-shadow"
                            >
                        </div>
                    </div>
                </div>

                {{-- ═══════════════════════════════════════════════════════════ --}}
                {{-- PASO 2: PERFIL DEL NEGOCIO --}}
                {{-- ═══════════════════════════════════════════════════════════ --}}
                <div x-show="{{ $currentStep }} === 2" x-transition>
                    <h2 class="text-2xl font-bold text-gray-900 mb-2">Información del negocio</h2>
                    <p class="text-gray-500 mb-6">Cuéntanos sobre tu empresa</p>

                    <div class="space-y-5">
                        {{-- Nombre del Negocio --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Nombre del negocio</label>
                            <input 
                                type="text"
                                wire:model.live="business_name"
                                placeholder="Baleadas Doña María"
                                class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-shadow @error('business_name') border-red-500 @enderror"
                            >
                            @error('business_name')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
                        </div>

                        {{-- URL Personalizada --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Tu URL en Conecta HN</label>
                            <div class="flex items-center gap-2">
                                <span class="text-gray-400 text-sm">conectahn.com/</span>
                                <input 
                                    type="text"
                                    wire:model.blur="slug"
                                    placeholder="mi-negocio"
                                    class="flex-1 px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-shadow @error('slug') border-red-500 @enderror"
                                >
                            </div>
                            @error('slug')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
                        </div>

                        {{-- Ciudad --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Ciudad</label>
                            <select 
                                wire:model="city_id"
                                class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-shadow @error('city_id') border-red-500 @enderror"
                            >
                                <option value="">Selecciona una ciudad</option>
                                @foreach($cities as $city)
                                <option value="{{ $city->id }}">{{ $city->name }}</option>
                                @endforeach
                            </select>
                            @error('city_id')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
                        </div>

                        {{-- Categoría --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Categoría</label>
                            <select 
                                wire:model="category_id"
                                class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-shadow @error('category_id') border-red-500 @enderror"
                            >
                                <option value="">Selecciona una categoría</option>
                                @foreach($categories as $category)
                                <option value="{{ $category->id }}">{{ $category->icon ?? '' }} {{ $category->name }}</option>
                                @endforeach
                            </select>
                            @error('category_id')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
                        </div>

                        {{-- WhatsApp --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">WhatsApp (opcional)</label>
                            <input 
                                type="tel"
                                wire:model="whatsapp"
                                placeholder="+504 9999-9999"
                                class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-shadow"
                            >
                            <p class="text-gray-400 text-xs mt-1">Los clientes podrán contactarte por aquí</p>
                        </div>
                    </div>
                </div>

                {{-- ═══════════════════════════════════════════════════════════ --}}
                {{-- PASO 3: SELECCIÓN DE PLAN --}}
                {{-- ═══════════════════════════════════════════════════════════ --}}
                <div x-show="{{ $currentStep }} === 3" x-transition>
                    <h2 class="text-2xl font-bold text-gray-900 mb-2">Elige tu plan</h2>
                    <p class="text-gray-500 mb-6">Puedes cambiar de plan en cualquier momento</p>

                    <div class="grid md:grid-cols-2 gap-4">
                        {{-- Plan Gratuito --}}
                        <label 
                            @class([
                                'relative flex flex-col p-6 rounded-2xl border-2 cursor-pointer transition-all',
                                'border-blue-600 bg-blue-50 ring-2 ring-blue-600 ring-offset-2' => $selectedPlan === 'free',
                                'border-gray-200 hover:border-gray-300' => $selectedPlan !== 'free',
                            ])
                        >
                            <input 
                                type="radio" 
                                wire:model="selectedPlan" 
                                value="free" 
                                class="sr-only"
                            >
                            
                            <div class="flex items-center justify-between mb-4">
                                <span class="text-lg font-bold text-gray-900">Gratuito</span>
                                <div @class([
                                    'w-6 h-6 rounded-full border-2 flex items-center justify-center',
                                    'border-blue-600 bg-blue-600' => $selectedPlan === 'free',
                                    'border-gray-300' => $selectedPlan !== 'free',
                                ])>
                                    @if($selectedPlan === 'free')
                                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    @endif
                                </div>
                            </div>

                            <div class="text-3xl font-bold text-gray-900 mb-4">
                                L. 0
                                <span class="text-sm font-normal text-gray-500">/mes</span>
                            </div>

                            <ul class="space-y-3 text-sm text-gray-600">
                                <li class="flex items-center gap-2">
                                    <svg class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    Hasta {{ $freeProductLimit }} productos
                                </li>
                                <li class="flex items-center gap-2">
                                    <svg class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    Perfil básico
                                </li>
                                <li class="flex items-center gap-2">
                                    <svg class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    Enlace de WhatsApp
                                </li>
                                <li class="flex items-center gap-2 text-gray-400">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                    Sin estadísticas
                                </li>
                            </ul>
                        </label>

                        {{-- Plan PRO --}}
                        <label 
                            @class([
                                'relative flex flex-col p-6 rounded-2xl border-2 cursor-pointer transition-all',
                                'border-blue-600 bg-blue-50 ring-2 ring-blue-600 ring-offset-2' => $selectedPlan === 'pro',
                                'border-gray-200 hover:border-gray-300' => $selectedPlan !== 'pro',
                            ])
                        >
                            <input 
                                type="radio" 
                                wire:model="selectedPlan" 
                                value="pro" 
                                class="sr-only"
                            >
                            
                            {{-- Badge Recomendado --}}
                            <span class="absolute -top-3 left-1/2 -translate-x-1/2 px-3 py-1 bg-gradient-to-r from-amber-400 to-amber-500 text-black text-xs font-bold rounded-full">
                                ⭐ RECOMENDADO
                            </span>
                            
                            <div class="flex items-center justify-between mb-4 mt-2">
                                <span class="text-lg font-bold text-gray-900">PRO</span>
                                <div @class([
                                    'w-6 h-6 rounded-full border-2 flex items-center justify-center',
                                    'border-blue-600 bg-blue-600' => $selectedPlan === 'pro',
                                    'border-gray-300' => $selectedPlan !== 'pro',
                                ])>
                                    @if($selectedPlan === 'pro')
                                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    @endif
                                </div>
                            </div>

                            <div class="text-3xl font-bold text-gray-900 mb-4">
                                L. {{ number_format($proPlanPrice, 0) }}
                                <span class="text-sm font-normal text-gray-500">/mes</span>
                            </div>

                            <ul class="space-y-3 text-sm text-gray-600">
                                <li class="flex items-center gap-2">
                                    <svg class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    Productos <strong>ilimitados</strong>
                                </li>
                                <li class="flex items-center gap-2">
                                    <svg class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    Micrositio completo
                                </li>
                                <li class="flex items-center gap-2">
                                    <svg class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    Estadísticas avanzadas
                                </li>
                                <li class="flex items-center gap-2">
                                    <svg class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    Prioridad en búsquedas
                                </li>
                                <li class="flex items-center gap-2">
                                    <svg class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    Badge verificado ⭐
                                </li>
                            </ul>
                        </label>
                    </div>
                </div>

                {{-- ═══════════════════════════════════════════════════════════ --}}
                {{-- NAVEGACIÓN --}}
                {{-- ═══════════════════════════════════════════════════════════ --}}
                <div class="flex items-center justify-between mt-8 pt-6 border-t border-gray-100">
                    @if($currentStep > 1)
                    <button 
                        type="button"
                        wire:click="previousStep"
                        class="flex items-center gap-2 px-5 py-3 text-gray-600 hover:text-gray-900 font-medium transition-colors"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                        </svg>
                        Atrás
                    </button>
                    @else
                    <a href="/" class="flex items-center gap-2 px-5 py-3 text-gray-600 hover:text-gray-900 font-medium transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                        </svg>
                        Cancelar
                    </a>
                    @endif

                    @if($currentStep < $totalSteps)
                    <button 
                        type="button"
                        wire:click="nextStep"
                        class="flex items-center gap-2 px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-xl transition-colors"
                    >
                        Continuar
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </button>
                    @else
                    <button 
                        type="submit"
                        wire:loading.attr="disabled"
                        class="flex items-center gap-2 px-8 py-4 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white font-bold rounded-xl transition-all shadow-lg hover:shadow-xl disabled:opacity-50"
                    >
                        <span wire:loading.remove>
                            @if($selectedPlan === 'pro')
                            Registrar y Pagar
                            @else
                            Crear mi negocio
                            @endif
                        </span>
                        <span wire:loading class="flex items-center gap-2">
                            <svg class="animate-spin h-5 w-5" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            Procesando...
                        </span>
                    </button>
                    @endif
                </div>

                {{-- Ya tienes cuenta? --}}
                <p class="text-center text-gray-500 text-sm mt-6">
                    ¿Ya tienes cuenta? 
                    <a href="/login" class="text-blue-600 hover:underline font-medium">Inicia sesión</a>
                </p>
            </form>
        </div>
        @endif
    </div>
</div>
