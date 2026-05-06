<?php

declare(strict_types=1);

namespace Tests\Feature\Actions;

use App\Actions\Interactions\RecordInteractionAction;
use App\Domain\Interactions\DTOs\InteractionData;
use App\Domain\Interactions\Events\InteractionRecorded;
use App\Domain\Interactions\Repositories\InteractionRepositoryInterface;
use App\Models\Interaction;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Mockery;
use Tests\TestCase;

class RecordInteractionActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_persists_interaction_via_repository_and_dispatches_event()
    {
        Event::fake([InteractionRecorded::class]);

        $tenant = Tenant::factory()->create();
        
        $data = new InteractionData(
            $tenant->id,
            Interaction::TYPE_WHATSAPP_CLICK,
            '127.0.0.1',
            'TestAgent',
            'test-session-id',
            123,
            ['foo' => 'bar']
        );

        // Mock Repository
        $mockRepo = Mockery::mock(InteractionRepositoryInterface::class);
        $mockRepo->shouldReceive('exists')
            ->once()
            ->andReturn(false);
            
        $mockRepo->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($arg) use ($tenant) {
                 return $arg['type'] === Interaction::TYPE_WHATSAPP_CLICK &&
                        $arg['session_id'] === 'test-session-id';
            }), $tenant->id)
            ->andReturn(new Interaction([
                'id' => 1, 
                'tenant_id' => $tenant->id,
                'type' => Interaction::TYPE_WHATSAPP_CLICK
            ]));

        $this->instance(InteractionRepositoryInterface::class, $mockRepo);

        $action = app(RecordInteractionAction::class);
        $result = $action->execute($data);

        // Assertions
        $this->assertNotNull($result);
        Event::assertDispatched(InteractionRecorded::class, function ($event) use ($result) {
            return $event->interaction->type === $result->type;
        });
    }

    public function test_idempotency_prevents_duplicates()
    {
        Event::fake();

        $tenant = Tenant::factory()->create();
        $data = new InteractionData(
            $tenant->id,
            Interaction::TYPE_WHATSAPP_CLICK,
            '127.0.0.1',
            'TestAgent',
            'test-session-id',
            123,
            []
        );

        $mockRepo = Mockery::mock(InteractionRepositoryInterface::class);
        $mockRepo->shouldReceive('exists')
            ->once()
            ->andReturn(true); // Simulate duplicate
            
        $mockRepo->shouldNotReceive('create');
        
        $this->instance(InteractionRepositoryInterface::class, $mockRepo);

        $action = app(RecordInteractionAction::class);
        $result = $action->execute($data);

        $this->assertNull($result);
        Event::assertNotDispatched(InteractionRecorded::class);
    }
}
