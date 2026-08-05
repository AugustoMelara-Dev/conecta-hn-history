<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure;

use App\Infrastructure\Models\OutboxMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class ReplayOutboxCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_specific_failed_message_is_requeued_with_canonical_retry_state(): void
    {
        $message = OutboxMessage::query()->create([
            'id' => (string) Str::uuid(),
            'type' => 'Tests\\Fixtures\\ReplayableEvent',
            'aggregate_id' => (string) Str::uuid(),
            'partition_key' => 1,
            'sequence_number' => 1,
            'payload' => ['meta' => [], 'data' => []],
            'occurred_at' => now(),
            'status' => OutboxMessage::STATUS_FAILED,
            'attempts' => OutboxMessage::MAX_ATTEMPTS,
            'recovery_count' => 2,
            'locked_at' => now(),
            'failed_at' => now(),
            'last_error' => 'provider timeout',
        ]);

        $this->artisan('outbox:replay', ['--id' => $message->id])
            ->assertSuccessful();

        $message->refresh();

        self::assertSame(OutboxMessage::STATUS_PENDING, $message->status);
        self::assertSame(0, $message->attempts);
        self::assertSame(0, $message->recovery_count);
        self::assertNull($message->locked_at);
        self::assertNull($message->failed_at);
        self::assertNull($message->last_error);
    }

    public function test_replay_never_resets_pending_or_processing_messages(): void
    {
        $pending = $this->messageWithStatus(OutboxMessage::STATUS_PENDING);
        $processing = $this->messageWithStatus(OutboxMessage::STATUS_PROCESSING, [
            'worker_id' => 'worker-active',
            'locked_at' => now(),
            'attempts' => 1,
        ]);

        $this->artisan('outbox:replay', ['--type' => 'Tests\\Fixtures\\ReplayableEvent'])
            ->assertSuccessful();

        $pending->refresh();
        $processing->refresh();

        self::assertSame(OutboxMessage::STATUS_PENDING, $pending->status);
        self::assertSame(OutboxMessage::STATUS_PROCESSING, $processing->status);
        self::assertSame('worker-active', $processing->worker_id);
        self::assertNotNull($processing->locked_at);
        self::assertSame(1, $processing->attempts);
    }

    /** @param array<string, mixed> $overrides */
    private function messageWithStatus(string $status, array $overrides = []): OutboxMessage
    {
        return OutboxMessage::query()->create(array_merge([
            'id' => (string) Str::uuid(),
            'type' => 'Tests\\Fixtures\\ReplayableEvent',
            'aggregate_id' => (string) Str::uuid(),
            'partition_key' => 1,
            'sequence_number' => random_int(10, 1000),
            'payload' => ['meta' => [], 'data' => []],
            'occurred_at' => now(),
            'status' => $status,
            'attempts' => 0,
        ], $overrides));
    }
}
