<?php

namespace Tests\Infrastructure\Outbox;

use App\Infrastructure\Shared\Telemetry\TraceContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

class TracePropagationTest extends TestCase
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;

    /** @test */
    public function it_rehydrates_trace_context_from_outbox_message()
    {
        // 1. Arrange: Create a message with a specific Trace ID
        $traceId = 'FORENSIC-TRACE-12345';
        $eventId = Uuid::uuid7()->toString();

        // We insert directly to bypass the repository for pure state setup
        DB::table('domain_outbox')->insert([
            'id' => $eventId,
            'type' => 'App\Domain\Auth\Events\OtpVerified', // Dummy event type
            'aggregate_id' => 'user-1',
            'correlation_id' => $traceId,
            'causation_id' => null,
            'payload' => json_encode(['foo' => 'bar']), // Dummy payload
            'metadata' => json_encode(['trace_id' => $traceId]),
            'status' => 'PENDING',
            'created_at' => now(),
            'updated_at' => now(),
            'occurred_at' => now(),
        ]);

        // Mock TraceContext using instance binding (Singleton behavior)
        $mockContext = \Mockery::mock(TraceContext::class);
        $mockContext->shouldReceive('setContext')
            ->with($traceId, \Mockery::any()) // Focus on TraceID, ignore causation format nuances
            ->once();

        $this->app->instance(TraceContext::class, $mockContext);

        // 2. Act: Run the worker/command
        // We use artisan() which resolves the command from the container,
        // using our bound TraceContext mock.
        $this->artisan('outbox:process');
    }
}
