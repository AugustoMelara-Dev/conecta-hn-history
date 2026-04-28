<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Application\Shared\Contracts\IdGeneratorInterface;
use App\Application\Shared\Contracts\SensitiveDataInterface;
use App\Infrastructure\Bus\TransactionalCommandBus;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class TransactionalOutboxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('outbox_messages')->delete();
    }

    #[Test]
    public function transactional_bus_persists_redacted_payload_and_marks_as_published(): void
    {
        $dispatcher = new TransactionalOutboxFakeDispatcher('handler-result');
        $bus = new TransactionalCommandBus(
            $dispatcher,
            app('db.connection'),
            new FixedOutboxIdGenerator('test-uuid-001'),
        );

        $command = new class('secret-password-123', 'visible-data') implements SensitiveDataInterface
        {
            public function __construct(
                public string $secret,
                public string $publicField,
            ) {}

            public function getSensitiveProperties(): array
            {
                return ['secret'];
            }
        };

        $result = $bus->dispatch($command);

        self::assertSame('handler-result', $result);
        self::assertSame($command, $dispatcher->receivedCommand);

        $entry = DB::table('outbox_messages')->first();
        self::assertNotNull($entry);
        self::assertSame('PUBLISHED', $entry->status);
        self::assertNotNull($entry->published_at);

        /** @var array{secret: string, publicField: string} $payload */
        $payload = json_decode((string) $entry->payload, true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('[REDACTED]', $payload['secret']);
        self::assertSame('visible-data', $payload['publicField']);
        self::assertSame(get_class($command), $entry->type);
    }

    #[Test]
    public function transactional_bus_rolls_back_outbox_on_handler_failure(): void
    {
        $dispatcher = new TransactionalOutboxFakeDispatcher(
            result: null,
            exception: new \RuntimeException('Handler exploded'),
        );
        $bus = new TransactionalCommandBus(
            $dispatcher,
            app('db.connection'),
            new FixedOutboxIdGenerator('test-uuid-002'),
        );

        $command = new class('data')
        {
            public function __construct(public string $value) {}
        };

        try {
            $bus->dispatch($command);
            self::fail('Expected RuntimeException was not thrown.');
        } catch (\RuntimeException $e) {
            self::assertSame('Handler exploded', $e->getMessage());
        }

        self::assertSame(0, DB::table('outbox_messages')->count());
    }

    #[Test]
    public function transactional_bus_persists_non_sensitive_command_without_redaction(): void
    {
        $dispatcher = new TransactionalOutboxFakeDispatcher(42);
        $bus = new TransactionalCommandBus(
            $dispatcher,
            app('db.connection'),
            new FixedOutboxIdGenerator('test-uuid-003'),
        );

        $command = new class('hello', 123)
        {
            public function __construct(
                public string $name,
                public int $count,
            ) {}
        };

        $result = $bus->dispatch($command);

        self::assertSame(42, $result);

        $entry = DB::table('outbox_messages')->first();
        self::assertNotNull($entry);

        /** @var array{name: string, count: int} $payload */
        $payload = json_decode((string) $entry->payload, true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('hello', $payload['name']);
        self::assertSame(123, $payload['count']);
        self::assertSame('PUBLISHED', $entry->status);
    }
}

final class TransactionalOutboxFakeDispatcher implements Dispatcher
{
    public mixed $receivedCommand = null;

    public function __construct(
        private readonly mixed $result,
        private readonly ?\Throwable $exception = null,
    ) {}

    public function dispatch($command)
    {
        return $this->dispatchSync($command);
    }

    public function dispatchSync($command, $handler = null)
    {
        $this->receivedCommand = $command;

        if ($this->exception instanceof \Throwable) {
            throw $this->exception;
        }

        return $this->result;
    }

    public function dispatchNow($command, $handler = null)
    {
        return $this->dispatchSync($command, $handler);
    }

    public function hasCommandHandler($command)
    {
        return true;
    }

    public function getCommandHandler($command)
    {
        return null;
    }

    public function pipeThrough(array $pipes)
    {
        return $this;
    }

    public function map(array $map)
    {
        return $this;
    }
}

final class FixedOutboxIdGenerator implements IdGeneratorInterface
{
    public function __construct(private readonly string $id) {}

    public function generate(): string
    {
        return $this->id;
    }
}
