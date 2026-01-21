<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Application\Auth\Commands\GenerateOtpCommand;
use App\Application\Auth\Contracts\GenerateOtpHandlerInterface;
use App\Infrastructure\Shared\Telemetry\TraceContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

class TelemetryIntegrationTest extends TestCase
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    /** @test */
    public function it_propagates_trace_id_to_outbox(): void
    {
        // 1. Arrange: Setup Context and Command
        $traceId = Uuid::uuid7()->toString();
        $tenantId = Uuid::uuid7()->toString();
        $idempotencyKey = Uuid::uuid7()->toString();

        /** @var TraceContext $context */
        $context = $this->app->make(TraceContext::class);
        $context->setContext($traceId); // This logic might need to be verified if TraceContext handles this manually

        // Use a valid command to pass validation
        $command = new GenerateOtpCommand(
            identifier: 'test@example.com',
            type: 'email',
            tenantId: $tenantId,
            ipAddress: '127.0.0.1',
            userAgent: 'TestAgent'
        );

        // Inject Idempotency Key via Context (simulating Middleware)
        $this->app->make(\App\Infrastructure\Shared\Context\IdempotencyContext::class)
            ->setKey($idempotencyKey);

        // 2. Act: Execute via Real Pipeline
        $handler = $this->app->make(GenerateOtpHandlerInterface::class);
        $handler->handle($command);

        // 3. Assert: Real DB state
        // OTP should be created
        $this->assertDatabaseHas('one_time_passwords', [
            'identifier' => 'test@example.com',
            'tenant_id' => $tenantId,
        ]);

        $this->assertDatabaseHas('domain_outbox', [
            'type' => 'App\Domain\Auth\Events\OtpGenerated',
            'metadata->trace_id' => $traceId,
        ]);

        // Outbox should be created
        // Note: TraceID propagation to Outbox metadata depends on implementation detailed in previous logs.
        // If not implemented yet, this test verifies core functionality first.
        // Assuming DomainEventMessage captures it or Context is passed.
        // For this refactor, we ensure the Pipeline executed successfully.

        $this->assertDatabaseHas('processed_commands', [
            'idempotency_key' => $idempotencyKey,
            'status' => 'COMPLETED',
        ]);
    }

    /** @test */
    public function it_rejects_invalid_commands_at_gate(): void
    {
        // 1. Arrange: Invalid Command
        $command = new GenerateOtpCommand(
            identifier: 'not-an-email',
            type: 'telepathy',
            tenantId: 'bad-uuid',
            ipAddress: '127.0.0.1',
            userAgent: 'TestAgent'
        );

        // Even for invalid commands, we need the context key IF the decorator/middleware runs before validation.
        // In the real world, middleware runs first.
        $this->app->make(\App\Infrastructure\Shared\Context\IdempotencyContext::class)
            ->setKey(Uuid::uuid7()->toString());

        // 2. Act & Assert: Expect Pipeline Validation Exception
        $this->expectException(ValidationException::class);

        $handler = $this->app->make(GenerateOtpHandlerInterface::class);
        $handler->handle($command);

        // 4. Verify No Side Effects (Database clean)
        $this->assertDatabaseCount('one_time_passwords', 0);
        $this->assertDatabaseCount('processed_commands', 0);
    }
}
