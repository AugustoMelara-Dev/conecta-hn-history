<?php

namespace Tests\Feature;

use App\Application\Actions\Auth\VerifyOtpAction;
use App\Application\Auth\Commands\VerifyOtpCommand;
use App\Application\Auth\Handlers\VerifyOtpHandlerInterface;
use App\Application\Auth\Results\VerifyOtpResult;
use App\Models\OneTimePassword;
use App\Models\Tenant;
use App\Models\User;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class OtpControllerTest extends TestCase
{
    use DatabaseTransactions;

    public function test_otp_generation_requires_identifier(): void
    {
        $response = $this->postJson('/api/v1/otp/generate', [], [
            'Idempotency-Key' => 'otp-generate-missing-identifier',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['identifier']);
    }

    public function test_otp_generation_uses_a_real_tenant_for_public_requests(): void
    {
        $tenant = Tenant::factory()->create();

        $response = $this->postJson('/api/v1/otp/generate', [
            'identifier' => 'nuevo@example.com',
        ], [
            'Idempotency-Key' => 'otp-generate-public-tenant',
        ]);

        $response->assertOk()
            ->assertJson([
                'tenant_id' => (string) $tenant->getKey(),
                'delivery_mode' => 'code',
            ]);

        $this->assertDatabaseHas('one_time_passwords', [
            'identifier' => 'nuevo@example.com',
            'tenant_id' => (string) $tenant->getKey(),
        ]);
    }

    public function test_otp_generation_reports_magic_link_and_code_when_enabled(): void
    {
        $tenant = Tenant::factory()->create();
        config()->set('auth.otp.magic_link_enabled', true);
        config()->set('auth.otp.magic_link_tenant_allowlist', [(string) $tenant->getKey()]);

        $response = $this->postJson('/api/v1/otp/generate', [
            'tenant_id' => (string) $tenant->getKey(),
            'identifier' => 'magic@example.com',
        ], [
            'Idempotency-Key' => 'otp-generate-magic-mode',
        ]);

        $response->assertOk()
            ->assertJson([
                'tenant_id' => (string) $tenant->getKey(),
                'delivery_mode' => 'magic_link_and_code',
            ]);
    }

    public function test_otp_verification_works(): void
    {
        $handler = new class implements VerifyOtpHandlerInterface
        {
            public ?VerifyOtpCommand $command = null;

            public function handle(VerifyOtpCommand $command): VerifyOtpResult
            {
                $this->command = $command;

                return new VerifyOtpResult(
                    userId: 'user-1',
                    tenantId: 'tenant-test',
                    verifiedAt: new DateTimeImmutable,
                );
            }
        };

        $this->app->instance(VerifyOtpAction::class, new VerifyOtpAction($handler));

        $response = $this->postJson('/api/v1/otp/verify', [
            'tenant_id' => 'tenant-test',
            'identifier' => 'cliente@example.com',
            'otp_code' => '123456',
        ], [
            'Idempotency-Key' => 'otp-verify-success',
        ]);

        $response->assertOk()
            ->assertJson([
                'requires_registration' => false,
                'redirect' => '/app',
                'redirect_to' => '/app',
                'home_surface' => 'business_app',
                'audience' => 'owner',
            ]);

        $this->assertInstanceOf(VerifyOtpCommand::class, $handler->command);
        $this->assertSame('tenant-test', $handler->command->tenantId);
        $this->assertSame('cliente@example.com', $handler->command->identifier);
        $this->assertSame('123456', $handler->command->code);
        $this->assertSame('127.0.0.1', $handler->command->ipAddress);
        $this->assertSame('otp-verify-success', $handler->command->idempotencyKey);
    }

    public function test_otp_verification_accepts_legacy_code_alias(): void
    {
        $handler = new class implements VerifyOtpHandlerInterface
        {
            public ?VerifyOtpCommand $command = null;

            public function handle(VerifyOtpCommand $command): VerifyOtpResult
            {
                $this->command = $command;

                return new VerifyOtpResult(
                    userId: null,
                    tenantId: null,
                    verifiedAt: new DateTimeImmutable,
                    requiresRegistration: true,
                );
            }
        };

        $this->app->instance(VerifyOtpAction::class, new VerifyOtpAction($handler));

        $response = $this->postJson('/api/v1/otp/verify', [
            'tenant_id' => 'public',
            'identifier' => 'comprador@example.com',
            'code' => '654321',
        ], [
            'Idempotency-Key' => 'otp-verify-legacy-code',
        ]);

        $response->assertOk()
            ->assertJson([
                'requires_registration' => true,
            ]);

        $this->assertSame('comprador@example.com', $handler->command?->identifier);
        $this->assertSame('654321', $handler->command?->code);
    }

    public function test_otp_verification_logs_in_existing_user_before_returning_redirect(): void
    {
        $tenant = Tenant::factory()->create([
            'slug' => 'otp-login-negocio',
        ]);
        $user = User::factory()->tenantOwner()->create([
            'tenant_id' => $tenant->getKey(),
        ]);
        $tenant->forceFill(['user_id' => $user->getKey()])->save();

        $handler = new class($user) implements VerifyOtpHandlerInterface
        {
            public function __construct(private readonly User $user) {}

            public function handle(VerifyOtpCommand $command): VerifyOtpResult
            {
                return new VerifyOtpResult(
                    userId: (string) $this->user->getKey(),
                    tenantId: (string) $this->user->tenant_id,
                    verifiedAt: new DateTimeImmutable,
                );
            }
        };

        $this->app->instance(VerifyOtpAction::class, new VerifyOtpAction($handler));

        $response = $this->postJson('/api/v1/otp/verify', [
            'tenant_id' => (string) $tenant->getKey(),
            'identifier' => $user->email,
            'otp_code' => '123456',
        ], [
            'Idempotency-Key' => 'otp-verify-login-existing-user',
        ]);

        $response->assertOk()
            ->assertJson([
                'requires_registration' => false,
                'redirect_to' => route('filament.app.pages.dashboard', ['tenant' => $tenant->slug], false),
            ]);

        $this->assertTrue(Auth::check());
        $this->assertSame((string) $user->getKey(), (string) Auth::id());
    }

    public function test_valid_magic_link_consumes_otp_logs_in_user_and_redirects(): void
    {
        $tenant = Tenant::factory()->create([
            'slug' => 'magic-login-negocio',
        ]);
        $user = User::factory()->tenantOwner()->create([
            'tenant_id' => $tenant->getKey(),
        ]);
        $tenant->forceFill(['user_id' => $user->getKey()])->save();
        config()->set('auth.otp.magic_link_enabled', true);
        config()->set('auth.otp.magic_link_tenant_allowlist', [(string) $tenant->getKey()]);

        $otp = OneTimePassword::query()->create([
            'user_id' => (string) $user->getKey(),
            'identifier' => $user->email,
            'tenant_id' => (string) $tenant->getKey(),
            'code' => 'hashed-code',
            'type' => 'email',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'expires_at' => now()->addMinutes(10),
            'attempts' => 0,
        ]);

        $url = URL::temporarySignedRoute('otp.consume', now()->addMinutes(10), [
            'otp' => (string) $otp->getKey(),
        ]);

        $response = $this->get($url);

        $response->assertRedirect(route('filament.app.pages.dashboard', ['tenant' => $tenant->slug], false));
        $this->assertTrue(Auth::check());
        $this->assertSame((string) $user->getKey(), (string) Auth::id());
        $this->assertDatabaseMissing('one_time_passwords', [
            'id' => (string) $otp->getKey(),
        ]);
    }

    public function test_magic_link_is_disabled_by_default(): void
    {
        $tenant = Tenant::factory()->create();
        $otp = OneTimePassword::query()->create([
            'user_id' => null,
            'identifier' => 'disabled@example.com',
            'tenant_id' => (string) $tenant->getKey(),
            'code' => 'hashed-code',
            'type' => 'email',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'expires_at' => now()->addMinutes(10),
            'attempts' => 0,
        ]);

        $url = URL::temporarySignedRoute('otp.consume', now()->addMinutes(10), [
            'otp' => (string) $otp->getKey(),
        ]);

        $this->get($url)->assertNotFound();
        $this->assertFalse(Auth::check());
    }
}
