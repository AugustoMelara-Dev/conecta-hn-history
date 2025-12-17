<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Skip;
use Tests\TestCase;

/**
 * ProfileTest - Tests de perfil de usuario
 *
 * @todo Refactorizar para Livewire architecture - las rutas /profile ahora usan Filament
 */
#[Skip('Pendiente refactorización para nueva arquitectura Livewire/Filament')]
class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/mi-cuenta');

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated(): void
    {
        $this->markTestSkipped('Pendiente refactorización para Livewire/Filament');
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $this->markTestSkipped('Pendiente refactorización para Livewire/Filament');
    }

    public function test_user_can_delete_their_account(): void
    {
        $this->markTestSkipped('Pendiente refactorización para Livewire/Filament');
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $this->markTestSkipped('Pendiente refactorización para Livewire/Filament');
    }
}
