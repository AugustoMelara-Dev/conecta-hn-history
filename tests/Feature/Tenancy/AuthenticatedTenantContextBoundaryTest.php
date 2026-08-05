<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Domain\Shared\Services\TenantContext;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\TestCase;

final class AuthenticatedTenantContextBoundaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_requests_clean_context_between_reused_application_cycles(): void
    {
        Route::middleware(['auth:sanctum', 'tenant.context'])
            ->get('/_test/tenant-context', static fn () => response()->json([
                'tenant_id' => app(TenantContext::class)->id(),
                'container_tenant_id' => app('currentTenant')->getKey(),
            ]));

        [$ownerA, $tenantA] = $this->tenantOwner();
        [$ownerB, $tenantB] = $this->tenantOwner();
        $context = app(TenantContext::class);

        foreach ([[$ownerA, $tenantA], [$ownerB, $tenantB]] as [$owner, $tenant]) {
            Sanctum::actingAs($owner);

            $this->withHeader('X-Tenant-ID', (string) $tenant->getKey())
                ->getJson('/_test/tenant-context')
                ->assertOk()
                ->assertJsonPath('tenant_id', (string) $tenant->getKey())
                ->assertJsonPath('container_tenant_id', (string) $tenant->getKey());

            $this->assertContextIsClear($context);
        }
    }

    public function test_exception_response_cleans_context(): void
    {
        Route::middleware(['auth:sanctum', 'tenant.context'])
            ->get('/_test/tenant-context-failure', static function (): never {
                throw new RuntimeException('Boundary failure.');
            });

        [$owner, $tenant] = $this->tenantOwner();
        $context = app(TenantContext::class);
        Sanctum::actingAs($owner);
        $this->withoutExceptionHandling();

        try {
            $this->withHeader('X-Tenant-ID', (string) $tenant->getKey())
                ->get('/_test/tenant-context-failure');

            self::fail('The route exception should be propagated.');
        } catch (RuntimeException $exception) {
            self::assertSame('Boundary failure.', $exception->getMessage());
        }

        $this->assertContextIsClear($context);
    }

    public function test_stream_callback_restores_only_its_tenant_and_cleans_after_streaming(): void
    {
        Route::middleware(['auth:sanctum', 'tenant.context'])
            ->get('/_test/tenant-context-stream', static fn () => response()->stream(
                static fn () => print app(TenantContext::class)->id(),
            ));

        [$owner, $tenant] = $this->tenantOwner();
        $context = app(TenantContext::class);
        Sanctum::actingAs($owner);

        $response = $this->withHeader('X-Tenant-ID', (string) $tenant->getKey())
            ->get('/_test/tenant-context-stream')
            ->assertOk();

        $this->assertContextIsClear($context);
        self::assertSame((string) $tenant->getKey(), $response->streamedContent());
        $this->assertContextIsClear($context);
    }

    /**
     * @return array{User, Tenant}
     */
    private function tenantOwner(): array
    {
        $owner = User::factory()->create();
        $tenant = Tenant::factory()->forUser($owner)->create();
        $owner->forceFill(['tenant_id' => $tenant->getKey()])->save();

        return [$owner, $tenant];
    }

    private function assertContextIsClear(TenantContext $context): void
    {
        self::assertFalse($context->isInitialized());
        self::assertFalse(app()->bound('currentTenant'));
        self::assertFalse(app()->bound('current_tenant'));
    }
}
