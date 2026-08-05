<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Domain\Shared\Services\TenantContext;
use App\Http\Middleware\InitializeFilamentTenantContext;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Mockery;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

final class FilamentTenantContextBoundaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_filament_request_owns_and_cleans_the_context_it_initializes(): void
    {
        $owner = User::factory()->create();
        $tenant = Tenant::factory()->forUser($owner)->create();
        $request = $this->requestFor($tenant, $owner);
        $context = app(TenantContext::class);

        $response = app(InitializeFilamentTenantContext::class)->handle(
            $request,
            function () use ($context, $tenant): Response {
                self::assertSame((string) $tenant->getKey(), $context->id());
                self::assertSame((string) $tenant->getKey(), (string) app('currentTenant')->getKey());
                self::assertSame((string) $tenant->getKey(), (string) app('current_tenant')->getKey());

                return new Response('ok');
            },
        );

        self::assertSame('ok', $response->getContent());
        $this->assertContextIsClear($context);
    }

    public function test_filament_request_cleans_context_when_downstream_throws(): void
    {
        $owner = User::factory()->create();
        $tenant = Tenant::factory()->forUser($owner)->create();
        $context = app(TenantContext::class);

        try {
            app(InitializeFilamentTenantContext::class)->handle(
                $this->requestFor($tenant, $owner),
                static function (): never {
                    throw new RuntimeException('Filament boundary failed.');
                },
            );

            self::fail('The downstream exception should be propagated.');
        } catch (RuntimeException $exception) {
            self::assertSame('Filament boundary failed.', $exception->getMessage());
        }

        $this->assertContextIsClear($context);
    }

    private function requestFor(Tenant $tenant, User $owner): Request
    {
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('route')->with('tenant')->andReturn($tenant);
        $request->shouldReceive('user')->andReturn($owner);
        $request->shouldReceive('session')->andReturn(session());

        return $request;
    }

    private function assertContextIsClear(TenantContext $context): void
    {
        self::assertFalse($context->isInitialized());
        self::assertFalse(app()->bound('currentTenant'));
        self::assertFalse(app()->bound('current_tenant'));
    }
}
