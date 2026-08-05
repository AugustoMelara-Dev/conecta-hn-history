<?php

declare(strict_types=1);

namespace Tests\Feature\AI;

use App\Infrastructure\Jobs\AI\IndexTenantKnowledgeJob;
use App\Models\Product;
use App\Models\Service;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class KnowledgeIndexDispatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_and_business_changes_queue_one_tenant_scoped_index_job(): void
    {
        config()->set('ai.knowledge.enabled', true);
        Queue::fake();

        $tenant = Tenant::factory()->create();
        Product::factory()->forTenant($tenant)->create();
        Service::factory()->for($tenant)->create();

        Queue::assertPushed(IndexTenantKnowledgeJob::class, function (IndexTenantKnowledgeJob $job) use ($tenant): bool {
            return $job->tenantId === (string) $tenant->getKey()
                && $job->uniqueId() === 'knowledge:'.(string) $tenant->getKey()
                && $job->afterCommit === true;
        });
    }

    public function test_credit_balance_updates_do_not_create_a_reindex_loop(): void
    {
        config()->set('ai.knowledge.enabled', false);
        $tenant = Tenant::factory()->create();
        Queue::fake();
        config()->set('ai.knowledge.enabled', true);

        $tenant->forceFill(['ai_balance' => 0.9])->save();

        Queue::assertNothingPushed();

        $tenant->update(['name' => 'Nombre indexable']);

        Queue::assertPushed(IndexTenantKnowledgeJob::class, 1);
    }
}
