<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Domain\Access\Enums\Role;
use App\Infrastructure\Shared\Middleware\TenantQueueMiddleware;
use App\Jobs\GenerateBusinessProfileJob;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class PrivateAiTenantContextIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_optimization_queues_uuid_context_authorized_by_membership(): void
    {
        [$owner, $tenant] = $this->tenantOwner('ai-profile-owner');
        config()->set([
            'ai.private.enabled' => true,
            'ai.private.tenant_allowlist' => [(string) $tenant->id],
        ]);
        Sanctum::actingAs($owner);
        Queue::fake();

        $this->withHeader('X-Tenant-ID', (string) $tenant->id)
            ->postJson('/api/v1/ai/optimize-profile', $this->payload())
            ->assertAccepted();

        Queue::assertPushed(GenerateBusinessProfileJob::class, function (GenerateBusinessProfileJob $job) use ($owner, $tenant): bool {
            return $job->tenantId === (string) $tenant->id
                && $job->userId === (string) $owner->id
                && $job->middleware()[0] instanceof TenantQueueMiddleware;
        });
    }

    public function test_ai_routes_reject_an_unrelated_tenant_header_before_dispatch(): void
    {
        [$owner] = $this->tenantOwner('ai-profile-a');
        [, $otherTenant] = $this->tenantOwner('ai-profile-b', 'ai-other@example.test');
        config()->set([
            'ai.private.enabled' => true,
            'ai.private.tenant_allowlist' => [(string) $otherTenant->id],
        ]);
        Sanctum::actingAs($owner);
        Queue::fake();

        $this->withHeader('X-Tenant-ID', (string) $otherTenant->id)
            ->postJson('/api/v1/ai/optimize-profile', $this->payload())
            ->assertForbidden();

        Queue::assertNothingPushed();
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return [
            'business_name' => 'Café Tegus',
            'description' => 'Café hondureño con granos seleccionados y servicio local.',
            'target_audience' => 'Compradores locales',
            'tone' => 'professional',
            'keywords' => ['café', 'Honduras'],
        ];
    }

    /** @return array{User, Tenant} */
    private function tenantOwner(string $slug, string $email = 'ai-owner@example.test'): array
    {
        $owner = User::factory()->tenantOwner()->create(['email' => $email]);
        $tenant = Tenant::factory()->pro()->forUser($owner)->create(['slug' => $slug]);
        $owner->forceFill(['tenant_id' => $tenant->id])->save();
        $tenant->memberships()->create([
            'user_id' => $owner->id,
            'role' => Role::OWNER,
            'accepted_at' => now(),
        ]);

        return [$owner->refresh(), $tenant];
    }
}
