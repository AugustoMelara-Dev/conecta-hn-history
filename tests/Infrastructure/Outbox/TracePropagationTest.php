<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Outbox;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

class TracePropagationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_processes_pending_outbox_message_and_marks_it_completed(): void
    {
        $id = Uuid::uuid7()->toString();

        DB::table('outbox_messages')->insert([
            'id' => $id,
            'type' => 'App\\Domain\\Auth\\Events\\OtpVerified',
            'aggregate_id' => 'user-1',
            'payload' => json_encode(['foo' => 'bar']),
            'status' => 'PENDING',
            'occurred_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('outbox:process', ['--once' => true])->assertSuccessful();

        $this->assertDatabaseHas('outbox_messages', [
            'id' => $id,
            'status' => 'COMPLETED',
        ]);
    }
}
