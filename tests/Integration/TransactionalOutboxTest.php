<?php

declare(strict_types=1);

/**
 * TransactionalOutboxTest - Integration Verification
 *
 * Validates:
 * - Outbox entries are persisted within same transaction as command execution
 * - SensitiveDataInterface properties are redacted in stored payload
 * - Status transitions PENDING → PUBLISHED on success
 * - Transaction rolls back on handler failure (no Ghost Events)
 */

use App\Application\Shared\Contracts\CommandBusInterface;
use App\Application\Shared\Contracts\SensitiveDataInterface;
use App\Infrastructure\Bus\TransactionalCommandBus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    DB::table('outbox_messages')->delete();
});

test('transactional bus persists redacted payload and marks as published', function () {
    // Arrange: Mock the inner dispatcher to simulate successful handler execution
    $mockDispatcher = Mockery::mock(\Illuminate\Contracts\Bus\Dispatcher::class);
    $mockDispatcher->shouldReceive('dispatchSync')->once()->andReturn('handler-result');

    $bus = new TransactionalCommandBus(
        $mockDispatcher,
        app('db.connection')
    );

    // Create a command implementing SensitiveDataInterface
    $command = new class('secret-password-123', 'visible-data') implements SensitiveDataInterface {
        public function __construct(
            public string $secret,
            public string $publicField,
        ) {}

        public function getSensitiveProperties(): array
        {
            return ['secret'];
        }
    };

    // Act
    $result = $bus->dispatch($command);

    // Assert: Handler result is returned correctly
    expect($result)->toBe('handler-result');

    // Assert: Outbox entry exists with PUBLISHED status
    $entry = DB::table('outbox_messages')->first();
    expect($entry)->not->toBeNull();
    expect($entry->status)->toBe('PUBLISHED');
    expect($entry->published_at)->not->toBeNull();

    // Assert: Payload is redacted
    $payload = json_decode($entry->payload, true);
    expect($payload['secret'])->toBe('[REDACTED]');
    expect($payload['publicField'])->toBe('visible-data');

    // Assert: Type is recorded correctly
    expect($entry->type)->toBe(get_class($command));
});

test('transactional bus rolls back outbox on handler failure', function () {
    // Arrange: Mock dispatcher to throw an exception
    $mockDispatcher = Mockery::mock(\Illuminate\Contracts\Bus\Dispatcher::class);
    $mockDispatcher->shouldReceive('dispatchSync')->once()->andThrow(
        new \RuntimeException('Handler exploded')
    );

    $bus = new TransactionalCommandBus(
        $mockDispatcher,
        app('db.connection')
    );

    $command = new class('data') {
        public function __construct(public string $value) {}
    };

    // Act & Assert: Exception propagates
    $thrown = false;
    try {
        $bus->dispatch($command);
    } catch (\RuntimeException $e) {
        $thrown = true;
        expect($e->getMessage())->toBe('Handler exploded');
    }
    expect($thrown)->toBeTrue();

    // Assert: No Ghost Event — outbox rolled back
    $count = DB::table('outbox_messages')->count();
    expect($count)->toBe(0, 'Outbox must roll back on handler failure');
});

test('transactional bus persists non-sensitive command without redaction', function () {
    // Arrange
    $mockDispatcher = Mockery::mock(\Illuminate\Contracts\Bus\Dispatcher::class);
    $mockDispatcher->shouldReceive('dispatchSync')->once()->andReturn(42);

    $bus = new TransactionalCommandBus(
        $mockDispatcher,
        app('db.connection')
    );

    $command = new class('hello', 123) {
        public function __construct(
            public string $name,
            public int $count,
        ) {}
    };

    // Act
    $result = $bus->dispatch($command);

    // Assert
    expect($result)->toBe(42);

    $entry = DB::table('outbox_messages')->first();
    $payload = json_decode($entry->payload, true);
    expect($payload['name'])->toBe('hello');
    expect($payload['count'])->toBe(123);
    expect($entry->status)->toBe('PUBLISHED');
});
