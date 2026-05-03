<?php

namespace Tests\Feature\Auth;

use App\Domain\Identity\Enums\UserRole;
use App\Models\Tenant;
use App\Models\TenantOnboardingState;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_email_verification_screen_can_be_rendered(): void
    {
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)->get('/verify-email');

        $response->assertStatus(200);
    }

    public function test_email_verification_screen_exposes_next_onboarding_action_for_new_owner(): void
    {
        $user = User::factory()->unverified()->create([
            'role' => UserRole::TENANT_OWNER,
        ]);
        $tenant = Tenant::factory()->create([
            'user_id' => $user->id,
            'slug' => 'tienda-onboarding-email',
        ]);
        $user->forceFill(['tenant_id' => $tenant->id])->save();

        TenantOnboardingState::query()->create([
            'tenant_id' => (string) $tenant->id,
            'user_id' => (string) $user->id,
            'role' => 'seller',
            'progress' => 20,
            'next_action_key' => 'owner_catalog',
            'next_action_title' => 'Publica tu primera oferta',
            'next_action_copy' => 'Agrega un producto o servicio para que el perfil no quede vacio.',
            'next_action_href' => '/app/tienda-onboarding-email/products',
            'milestones' => [],
        ]);

        $this->actingAs($user)
            ->get('/verify-email')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Auth/VerifyEmail')
                ->where('onboardingNextAction.progress', 20)
                ->where('onboardingNextAction.title', 'Publica tu primera oferta')
                ->where('onboardingNextAction.href', '/app/tienda-onboarding-email/products')
            );
    }

    public function test_email_can_be_verified(): void
    {
        $user = User::factory()->unverified()->create();

        Event::fake();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $response = $this->actingAs($user)->get($verificationUrl);

        Event::assertDispatched(Verified::class);
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $response->assertRedirect(route('directory.index', absolute: false).'?verified=1');
    }

    public function test_tenant_owners_are_sent_to_the_filament_app_panel_after_email_verification(): void
    {
        $user = User::factory()->unverified()->create([
            'role' => UserRole::TENANT_OWNER,
        ]);
        $tenant = Tenant::factory()->create([
            'user_id' => $user->id,
            'slug' => 'tienda-verificada',
        ]);
        $user->forceFill(['tenant_id' => $tenant->id])->save();

        Event::fake();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $response = $this->actingAs($user)->get($verificationUrl);

        Event::assertDispatched(Verified::class);
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $response->assertRedirect(route('filament.app.pages.dashboard', ['tenant' => 'tienda-verificada'], false).'?verified=1');
    }

    public function test_email_is_not_verified_with_invalid_hash(): void
    {
        $user = User::factory()->unverified()->create();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1('wrong-email')]
        );

        $this->actingAs($user)->get($verificationUrl);

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }
}
