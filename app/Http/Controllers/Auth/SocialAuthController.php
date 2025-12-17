<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

/**
 * SocialAuthController - OAuth "Match by Email" Strategy
 *
 * Flujo:
 * 1. Usuario hace clic en "Continuar con Google"
 * 2. Redirigimos a Google OAuth
 * 3. Google redirige de vuelta con datos del usuario
 * 4. Buscamos si el email YA EXISTE en nuestra DB
 *    - SÍ: Vinculamos el google_id al usuario existente (silent merge)
 *    - NO: Creamos nuevo usuario con datos de Google
 * 5. Login automático y redirección
 *
 * Beneficio: Zero fricción para usuarios que ya tienen cuenta
 */
class SocialAuthController
{
    /**
     * Proveedores OAuth permitidos
     */
    protected array $allowedProviders = ['google', 'facebook'];

    /**
     * Redirige al proveedor OAuth
     */
    public function redirect(string $provider)
    {
        // Validar proveedor permitido
        if (! in_array($provider, $this->allowedProviders)) {
            return redirect('/login')->with('error', 'Proveedor de autenticación no válido.');
        }

        return Socialite::driver($provider)
            ->scopes(['email', 'profile']) // Asegurar permisos mínimos
            ->redirect();
    }

    /**
     * Callback del proveedor OAuth
     */
    public function callback(string $provider)
    {
        // Validar proveedor
        if (! in_array($provider, $this->allowedProviders)) {
            return redirect('/login')->with('error', 'Proveedor de autenticación no válido.');
        }

        try {
            // Obtener datos del usuario desde el proveedor
            $socialUser = Socialite::driver($provider)->user();

            // Buscar usuario existente por email (Match by Email)
            $existingUser = User::where('email', $socialUser->getEmail())->first();

            if ($existingUser) {
                // ═══════════════════════════════════════════════════════════
                // ESCENARIO A: Usuario existe → Vincular cuenta
                // ═══════════════════════════════════════════════════════════
                $user = $this->linkSocialAccount($existingUser, $provider, $socialUser);
                $isNewUser = false;
            } else {
                // ═══════════════════════════════════════════════════════════
                // ESCENARIO B: Usuario nuevo → Crear cuenta
                // ═══════════════════════════════════════════════════════════
                $user = $this->createFromSocialUser($provider, $socialUser);
                $isNewUser = true;
            }

            // Login automático con "remember me"
            Auth::login($user, true);

            // Flash message
            session()->flash('toast', $isNewUser
                ? '¡Cuenta creada con '.$this->getProviderName($provider).'! 🎉'
                : '¡Bienvenido de nuevo, '.$user->name.'! 👋'
            );

            // Redirigir a la URL intentada o al home
            return redirect()->intended('/');

        } catch (\Laravel\Socialite\Two\InvalidStateException $e) {
            // Usuario canceló el flujo o hubo problema de estado
            return redirect('/login')->with('error', 'Autenticación cancelada. Intenta de nuevo.');

        } catch (\Exception $e) {
            // Error genérico
            report($e);

            return redirect('/login')->with('error', 'Error al conectar con '.$this->getProviderName($provider).'. Intenta de nuevo.');
        }
    }

    /**
     * Vincula cuenta social a usuario existente
     */
    protected function linkSocialAccount(User $user, string $provider, $socialUser): User
    {
        $providerIdColumn = $provider.'_id';

        // Actualizar el ID del proveedor
        $user->{$providerIdColumn} = $socialUser->getId();

        // Actualizar avatar solo si el usuario no tiene uno
        if (empty($user->avatar) && $socialUser->getAvatar()) {
            $user->avatar = $socialUser->getAvatar();
        }

        // Marcar email como verificado si no lo estaba
        if (empty($user->email_verified_at)) {
            $user->email_verified_at = now();
        }

        $user->save();

        return $user;
    }

    /**
     * Crea usuario desde datos de proveedor social
     */
    protected function createFromSocialUser(string $provider, $socialUser): User
    {
        $providerIdColumn = $provider.'_id';

        $user = User::create([
            'name' => $socialUser->getName() ?? $this->extractNameFromEmail($socialUser->getEmail()),
            'email' => $socialUser->getEmail(),
            'password' => Hash::make(Str::random(32)), // Contraseña aleatoria segura
            'email_verified_at' => now(), // Google/FB ya verificaron el email
            'avatar' => $socialUser->getAvatar(),
            $providerIdColumn => $socialUser->getId(),
        ]);

        // Asignar rol por defecto si existe el método
        if (method_exists($user, 'assignRole')) {
            $user->assignRole('panel_user');
        }

        return $user;
    }

    /**
     * Extrae nombre del email si no viene del proveedor
     */
    protected function extractNameFromEmail(string $email): string
    {
        $localPart = explode('@', $email)[0];
        $parts = preg_split('/[._-]/', $localPart);

        if (count($parts) > 1) {
            return ucfirst($parts[0]).' '.ucfirst($parts[1]);
        }

        return ucfirst($localPart);
    }

    /**
     * Nombre amigable del proveedor
     */
    protected function getProviderName(string $provider): string
    {
        return match ($provider) {
            'google' => 'Google',
            'facebook' => 'Facebook',
            default => ucfirst($provider),
        };
    }
}
