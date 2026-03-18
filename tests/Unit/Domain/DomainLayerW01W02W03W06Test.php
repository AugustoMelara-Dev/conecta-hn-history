<?php

declare(strict_types=1);

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

use App\Domain\Identity\Events\UserRegistered;
use App\Domain\Identity\Events\UserRegisteredViaSocial;
use App\Domain\Sales\Events\OrderCreated;
use App\Domain\Sales\Events\OrderCancelled;
use App\Domain\TenantGovernance\Events\TenantCreated;
use App\Domain\TenantGovernance\Events\TenantProfileEnhanced;
use App\Domain\TenantGovernance\Events\TenantUpdated;
use App\Domain\Shared\Contracts\Events\DomainEvent as DomainEventAlias;

// ─── W-01 ────────────────────────────────────────────────────────────────────

it('legacy InteractionRepositoryInterface class does not exist', function () {
    expect(class_exists(\App\Domain\Interactions\Repositories\InteractionRepositoryInterface::class, false))
        ->toBeFalse();
});

it('legacy EloquentInteractionRepository class does not exist', function () {
    expect(class_exists(\App\Infrastructure\Interactions\Persistence\EloquentInteractionRepository::class, false))
        ->toBeFalse();
});

// ─── W-02 ────────────────────────────────────────────────────────────────────

it('UserRegistered has EncryptedEmail property email', function () {
    $reflection = new ReflectionClass(UserRegistered::class);
    $prop = $reflection->getProperty('email');
    expect($prop->getType()->getName())->toBe(\App\Domain\Shared\ValueObjects\EncryptedEmail::class);
});

it('UserRegistered SCHEMA_VERSION is 2', function () {
    $reflection = new ReflectionClass(UserRegistered::class);
    $const = $reflection->getConstant('SCHEMA_VERSION');
    expect($const)->toBe(2);
});

it('UserRegisteredViaSocial has EncryptedEmail property email', function () {
    $reflection = new ReflectionClass(UserRegisteredViaSocial::class);
    $prop = $reflection->getProperty('email');
    expect($prop->getType()->getName())->toBe(\App\Domain\Shared\ValueObjects\EncryptedEmail::class);
});

it('UserRegisteredViaSocial SCHEMA_VERSION is 2', function () {
    $reflection = new ReflectionClass(UserRegisteredViaSocial::class);
    $const = $reflection->getConstant('SCHEMA_VERSION');
    expect($const)->toBe(2);
});

it('UserRegistered fromPayload parses EncryptedEmail correctly', function () {
    $v2Payload = [
        'user_id'       => 'usr-1',
        'name'          => 'Test',
        'email'         => base64_encode('test@example.com'), // fake encrypted payload
        'role'          => 'customer',
        'tenant_id'     => '00000000-0000-0000-0000-000000000001',
        'occurred_on'   => '2026-01-01T00:00:00+00:00',
        'schema_version' => 2,
    ];
    $event = UserRegistered::fromPayload($v2Payload);
    expect($event->email->value())->toBe(base64_encode('test@example.com'));
});

it('UserRegistered now() encrypts email', function () {
    $event = UserRegistered::now('u1', 'Alice', 'alice@test.com', 'customer', 'tenant-1');
    expect($event->email)->toBeInstanceOf(\App\Domain\Shared\ValueObjects\EncryptedEmail::class);
});

it('UserRegisteredViaSocial now() encrypts email', function () {
    $event = UserRegisteredViaSocial::now('u1', 'tenant-1', 'bob@test.com', 'google');
    expect($event->email)->toBeInstanceOf(\App\Domain\Shared\ValueObjects\EncryptedEmail::class);
});

// ─── W-03 ────────────────────────────────────────────────────────────────────

it('OrderCreated has schemaVersion method returning 1', function () {
    $reflection = new ReflectionClass(OrderCreated::class);
    expect($reflection->hasMethod('schemaVersion'))->toBeTrue();
    expect($reflection->getConstant('SCHEMA_VERSION'))->toBe(1);
});

it('OrderCancelled has schemaVersion method returning 1', function () {
    $reflection = new ReflectionClass(OrderCancelled::class);
    expect($reflection->hasMethod('schemaVersion'))->toBeTrue();
    expect($reflection->getConstant('SCHEMA_VERSION'))->toBe(1);
});

it('TenantCreated has schemaVersion method returning 1', function () {
    $reflection = new ReflectionClass(TenantCreated::class);
    expect($reflection->hasMethod('schemaVersion'))->toBeTrue();
    expect($reflection->getConstant('SCHEMA_VERSION'))->toBe(1);
});

it('TenantProfileEnhanced has schemaVersion method returning 1', function () {
    $reflection = new ReflectionClass(TenantProfileEnhanced::class);
    expect($reflection->hasMethod('schemaVersion'))->toBeTrue();
    expect($reflection->getConstant('SCHEMA_VERSION'))->toBe(1);
});

it('TenantUpdated has schemaVersion method returning 1', function () {
    $reflection = new ReflectionClass(TenantUpdated::class);
    expect($reflection->hasMethod('schemaVersion'))->toBeTrue();
    expect($reflection->getConstant('SCHEMA_VERSION'))->toBe(1);
});

it('OrderCreated toPayload includes schema_version key', function () {
    $event = OrderCreated::fromPayload([
        'order_id' => 1, 'tenant_id' => 'ten-1', 'order_number' => 'ORD-001',
        'customer_name' => 'Alice', 'total' => 100, 'status' => 'pending',
        'event_id' => null, 'occurred_at' => null, 'correlation_id' => null,
    ]);
    expect($event->toPayload())->toHaveKey('schema_version');
    expect($event->toPayload()['schema_version'])->toBe(1);
});

// ─── W-06 ────────────────────────────────────────────────────────────────────

it('DomainEvent alias interface exists in Domain\Shared namespace', function () {
    expect(interface_exists(DomainEventAlias::class))->toBeTrue();
});

it('HasDomainMetadata trait exists in Domain\Shared\Traits', function () {
    expect(trait_exists(\App\Domain\Shared\Traits\HasDomainMetadata::class))->toBeTrue();
});

it('HasDomainMetadata still exists in App\Traits for backward compat', function () {
    expect(trait_exists(\App\Traits\HasDomainMetadata::class))->toBeTrue();
});