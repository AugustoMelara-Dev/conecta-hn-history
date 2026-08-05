<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Outbox;

use App\Domain\Auth\Events\OtpVerified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

class TracePropagationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_processes_pending_outbox_message_and_marks_it_completed(): void
    {
        $id = Uuid::uuid7()->toString();
        $tenantId = Uuid::uuid7()->toString();
        $userId = Uuid::uuid7()->toString();
        Event::fake([OtpVerified::class]);

        DB::table('outbox_messages')->insert([
            'id' => $id,
            'type' => OtpVerified::class,
            'aggregate_id' => $userId,
            'payload' => json_encode([
                'meta' => ['event_type' => OtpVerified::class, 'trace_id' => 'trace-1'],
                'data' => [
                    'event_id' => $id,
                    'otp_identifier' => 'otp-record-1',
                    'user_id' => $userId,
                    'tenant_id' => $tenantId,
                    'verified_at' => now()->toAtomString(),
                ],
            ]),
            'status' => 'PENDING',
            'occurred_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('outbox:process', ['--once' => true])->assertSuccessful();

        Event::assertDispatched(
            OtpVerified::class,
            static fn (OtpVerified $event): bool => $event->tenantId === $tenantId && $event->userId === $userId,
        );

        $this->assertDatabaseHas('outbox_messages', [
            'id' => $id,
            'status' => 'COMPLETED',
        ]);
    }
}
