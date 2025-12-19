<?php

namespace App\Livewire\Tenant\Settings;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * Security Settings - Password Management
 *
 * Secure password change with current password verification
 */
#[Layout('layouts.app')]
class Security extends Component
{
    #[Validate('required|current_password')]
    public string $current_password = '';

    #[Validate('required|min:8|confirmed')]
    public string $password = '';

    public string $password_confirmation = '';

    // ═══════════════════════════════════════════════════════════════════════
    // ACTIONS
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Update user password
     */
    public function save(): void
    {
        $this->validate();

        // Ensure new password is different
        if (Hash::check($this->password, Auth::user()->password)) {
            throw ValidationException::withMessages([
                'password' => 'La nueva contraseña debe ser diferente a la actual.',
            ]);
        }

        // Update password
        Auth::user()->update([
            'password' => Hash::make($this->password),
        ]);

        // Clear sensitive fields
        $this->reset(['current_password', 'password', 'password_confirmation']);

        session()->flash('toast', '✅ Contraseña actualizada correctamente');
    }

    // ═══════════════════════════════════════════════════════════════════════
    // RENDER
    // ═══════════════════════════════════════════════════════════════════════

    public function render()
    {
        return view('livewire.tenant.settings.security')
            ->title('Seguridad | Conecta HN');
    }
}

