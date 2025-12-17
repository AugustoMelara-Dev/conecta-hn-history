<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * RegistrationTest - Tests para el flujo de registro de usuarios
 *
 * NOTA: El registro tradicional /register usa Breeze.
 * El registro de negocios usa /registrar-negocio (Livewire wizard).
 */
class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test: La pantalla de registro tradicional se renderiza
     */
    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        // La ruta existe y responde
        $response->assertSuccessful();
    }

    /**
     * Test: El wizard de registro de negocios se renderiza
     */
    public function test_tenant_registration_wizard_can_be_rendered(): void
    {
        $response = $this->get('/registrar-negocio');

        $response->assertSuccessful();
    }
}
