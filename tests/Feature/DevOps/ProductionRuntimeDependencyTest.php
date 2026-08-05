<?php

declare(strict_types=1);

namespace Tests\Feature\DevOps;

use App\Domain\Identity\Enums\UserRole;
use App\Http\Middleware\RequireTwoFactorSession;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

final class ProductionRuntimeDependencyTest extends TestCase
{
    public function test_worker_and_storage_guard_commands_are_registered_in_the_production_graph(): void
    {
        self::assertArrayHasKey('horizon', Artisan::all());
        self::assertArrayHasKey('storage:production-guard', Artisan::all());
    }

    public function test_horizon_dashboard_requires_authentication_mfa_and_canonical_superadmin_gate(): void
    {
        self::assertSame(
            ['web', 'auth', RequireTwoFactorSession::class],
            config('horizon.middleware'),
        );

        $provider = (string) file_get_contents(base_path('app/Providers/HorizonServiceProvider.php'));
        self::assertStringContainsString('$user instanceof User && $user->isSuperAdmin()', $provider);
    }

    public function test_required_unenrolled_platform_admin_cannot_reach_horizon_middleware_chain(): void
    {
        $user = new User;
        $user->forceFill([
            'id' => 901,
            'email' => 'platform-security@example.test',
            'role' => UserRole::SUPER_ADMIN,
        ]);
        config()->set([
            'auth.super_admins' => [$user->email],
            'security.admin_mfa_required' => true,
        ]);

        $request = Request::create('/horizon', 'GET');
        $request->setUserResolver(static fn (): User => $user);
        $request->setLaravelSession(app('session.store'));

        $response = app(RequireTwoFactorSession::class)->handle(
            $request,
            static fn () => response('ALLOWED'),
        );

        self::assertSame(302, $response->getStatusCode());
        self::assertSame(route('profile.two-factor.show'), $response->headers->get('Location'));
    }

    public function test_production_guard_rejects_plaintext_redis_even_when_queue_uses_redis(): void
    {
        config()->set([
            'queue.default' => 'redis',
            'database.redis.default.url' => null,
            'database.redis.default.scheme' => 'tcp',
        ]);

        $exitCode = Artisan::call('config:production-guard', [
            '--env' => 'production',
            '--json' => true,
        ]);
        $payload = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(1, $exitCode);
        self::assertContains('REDIS_SCHEME', array_column($payload['violations'], 'key'));
    }
}
