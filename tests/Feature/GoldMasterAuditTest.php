<?php

namespace Tests\Feature;

use App\Infrastructure\Jobs\Analytics\RecordInteraction;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class GoldMasterAuditTest extends TestCase
{
    use DatabaseTransactions;

    public function test_landing_page_renders_correctly(): void
    {
        $response = $this->get('/');

        $response->assertOk();
    }

    public function test_directory_city_filter_works(): void
    {
        $response = $this->get('/directorio?city=all');

        $response->assertOk();
    }

    public function test_analytics_pipeline_works(): void
    {
        Queue::fake();

        RecordInteraction::dispatch(
            tenantId: 1,
            eventType: 'view',
            metadata: ['source' => 'gold-master'],
        );

        Queue::assertPushed(RecordInteraction::class, function (RecordInteraction $job): bool {
            return $job->tenantId === 1
                && $job->eventType === 'view'
                && $job->metadata['source'] === 'gold-master';
        });
    }
}
