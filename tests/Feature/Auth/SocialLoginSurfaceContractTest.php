<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Application\Auth\Contracts\AuthTokenServiceInterface;
use App\Application\Auth\Enums\AuthStatus;
use App\Application\Auth\Handlers\LoginViaSocialProviderHandlerInterface;
use App\Application\Auth\Results\SocialLoginResult;
use App\Domain\Identity\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Auth\AuthRedirector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\Factory as SocialiteFactory;
use Mockery;
use Tests\TestCase;

final class SocialLoginSurfaceContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_social_login_returns_surface_contract_for_customer(): void
    {
        $tenant = Tenant::factory()->create();
        $customer = User::factory()->create([
            'role' => UserRole::CUSTOMER,
            'tenant_id' => null,
            'email_verified_at' => now(),
        ]);
        config(['app.default_tenant_id' => (string) $tenant->getKey()]);

        $this->bindSocialLoginFakes($customer, $tenant, AuthStatus::SUCCESS);

        $response = $this->postJson('/api/v1/auth/social', [
            'provider' => 'google',
            'token' => 'social-token-123',
        ]);

        $context = AuthRedirector::contextFor($customer);

        $response->assertOk()
            ->assertJsonPath('home_surface', $context['home_surface'])
            ->assertJsonPath('audience', $context['audience'])
            ->assertJsonPath('redirect_to', $context['redirect_to']);
    }

    public function test_social_login_returns_surface_contract_for_business_owner(): void
    {
        $owner = User::factory()->create([
            'role' => UserRole::TENANT_OWNER,
            'email_verified_at' => now(),
        ]);
        $tenant = Tenant::factory()->forUser($owner)->create([
            'slug' => 'owner-surface',
            'status' => 'approved',
            'is_active' => true,
            'subscription_status' => 'active',
        ]);
        $owner->forceFill(['tenant_id' => (string) $tenant->getKey()])->save();
        config(['app.default_tenant_id' => (string) $tenant->getKey()]);

        $this->bindSocialLoginFakes($owner, $tenant, AuthStatus::SUCCESS);

        $response = $this->postJson('/api/v1/auth/social', [
            'provider' => 'google',
            'token' => 'social-token-456',
        ]);

        $context = AuthRedirector::contextFor($owner);

        $response->assertOk()
            ->assertJsonPath('home_surface', $context['home_surface'])
            ->assertJsonPath('audience', $context['audience'])
            ->assertJsonPath('redirect_to', $context['redirect_to']);
    }

    private function bindSocialLoginFakes(User $user, Tenant $tenant, AuthStatus $status): void
    {
        $socialUser = new class($user) {
            public array $user = ['email_verified' => true];

            public function __construct(private readonly User $userModel) {}

            public function getId(): string
            {
                return 'provider-'.$this->userModel->getKey();
            }

            public function getEmail(): string
            {
                return (string) $this->userModel->email;
            }

            public function getName(): string
            {
                return (string) $this->userModel->name;
            }

            public function getAvatar(): ?string
            {
                return null;
            }
        };

        $provider = new class($socialUser) {
            public function __construct(private readonly object $socialUser) {}

            public function stateless(): self
            {
                return $this;
            }

            public function userFromToken(string $token): object
            {
                return $this->socialUser;
            }
        };

        $socialite = Mockery::mock(SocialiteFactory::class);
        $socialite->expects('driver')->with('google')->andReturn($provider);
        $this->app->instance(SocialiteFactory::class, $socialite);

        $handler = Mockery::mock(LoginViaSocialProviderHandlerInterface::class);
        $handler->expects('handle')
            ->andReturn(new SocialLoginResult(
                status: $status,
                userId: (string) $user->getKey(),
                tenantId: (string) $tenant->getKey(),
            ));

        $tokenService = Mockery::mock(AuthTokenServiceInterface::class);
        $tokenService->expects('createTokenForUser')
            ->andReturn([
                'user' => $user->fresh(),
                'token' => 'test-token',
            ]);

        $this->app->instance(LoginViaSocialProviderHandlerInterface::class, $handler);
        $this->app->instance(AuthTokenServiceInterface::class, $tokenService);
    }
}
