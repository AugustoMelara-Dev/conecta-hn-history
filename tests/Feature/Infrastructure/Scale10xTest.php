<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure;

use App\Application\Shared\Contracts\SchemaValidatorInterface;
use App\Domain\Shared\Exceptions\SchemaViolationException;
use App\Http\Middleware\EnsureIdempotencyKey;
use App\Infrastructure\Shared\Services\Upcasting\UpcasterChain;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

class Scale10xTest extends TestCase
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\Test]
    public function phase_1_schema_registry_blocks_malformed_events()
    {
        // 1. Arrange: Mock the Outbox Repository setup manually to test the service in isolation OR use the bind
        /** @var SchemaValidatorInterface $validator */
        $validator = $this->app->make(SchemaValidatorInterface::class);

        // Create an Invalid Payload for 'user.registered.v1'
        // Missing 'userId' which is required
        $invalidPayload = [
            'email' => 'hacker@example.com',
            'tenantId' => Uuid::uuid7()->toString(),
        ];

        // 2. Act & Assert: Expect SchemaViolationException
        $this->expectException(SchemaViolationException::class);

        $validator->validate('user.registered.v1', $invalidPayload);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function phase_2_upcasting_transforms_legacy_data_transparently()
    {
        // 1. Arrange: Insert a "V1" event manually into domain_outbox

        $v1Payload = [
            'fullname' => 'John Doe', // V1 field
            'email' => 'john@example.com',
            // Other fields not strictly checked by upcaster but good to have
        ];

        $envelope = [
            'meta' => ['version' => '1.0', 'event_type' => 'App\Domain\Identity\Events\UserRegistered'],
            'data' => $v1Payload,
        ];

        $outboxId = Uuid::uuid7()->toString();

        DB::table('domain_outbox')->insert([
            'id' => $outboxId,
            'type' => 'App\Domain\Identity\Events\UserRegistered', // Must match Upcaster check
            'aggregate_id' => Uuid::uuid7()->toString(),
            'payload' => json_encode($envelope),
            'metadata' => json_encode(['trace_id' => '123']),
            'status' => 'PENDING',
            'correlation_id' => Uuid::uuid7()->toString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 2. Act: Run ProcessOutboxCommand
        // We need to Mock the Event Dispatcher so we can inspect what was dispatched
        Event::fake();

        $this->artisan('outbox:process');

        // 3. Assert: Verify the event dispatched has the V2 structure

        /** @var UpcasterChain $chain */
        $chain = $this->app->make(UpcasterChain::class);

        $upcasted = $chain->upcast('App\Domain\Identity\Events\UserRegistered', $v1Payload, '1.0');

        $this->assertArrayHasKey('name', $upcasted);
        $this->assertEquals('John Doe', $upcasted['name']);
        $this->assertArrayNotHasKey('fullname', $upcasted);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function phase_3_idempotency_middleware_enforces_header()
    {
        $middleware = $this->app->make(EnsureIdempotencyKey::class);
        $request = Request::create('/api/v1/auth/otp/generate', 'POST');

        $response = $middleware->handle($request, fn () => response()->json(['ok' => true]));
        $payload = json_decode((string) $response->getContent(), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('Missing Idempotency-Key header', $payload['error']);
    }
}
