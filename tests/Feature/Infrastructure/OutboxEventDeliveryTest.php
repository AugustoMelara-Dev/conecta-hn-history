<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure;

use App\Domain\Identity\Events\UserRegisteredViaSocial;
use App\Infrastructure\Models\OutboxMessage;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class OutboxEventDeliveryTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function processor_hydrates_and_dispatches_before_marking_message_completed(): void
    {
        Event::fake([UserRegisteredViaSocial::class]);
        $tenantId = (string) Str::uuid();
        Tenant::factory()->create(['id' => $tenantId]);
        $userId = (string) Str::uuid();
        $message = $this->message(UserRegisteredViaSocial::class, [
            'user_id' => $userId,
            'tenant_id' => $tenantId,
            'email' => 'outbox@example.test',
            'provider' => 'google',
            'occurred_at' => '2026-07-21T12:00:00+00:00',
        ]);

        $this->artisan('outbox:process', ['--once' => true])->assertSuccessful();

        Event::assertDispatched(
            UserRegisteredViaSocial::class,
            static fn (UserRegisteredViaSocial $event): bool => $event->tenantId === $tenantId && $event->userId === $userId,
        );
        self::assertSame(OutboxMessage::STATUS_COMPLETED, $message->fresh()->status);
        self::assertNotNull($message->fresh()->published_at);
    }

    #[Test]
    public function processor_keeps_an_unhydratable_message_pending(): void
    {
        $message = $this->message('Missing\\Domain\\Event', ['value' => 'invalid']);

        $this->artisan('outbox:process', ['--once' => true])->assertSuccessful();

        self::assertSame(OutboxMessage::STATUS_PENDING, $message->fresh()->status);
        self::assertNull($message->fresh()->published_at);
    }

    /** @param array<string, mixed> $data */
    private function message(string $type, array $data): OutboxMessage
    {
        return OutboxMessage::create([
            'id' => (string) Str::uuid(),
            'type' => $type,
            'aggregate_id' => (string) Str::uuid(),
            'partition_key' => 0,
            'sequence_number' => 0,
            'payload' => [
                'meta' => ['event_type' => $type],
                'data' => $data,
            ],
            'occurred_at' => now(),
            'status' => OutboxMessage::STATUS_PENDING,
            'attempts' => 0,
            'recovery_count' => 0,
        ]);
    }
}
