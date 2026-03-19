<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Outbox;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

final class TracePropagationTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_processes_an_outbox_message_that_contains_trace_metadata(): void
    {
        $traceId = 'FORENSIC-TRACE-12345';
        $eventId = Uuid::uuid7()->toString();

        DB::table('domain_outbox')->insert([
            'id' => $eventId,
            'type' => 'App\Domain\Auth\Events\OtpVerified',
            'aggregate_id' => 'user-1',
            'correlation_id' => $traceId,
            'causation_id' => null,
            'payload' => json_encode(['foo' => 'bar'], JSON_THROW_ON_ERROR),
            'metadata' => json_encode(['trace_id' => $traceId], JSON_THROW_ON_ERROR),
            'status' => 'PENDING',
            'created_at' => now(),
            'updated_at' => now(),
            'occurred_at' => now(),
        ]);

        $this->artisan('outbox:process')
            ->assertExitCode(0);
    }
}
