<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Infrastructure\Jobs\AI\IndexTenantKnowledgeJob;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class ReindexAiKnowledgeCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_queues_a_scoped_reindex_for_one_tenant(): void
    {
        config()->set('ai.knowledge.enabled', false);
        $tenant = Tenant::factory()->create();
        Queue::fake();
        config()->set('ai.knowledge.enabled', true);

        $this->artisan('ai:knowledge:reindex', [
            '--tenant' => (string) $tenant->getKey(),
        ])->assertSuccessful();

        Queue::assertPushed(
            IndexTenantKnowledgeJob::class,
            fn (IndexTenantKnowledgeJob $job): bool => $job->tenantId === (string) $tenant->getKey(),
        );
    }

    public function test_it_refuses_to_run_when_knowledge_is_disabled(): void
    {
        config()->set('ai.knowledge.enabled', false);
        Queue::fake();

        $this->artisan('ai:knowledge:reindex')->assertFailed();

        Queue::assertNothingPushed();
    }
}
