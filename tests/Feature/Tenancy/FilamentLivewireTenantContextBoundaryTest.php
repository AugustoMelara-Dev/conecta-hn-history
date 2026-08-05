<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Domain\Shared\Exceptions\TenantContextMutationException;
use App\Domain\Shared\Services\TenantContext;
use App\Http\Middleware\InitializeFilamentLivewireTenantContext;
use App\Http\Middleware\InitializeFilamentTenantContext;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

final class FilamentLivewireTenantContextBoundaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_livewire_app_context_is_initialized_and_cleaned(): void
    {
        [$owner, $tenant] = $this->ownerTenant('livewire-owner');
        $request = $this->livewireRequest($owner, [$tenant->slug], $tenant);
        $context = app(TenantContext::class);

        $response = app(InitializeFilamentLivewireTenantContext::class)->handle(
            $request,
            function () use ($context, $tenant): Response {
                self::assertSame((string) $tenant->getKey(), $context->id());
                self::assertSame((string) $tenant->getKey(), (string) app('currentTenant')->getKey());
                self::assertSame((string) $tenant->getKey(), (string) app('current_tenant')->getKey());

                return new Response('ok');
            },
        );

        self::assertSame('ok', $response->getContent());
        self::assertFalse($context->isInitialized());
        self::assertFalse(app()->bound('currentTenant'));
        self::assertFalse(app()->bound('current_tenant'));
    }

    public function test_livewire_app_context_is_cleaned_when_downstream_fails(): void
    {
        [$owner, $tenant] = $this->ownerTenant('livewire-failure');
        $context = app(TenantContext::class);

        try {
            app(InitializeFilamentLivewireTenantContext::class)->handle(
                $this->livewireRequest($owner, [$tenant->slug], $tenant),
                static function (): never {
                    throw new \RuntimeException('livewire downstream failed');
                },
            );

            self::fail('The downstream exception should be propagated.');
        } catch (\RuntimeException $exception) {
            self::assertSame('livewire downstream failed', $exception->getMessage());
        }

        self::assertFalse($context->isInitialized());
        self::assertFalse(app()->bound('currentTenant'));
        self::assertFalse(app()->bound('current_tenant'));
    }

    public function test_session_selection_is_written_only_for_an_authenticated_active_authorized_route(): void
    {
        [$owner, $tenant] = $this->ownerTenant('session-authorized');
        $request = Request::create('/app/'.$tenant->slug, 'GET');
        $request->setUserResolver(static fn (): User => $owner);
        $request->setLaravelSession(session()->driver());
        $route = new Route(['GET'], 'app/{tenant}', static fn (): Response => new Response('ok'));
        $route->name('filament.app.pages.dashboard');
        $route->bind($request);
        $request->setRouteResolver(static function () use ($route): Route {
            return $route;
        });

        app(InitializeFilamentTenantContext::class)->handle(
            $request,
            static fn (): Response => new Response('ok'),
        );

        self::assertSame([
            'id' => (string) $tenant->getKey(),
            'slug' => (string) $tenant->slug,
        ], session(InitializeFilamentTenantContext::SELECTED_TENANT_SESSION_KEY));
    }

    public function test_session_selection_is_not_written_for_unauthenticated_inactive_or_foreign_tenant(): void
    {
        [$owner, $tenant] = $this->ownerTenant('session-inactive');
        $foreign = User::factory()->create();
        $inactive = Tenant::factory()->forUser($owner)->inactive()->create(['slug' => 'session-inactive-closed']);

        foreach ([
            [null, $tenant],
            [$owner, $inactive],
            [$foreign, $tenant],
        ] as [$user, $candidate]) {
            session()->forget(InitializeFilamentTenantContext::SELECTED_TENANT_SESSION_KEY);
            $request = Request::create('/app/'.$candidate->slug, 'GET');
            $request->setUserResolver(static fn () => $user);
            $request->setLaravelSession(session()->driver());
            $request->setRouteResolver(static function () use ($candidate, $request): Route {
                $route = new Route(['GET'], 'app/{tenant}', static fn (): Response => new Response('ok'));
                $route->setParameter('tenant', $candidate);
                $route->bind($request);
                return $route;
            });

            app(InitializeFilamentTenantContext::class)->handle(
                $request,
                static fn (): Response => new Response('ok'),
            );

            self::assertNull(session(InitializeFilamentTenantContext::SELECTED_TENANT_SESSION_KEY));
            self::assertFalse(app(TenantContext::class)->isInitialized());
        }
    }

    public function test_livewire_selection_reauthenticates_id_and_slug_pair(): void
    {
        [$owner, $tenant] = $this->ownerTenant('livewire-reauth');
        $other = Tenant::factory()->forUser($owner)->create(['slug' => 'livewire-other']);

        $this->expectException(HttpException::class);
        app(InitializeFilamentLivewireTenantContext::class)->handle(
            $this->livewireRequest($owner, [$tenant->slug], $tenant, [
                'id' => (string) $other->getKey(),
                'slug' => (string) $tenant->slug,
            ]),
            static fn (): Response => new Response('must not execute'),
        );
    }

    public function test_livewire_selection_rejects_slug_mismatch(): void
    {
        [$owner, $tenant] = $this->ownerTenant('livewire-mismatch');

        $this->expectException(HttpException::class);
        app(InitializeFilamentLivewireTenantContext::class)->handle(
            $this->livewireRequest($owner, ['different-slug'], $tenant),
            static fn (): Response => new Response('must not execute'),
        );
    }

    public function test_livewire_selection_rejects_inactive_or_foreign_tenant(): void
    {
        [$owner, $tenant] = $this->ownerTenant('livewire-active');
        $foreign = User::factory()->create();
        $inactive = Tenant::factory()->forUser($owner)->inactive()->create(['slug' => 'livewire-inactive']);

        foreach ([[$owner, $inactive], [$foreign, $tenant]] as [$user, $candidate]) {
            $this->expectException(HttpException::class);
            app(InitializeFilamentLivewireTenantContext::class)->handle(
                $this->livewireRequest($user, [$candidate->slug], $candidate),
                static fn (): Response => new Response('must not execute'),
            );
            app(TenantContext::class)->reset();
        }
    }

    public function test_admin_and_public_livewire_payloads_are_outside_tenant_wrapper(): void
    {
        [$owner, $tenant] = $this->ownerTenant('livewire-public');
        $context = app(TenantContext::class);

        foreach (['admin/dashboard', 'public/home'] as $path) {
            $request = $this->livewireRequest($owner, [], $tenant, null, [$path]);
            $response = app(InitializeFilamentLivewireTenantContext::class)->handle(
                $request,
                static fn (): Response => new Response('outside'),
            );

            self::assertSame('outside', $response->getContent());
            self::assertFalse($context->isInitialized());
        }
    }

    public function test_mixed_or_different_app_snapshots_fail_closed(): void
    {
        [$owner, $tenant] = $this->ownerTenant('livewire-mixed');
        $cases = [
            [$tenant->slug, 'admin/dashboard'],
            [$tenant->slug, 'app/other-tenant/products'],
        ];

        foreach ($cases as [$first, $second]) {
            $this->expectException(HttpException::class);
            app(InitializeFilamentLivewireTenantContext::class)->handle(
                $this->livewireRequest($owner, [$tenant->slug, $second], $tenant, null, [$first, $second]),
                static fn (): Response => new Response('must not execute'),
            );
            app(TenantContext::class)->reset();
        }
    }

    public function test_preinitialized_same_tenant_is_preserved_but_hopping_throws(): void
    {
        [$owner, $tenant] = $this->ownerTenant('livewire-preinitialized');
        $context = app(TenantContext::class);
        $context->setTenant((string) $tenant->getKey());

        app(InitializeFilamentLivewireTenantContext::class)->handle(
            $this->livewireRequest($owner, [$tenant->slug], $tenant),
            static fn (): Response => new Response('same'),
        );

        self::assertTrue($context->isInitialized());
        $context->reset();

        $otherOwner = User::factory()->create();
        $otherTenant = Tenant::factory()->forUser($otherOwner)->create(['slug' => 'livewire-hop']);
        $context->setTenant((string) $tenant->getKey());

        $this->expectException(TenantContextMutationException::class);
        try {
            app(InitializeFilamentLivewireTenantContext::class)->handle(
                $this->livewireRequest($otherOwner, [$otherTenant->slug], $otherTenant),
                static fn (): Response => new Response('must not execute'),
            );
        } finally {
            $context->reset();
        }
    }

    public function test_livewire_update_route_keeps_web_middleware_and_livewire_update_name(): void
    {
        $route = collect(app('router')->getRoutes()->getRoutes())
            ->first(static fn (Route $route): bool => $route->uri() === 'livewire/update'
                && in_array('POST', $route->methods(), true)
                && str_ends_with((string) $route->getName(), 'livewire.update'));

        self::assertInstanceOf(Route::class, $route);
        self::assertContains('web', (array) ($route->getAction('middleware') ?? []));
    }

    /** @return array{0: User, 1: Tenant} */
    private function ownerTenant(string $slug): array
    {
        $owner = User::factory()->create();
        $tenant = Tenant::factory()->forUser($owner)->create(['slug' => $slug]);

        return [$owner, $tenant];
    }

    /**
     * @param  list<string>  $snapshotSlugs
     * @param  array{id?: string, slug?: string}|null  $selection
     * @param  list<string>|null  $rawPaths
     */
    private function livewireRequest(
        User $user,
        array $snapshotSlugs,
        Tenant $tenant,
        ?array $selection = null,
        ?array $rawPaths = null,
    ): Request {
        $paths = $rawPaths ?? array_map(
            static fn (string $slug): string => 'app/'.$slug,
            $snapshotSlugs,
        );
        $components = array_map(
            static fn (string $path): array => [
                'snapshot' => json_encode(['memo' => ['path' => $path, 'method' => 'GET']], JSON_THROW_ON_ERROR),
                'updates' => [],
                'calls' => [],
            ],
            $paths,
        );

        $request = Request::create('/livewire/update', 'POST', ['components' => $components]);
        $route = new Route(['POST'], 'livewire/update', static fn (): Response => new Response('ok'));
        $route->name('livewire.update');
        $route->bind($request);
        $request->setRouteResolver(static function () use ($route): Route {
            return $route;
        });
        $request->setUserResolver(static function () use ($user): User {
            return $user;
        });
        $request->setLaravelSession(session()->driver());

        session()->put(InitializeFilamentTenantContext::SELECTED_TENANT_SESSION_KEY, $selection ?? [
            'id' => (string) $tenant->getKey(),
            'slug' => (string) $tenant->slug,
        ]);

        return $request;
    }
}
