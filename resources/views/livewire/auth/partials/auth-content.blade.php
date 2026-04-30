{{-- 
    Contenido compartido del AuthModal
    (Reutilizado en Mobile y Desktop)
--}}

{{-- Contexto de acción (si existe) --}}
@if($actionLabel)
    <div class="mb-4 px-4 py-3 bg-blue-50 border border-blue-100 rounded-xl">
        <p class="text-sm text-blue-700 text-center">
            Inicia sesión para <span class="font-semibold">{{ $actionLabel }}</span>
        </p>
    </div>
@endif

{{-- Título (solo en mobile, desktop lo tiene en header) --}}
<h2 class="md:hidden text-xl font-bold text-gray-900 text-center mb-6">
    {{ $mode === 'login' ? 'Bienvenido de nuevo' : 'Crear cuenta' }}
</h2>

{{-- ═══════════════════════════════════════════════════════════════════ --}}
{{-- BOTÓN DE GOOGLE (Hero) --}}
{{-- ═══════════════════════════════════════════════════════════════════ --}}
<button
    wire:click="loginWithGoogle"
    type="button"
    class="w-full flex items-center justify-center gap-3 px-4 py-3.5 bg-white border border-gray-300 rounded-xl shadow-sm hover:shadow-md hover:bg-gray-50 transition-shadow duration-200"
>
    {{-- Google Logo SVG --}}
    <svg class="w-5 h-5" viewBox="0 0 24 24">
        <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
        <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
        <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
        <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
    </svg>
    <span class="text-gray-700 font-medium">Continuar con Google</span>
</button>

{{-- ═══════════════════════════════════════════════════════════════════ --}}
{{-- SEPARADOR --}}
{{-- ═══════════════════════════════════════════════════════════════════ --}}
<div class="relative my-6">
    <div class="absolute inset-0 flex items-center">
        <div class="w-full border-t border-gray-200"></div>
    </div>
    <div class="relative flex justify-center">
        <span class="px-4 bg-white text-sm text-gray-500">o usa tu correo</span>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════════════════ --}}
{{-- FORMULARIO MINIMALISTA --}}
{{-- ═══════════════════════════════════════════════════════════════════ --}}
<form wire:submit="{{ $mode === 'login' ? 'login' : 'register' }}" class="space-y-4">
    {{-- Email --}}
    <div>
        <input
            wire:model="email"
            type="email"
            placeholder="tu@email.com"
            autocomplete="email"
            class="w-full px-4 py-3.5 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors placeholder-gray-400
                @error('email') border-red-300 bg-red-50 @enderror"
        />
        @error('email')
            <p class="mt-1.5 text-sm text-red-500 flex items-center gap-1">
                {{ $message }}
                @if(str_contains($message, 'registrado'))
                    <button type="button" wire:click="switchToLogin" class="underline hover:text-red-700 font-medium">
                        Ingresar
                    </button>
                @endif
            </p>
        @enderror
    </div>

    {{-- Password --}}
    <div x-data="{ show: false }" class="relative">
        <input
            wire:model="password"
            :type="show ? 'text' : 'password'"
            placeholder="{{ $mode === 'login' ? 'Contraseña' : 'Crea una contraseña (8+ caracteres)' }}"
            autocomplete="{{ $mode === 'login' ? 'current-password' : 'new-password' }}"
            class="w-full px-4 py-3.5 pr-12 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors placeholder-gray-400
                @error('password') border-red-300 bg-red-50 @enderror"
        />
        <button
            type="button"
            @click="show = !show"
            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition-colors"
        >
            <svg x-show="!show" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
            </svg>
            <svg x-show="show" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display: none;">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
            </svg>
        </button>
        @error('password')
            <p class="mt-1.5 text-sm text-red-500">{{ $message }}</p>
        @enderror
    </div>

    {{-- Forgot Password (solo en login) --}}
    @if($mode === 'login')
        <div class="text-right">
            <a href="/forgot-password" class="text-sm text-blue-600 hover:text-blue-700 hover:underline">
                ¿Olvidaste tu contraseña?
            </a>
        </div>
    @endif

    {{-- Submit Button --}}
    <button
        type="submit"
        wire:loading.attr="disabled"
        wire:loading.class="opacity-50 cursor-wait"
        class="w-full py-3.5 px-6 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white font-semibold rounded-xl shadow-lg shadow-blue-500/25 transition-shadow hover:shadow-xl active:scale-[0.98]"
    >
        <span wire:loading.remove>
            {{ $mode === 'login' ? 'Ingresar' : 'Crear cuenta' }}
        </span>
        <span wire:loading class="flex items-center justify-center gap-2">
            <svg class="animate-spin h-5 w-5" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
            </svg>
        </span>
    </button>
</form>

{{-- ═══════════════════════════════════════════════════════════════════ --}}
{{-- TOGGLE MODE --}}
{{-- ═══════════════════════════════════════════════════════════════════ --}}
<p class="mt-6 text-center text-sm text-gray-600">
    @if($mode === 'login')
        ¿No tienes cuenta?
        <button 
            type="button"
            wire:click="switchToRegister"
            class="text-blue-600 hover:text-blue-700 font-semibold hover:underline"
        >
            Regístrate
        </button>
    @else
        ¿Ya tienes cuenta?
        <button 
            type="button"
            wire:click="switchToLogin"
            class="text-blue-600 hover:text-blue-700 font-semibold hover:underline"
        >
            Ingresa
        </button>
    @endif
</p>

{{-- Términos (solo en registro) --}}
@if($mode === 'register')
    <p class="mt-4 text-center text-xs text-gray-400">
        Al crear una cuenta, aceptas nuestros
        <a href="/terms" class="underline hover:text-gray-600">Términos</a>
        y
        <a href="/privacy" class="underline hover:text-gray-600">Privacidad</a>
    </p>
@endif
