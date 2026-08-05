<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Domain\Shared\Exceptions\TenantContextMutationException;
use App\Domain\Shared\Services\TenantContext;
use App\Http\Middleware\InitializeFilamentLivewireTenantContext;
use App\Http\Middleware\InitializeFilamentTenantContext;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

final class FilamentLivewireTenantContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_panel_visit_persists_an_id_slug_selection_and_cleans_context(): void
    {
        [$user, $tenant] = $this->ownerAndTenant('owner-panel');
        $request = $this->request('GET', '/app/owner-panel', 'filament.app.tenant', $user);
        $route = $this->route('GET', '/app/{tenant}', 'filament.app.tenant');
        $route->bind($request);
        $request->setRouteResolver(static fn (): Route => $route);

        app(InitializeFilamentTenantContext::class)->handle($request, function () use ($tenant): Response {
            self::assertSame((string) $tenant->getKey(), app(TenantContext::class)->id());

            return new Response('ok');
        });

        self::assertSame([
            'id' => (string) $tenant->getKey(),
            'slug' => 'owner-panel',
        ], $request->session()->get(InitializeFilamentTenantContext::SELECTED_TENANT_SESSION_KEY));
        $this->assertContextIsClear();
    }

    public function test_real_livewire_update_wraps_the_full_downstream_request_and_cleans_context(): void
    {
        [$user, $tenant] = $this->ownerAndTenant('livewire-owner');
        $request = $this->livewireRequest($user, ['app/livewire-owner', 'app/livewire-owner']);
        $request->session()->put(InitializeFilamentTenantContext::SELECTED_TENANT_SESSION_KEY, [
            'id' => (string) $tenant->getKey(),
            'slug' => 'livewire-owner',
        ]);

        $response = app(InitializeFilamentLivewireTenantContext::class)->handle(
            $request,
            function () use ($tenant): Response {
                self::assertSame((string) $tenant->getKey(), app(TenantContext::class)->id());
                self::assertSame((string) $tenant->getKey(), (string) app('currentTenant')->getKey());
                self::assertSame((string) $tenant->getKey(), (string) app('current_tenant')->getKey());

                return new Response('wrapped');
            },
        );

        self::assertSame('wrapped', $response->getContent());
        $this->assertContextIsClear();
    }

    public function test_admin_or_public_livewire_payload_is_not_assigned_a_tenant(): void
    {
        [$user, $tenant] = $this->ownerAndTenant('ignored-owner');

        foreach (['admin', 'login', 'app/login', 'app/password-reset/request', 'app/email-verification/prompt'] as $path) {
            $request = $this->livewireRequest($user, [$path]);
            $request->session()->put(InitializeFilamentTenantContext::SELECTED_TENANT_SESSION_KEY, [
                'id' => (string) $tenant->getKey(),
                'slug' => 'ignored-owner',
            ]);
            $called = false;

            app(InitializeFilamentLivewireTenantContext::class)->handle($request, function () use (&$called): Response {
                $called = true;
                self::assertFalse(app(TenantContext::class)->isInitialized());

                return new Response('plain');
            });

            self::assertTrue($called);
            $this->assertContextIsClear();
        }
    }

    /** @param  list<string>  $paths */
    #[DataProvider('rejectedPayloadProvider')]
    public function test_mixed_mismatched_or_malformed_app_payloads_fail_closed(array $paths): void
    {
        [$user, $tenant] = $this->ownerAndTenant('selected-owner');
        $request = $this->livewireRequest($user, $paths);
        $request->session()->put(InitializeFilamentTenantContext::SELECTED_TENANT_SESSION_KEY, [
            'id' => (string) $tenant->getKey(),
            'slug' => 'selected-owner',
        ]);

        try {
            app(InitializeFilamentLivewireTenantContext::class)->handle($request, static fn (): Response => new Response('unsafe'));
            self::fail('Mixed, mismatched, or malformed app payload must fail closed.');
        } catch (HttpException $exception) {
            self::assertSame(403, $exception->getStatusCode());
        }

        $this->assertContextIsClear();
    }

    /** @return iterable<string, array{0: list<string>}> */
    public static function rejectedPayloadProvider(): iterable
    {
        yield 'different selected slug' => [['app/other-owner']];
        yield 'two app tenants' => [['app/selected-owner', 'app/other-owner']];
        yield 'mixed app and auth' => [['app/selected-owner', 'app/login']];
        yield 'unresolvable app route' => [['app/not-a-real-tenant-route/definitely-missing']];
    }

    public function test_unresolvable_app_snapshot_without_method_fails_closed(): void
    {
        [$user, $tenant] = $this->ownerAndTenant('selected-owner');
        $request = $this->request('POST', '/livewire/update', 'livewire.update', $user, [
            'components' => [[
                'snapshot' => json_encode(['memo' => ['path' => 'app/selected-owner']], JSON_THROW_ON_ERROR),
                'updates' => [],
                'calls' => [],
            ]],
        ]);
        $request->session()->put(InitializeFilamentTenantContext::SELECTED_TENANT_SESSION_KEY, [
            'id' => (string) $tenant->getKey(),
            'slug' => 'selected-owner',
        ]);

        try {
            app(InitializeFilamentLivewireTenantContext::class)->handle($request, static fn (): Response => new Response('unsafe'));
            self::fail('An app snapshot without its original method must fail closed.');
        } catch (HttpException $exception) {
            self::assertSame(403, $exception->getStatusCode());
        }
    }

    public function test_inactive_or_foreign_session_selection_fails_closed(): void
    {
        [$user, $ownedTenant] = $this->ownerAndTenant('owned');
        [, $foreignTenant] = $this->ownerAndTenant('foreign');

        foreach ([$ownedTenant, $foreignTenant] as $index => $tenant) {
            if ($index === 0) {
                $tenant->forceFill(['is_active' => false])->save();
            }

            $request = $this->livewireRequest($user, ['app/'.$tenant->slug]);
            $request->session()->put(InitializeFilamentTenantContext::SELECTED_TENANT_SESSION_KEY, [
                'id' => (string) $tenant->getKey(),
                'slug' => (string) $tenant->slug,
            ]);

            try {
                app(InitializeFilamentLivewireTenantContext::class)->handle($request, static fn (): Response => new Response('unsafe'));
                self::fail('Inactive or foreign tenant selection must fail closed.');
            } catch (HttpException $exception) {
                self::assertSame(403, $exception->getStatusCode());
            }

            $this->assertContextIsClear();
        }
    }

    public function test_preinitialized_different_context_is_rejected_as_tenant_hopping(): void
    {
        [$user, $selected] = $this->ownerAndTenant('selected');
        [, $other] = $this->ownerAndTenant('other');
        $context = app(TenantContext::class);
        $context->setTenant((string) $other->getKey());

        $request = $this->livewireRequest($user, ['app/selected']);
        $request->session()->put(InitializeFilamentTenantContext::SELECTED_TENANT_SESSION_KEY, [
            'id' => (string) $selected->getKey(),
            'slug' => 'selected',
        ]);

        try {
            $this->expectException(TenantContextMutationException::class);
            app(InitializeFilamentLivewireTenantContext::class)->handle($request, static fn (): Response => new Response('unsafe'));
        } finally {
            $context->reset();
        }
    }

    public function test_matching_preexisting_bindings_are_preserved_by_identity(): void
    {
        [$user, $tenant] = $this->ownerAndTenant('preserved');
        $context = app(TenantContext::class);
        $context->setTenant((string) $tenant->getKey());
        app()->instance('currentTenant', $tenant);
        app()->instance('current_tenant', $tenant);

        $request = $this->livewireRequest($user, ['app/preserved']);
        $request->session()->put(InitializeFilamentTenantContext::SELECTED_TENANT_SESSION_KEY, [
            'id' => (string) $tenant->getKey(),
            'slug' => 'preserved',
        ]);

        try {
            app(InitializeFilamentLivewireTenantContext::class)->handle($request, function () use ($tenant): Response {
                self::assertSame($tenant, app('currentTenant'));
                self::assertSame($tenant, app('current_tenant'));

                return new Response('ok');
            });

            self::assertSame($tenant, app('currentTenant'));
            self::assertSame($tenant, app('current_tenant'));
        } finally {
            $context->reset();
            app()->forgetInstance('currentTenant');
            app()->forgetInstance('current_tenant');
        }
    }

    public function test_mismatched_preexisting_binding_is_rejected_without_overwrite(): void
    {
        [$user, $selected] = $this->ownerAndTenant('selected-binding');
        [, $other] = $this->ownerAndTenant('other-binding');
        app()->instance('currentTenant', $other);

        $request = $this->livewireRequest($user, ['app/selected-binding']);
        $request->session()->put(InitializeFilamentTenantContext::SELECTED_TENANT_SESSION_KEY, [
            'id' => (string) $selected->getKey(),
            'slug' => 'selected-binding',
        ]);

        try {
            app(InitializeFilamentLivewireTenantContext::class)->handle($request, static fn (): Response => new Response('unsafe'));
            self::fail('A mismatched pre-existing binding must fail closed.');
        } catch (HttpException $exception) {
            self::assertSame(403, $exception->getStatusCode());
            self::assertSame($other, app('currentTenant'));
            self::assertFalse(app(TenantContext::class)->isInitialized());
        } finally {
            app()->forgetInstance('currentTenant');
        }
    }

    public function test_livewire_update_route_uses_web_group_with_the_real_context_wrapper(): void
    {
        $route = app('router')->getRoutes()->match(Request::create('/livewire/update', 'POST'));
        $middleware = app('router')->gatherRouteMiddleware($route);

        self::assertStringEndsWith('livewire.update', (string) $route->getName());
        self::assertContains(InitializeFilamentLivewireTenantContext::class, $middleware);
    }

    /** @return array{0: User, 1: Tenant} */
    private function ownerAndTenant(string $slug): array
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->for($user)->create([
            'slug' => $slug,
            'is_active' => true,
        ]);
        $user->forceFill(['tenant_id' => $tenant->getKey()])->save();

        return [$user->fresh(), $tenant->fresh()];
    }

    /** @param  list<string>  $paths */
    private function livewireRequest(User $user, array $paths): Request
    {
        $components = array_map(static fn (string $path): array => [
            'snapshot' => json_encode([
                'memo' => ['path' => $path, 'method' => 'GET'],
            ], JSON_THROW_ON_ERROR),
            'updates' => [],
            'calls' => [],
        ], $paths);

        return $this->request('POST', '/livewire/update', 'livewire.update', $user, ['components' => $components]);
    }

    /** @param  array<string, mixed>  $payload */
    private function request(string $method, string $uri, string $routeName, User $user, array $payload = []): Request
    {
        $request = Request::create($uri, $method, $payload);
        $request->setLaravelSession(new Store('filament-livewire-test', new ArraySessionHandler(120)));
        $request->setUserResolver(static fn (): User => $user);
        $route = $this->route($method, $uri, $routeName);
        $route->bind($request);
        $request->setRouteResolver(static fn (): Route => $route);

        return $request;
    }

    private function route(string $method, string $uri, string $name): Route
    {
        $route = new Route([$method], $uri, static fn (): Response => new Response());
        $route->name($name);

        return $route;
    }

    private function assertContextIsClear(): void
    {
        self::assertFalse(app(TenantContext::class)->isInitialized());
        self::assertFalse(app()->bound('currentTenant'));
        self::assertFalse(app()->bound('current_tenant'));
    }
}
