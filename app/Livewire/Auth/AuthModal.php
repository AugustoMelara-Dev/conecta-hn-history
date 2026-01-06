<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * AuthModal - Componente de Autenticación B2C "Barrera Invisible"
 *
 * Estrategia: El usuario solo ve este modal cuando intenta realizar
 * una acción de valor (like, pedir, comentar). No existe /login aislado.
 *
 * Características:
 * - 📱 Mobile: Bottom Sheet (slide up)
 * - 🖥️ Desktop: Modal centrado (fade + scale)
 * - 🔄 Toggle instantáneo login/register
 * - 🌐 Google OAuth ready
 * - ⚡ Zero page reload
 */
class AuthModal extends Component
{
    // ═══════════════════════════════════════════════════════════════════════
    // STATE MANAGEMENT
    // ═══════════════════════════════════════════════════════════════════════

    public bool $isOpen = false;

    public string $mode = 'login'; // 'login' | 'register'

    public string $email = '';

    public string $password = '';

    public string $name = ''; // Solo para registro

    public bool $isSubmitting = false;

    // Contexto de redirección (opcional)
    public ?string $redirectTo = null;

    public ?string $actionLabel = null; // "añadir a favoritos", "enviar mensaje"

    // ═══════════════════════════════════════════════════════════════════════
    // LISTENERS
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Escucha el evento global para abrir el modal
     */
    #[On('open-auth-modal')]
    public function openModal(?string $action = null, ?string $redirect = null): void
    {
        // Solo abrir si el usuario NO está autenticado
        if (Auth::check()) {
            return;
        }

        $this->resetForm();
        $this->isOpen = true;
        $this->actionLabel = $action;
        $this->redirectTo = $redirect;
    }

    /**
     * Cierra el modal
     */
    public function closeModal(): void
    {
        $this->isOpen = false;
        $this->resetForm();
    }

    /**
     * Resetea el formulario
     */
    protected function resetForm(): void
    {
        $this->email = '';
        $this->password = '';
        $this->name = '';
        $this->resetValidation();
        $this->isSubmitting = false;
    }

    // ═══════════════════════════════════════════════════════════════════════
    // MODE SWITCHING
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Cambiar a modo login
     */
    public function switchToLogin(): void
    {
        $this->mode = 'login';
        $this->resetValidation();
    }

    /**
     * Cambiar a modo registro
     */
    public function switchToRegister(): void
    {
        $this->mode = 'register';
        $this->resetValidation();
    }

    // ═══════════════════════════════════════════════════════════════════════
    // AUTHENTICATION
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Procesa el login
     */
    public function login(): void
    {
        $this->validate([
            'email' => 'required|email',
            'password' => 'required|min:6',
        ], [
            'email.required' => 'Ingresa tu correo',
            'email.email' => 'Correo inválido',
            'password.required' => 'Ingresa tu contraseña',
            'password.min' => 'Mínimo 6 caracteres',
        ]);

        $this->isSubmitting = true;

        if (Auth::attempt(['email' => $this->email, 'password' => $this->password], true)) {
            $this->handleSuccess();
        } else {
            $this->isSubmitting = false;
            $this->addError('email', 'Credenciales incorrectas');
        }
    }

    /**
     * Procesa el registro (Perfil Progresivo - solo email y password)
     */
    public function register(): void
    {
        $this->validate([
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:8',
        ], [
            'email.required' => 'Ingresa tu correo',
            'email.email' => 'Correo inválido',
            'email.unique' => 'Este correo ya está registrado',
            'password.required' => 'Crea una contraseña',
            'password.min' => 'Mínimo 8 caracteres',
        ]);

        $this->isSubmitting = true;

        try {
            // Crear usuario con nombre derivado del email (Perfil Progresivo)
            $user = User::create([
                'name' => $this->extractNameFromEmail($this->email),
                'email' => $this->email,
                'password' => Hash::make($this->password),
                'email_verified_at' => now(), // Skip email verification for B2C
            ]);

            Auth::login($user, true);
            $this->handleSuccess(true);

        } catch (\Exception $e) {
            $this->isSubmitting = false;
            $this->addError('email', 'Error al crear cuenta. Intenta de nuevo.');
            report($e);
        }
    }

    /**
     * Maneja el éxito de autenticación
     */
    protected function handleSuccess(bool $isNewUser = false): void
    {
        $this->isOpen = false;

        // Emitir evento para que otros componentes se actualicen
        $this->dispatch('auth-success', [
            'isNewUser' => $isNewUser,
            'userName' => Auth::user()->name,
        ]);

        // Flash message
        session()->flash('toast', $isNewUser
            ? '¡Cuenta creada! Bienvenido 🎉'
            : '¡Hola de nuevo, '.Auth::user()->name.'! 👋'
        );

        // Redirect si se especificó
        if ($this->redirectTo) {
            $this->redirect($this->redirectTo, navigate: true);
        }
    }

    /**
     * Extrae un nombre tentativo del email
     */
    protected function extractNameFromEmail(string $email): string
    {
        $localPart = explode('@', $email)[0];

        // Intentar separar por puntos o guiones
        $parts = preg_split('/[._-]/', $localPart);

        if (count($parts) > 1) {
            return ucfirst($parts[0]).' '.ucfirst($parts[1]);
        }

        return ucfirst($localPart);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // OAUTH (Placeholder for Google)
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Redirige a Google OAuth
     */
    public function loginWithGoogle()
    {
        return redirect()->route('social.redirect', 'google');
    }

    /**
     * Redirige a Facebook OAuth
     */
    public function loginWithFacebook()
    {
        return redirect()->route('social.redirect', 'facebook');
    }

    // ═══════════════════════════════════════════════════════════════════════
    // RENDER
    // ═══════════════════════════════════════════════════════════════════════

    public function render()
    {
        return view('livewire.auth.auth-modal');
    }
}
