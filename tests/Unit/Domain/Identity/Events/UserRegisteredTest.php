<?php

declare(strict_types=1);

use App\Domain\Identity\Events\UserRegistered;

it('serializes to payload with schema version 2', function () {
    $event = new UserRegistered(
        user_id: 'usr_123456',
        name: 'Jane Doe',
        email: new \App\Domain\Shared\ValueObjects\EncryptedEmail(base64_encode('jane@example.com')),
        role: 'user',
        tenant_id: 'tnt_987654',
        occurredOn: new \DateTimeImmutable('2026-03-04T12:00:00+00:00'),
    );

    $payload = $event->toPayload();

    expect($payload)
        ->toBeArray()
        ->toHaveKey('schema_version', 2)
        ->toHaveKey('user_id', 'usr_123456')
        ->toHaveKey('tenant_id', 'tnt_987654')
        ->and($event->schemaVersion())->toBe(2);
});

it('reconstitutes from payload successfully', function () {
    $payload = [
        'schema_version' => 2,
        'user_id' => 'usr_123456',
        'name' => 'Jane Doe',
        'email' => base64_encode('jane@example.com'),
        'role' => 'user',
        'tenant_id' => 'tnt_987654',
        'occurred_on' => '2026-03-04T12:00:00+00:00',
    ];

    $event = UserRegistered::fromPayload($payload);

    expect($event->user_id)->toBe('usr_123456')
        ->and($event->occurredOn->format(\DateTimeInterface::ATOM))->toBe('2026-03-04T12:00:00+00:00');
});

it('toPayload contains all required contract keys', function () {
    $event = UserRegistered::now(
        user_id: 'usr_abc',
        name: 'Test User',
        email: 'test@example.com',
        role: 'customer',
        tenant_id: 'tnt_xyz',
    );

    $payload = $event->toPayload();

    expect($payload)
        ->toHaveKeys(['schema_version', 'user_id', 'name', 'email', 'role', 'tenant_id', 'occurred_on'])
        ->and($payload['schema_version'])->toBeInt()->toBe(2);
});
