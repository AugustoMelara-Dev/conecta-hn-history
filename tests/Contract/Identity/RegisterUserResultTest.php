<?php

declare(strict_types=1);

namespace Tests\Contract\Identity;

use App\Application\Identity\Results\RegisterUserResult;
use DateTimeImmutable;
use Tests\TestCase;

final class RegisterUserResultTest extends TestCase
{
    public function test_register_result_serialization_contract(): void
    {
        $registeredAt = new DateTimeImmutable('2026-01-15T22:00:00+00:00');

        $result = new RegisterUserResult(
            id: '01942f8a-1234-7890-abcd-ef1234567890',
            tenantId: '01942f8b-5678-7890-abcd-ef1234567890',
            email: 'test@example.com',
            registeredAt: $registeredAt,
            requiresVerification: true,
        );

        /** @var array{id: string, tenantId: string, email: string, registeredAt: array{date: string, timezone_type: int, timezone: string}, requiresVerification: bool} $json */
        $json = json_decode(json_encode($result, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);

        self::assertArrayHasKey('id', $json);
        self::assertArrayHasKey('tenantId', $json);
        self::assertArrayHasKey('email', $json);
        self::assertArrayHasKey('registeredAt', $json);
        self::assertArrayHasKey('requiresVerification', $json);

        self::assertSame('01942f8a-1234-7890-abcd-ef1234567890', $json['id']);
        self::assertSame('01942f8b-5678-7890-abcd-ef1234567890', $json['tenantId']);
        self::assertSame('test@example.com', $json['email']);
        self::assertTrue($json['requiresVerification']);
        self::assertSame('2026-01-15 22:00:00.000000', $json['registeredAt']['date']);
        self::assertSame(1, $json['registeredAt']['timezone_type']);
        self::assertSame('+00:00', $json['registeredAt']['timezone']);
    }

    public function test_register_result_exposes_current_contract_properties(): void
    {
        $registeredAt = new DateTimeImmutable('2026-01-15T22:00:00+00:00');

        $result = new RegisterUserResult(
            id: '01942f8a-1234-7890-abcd-ef1234567890',
            tenantId: '01942f8b-5678-7890-abcd-ef1234567890',
            email: 'test@example.com',
            registeredAt: $registeredAt,
            requiresVerification: true,
        );

        self::assertSame('01942f8a-1234-7890-abcd-ef1234567890', $result->id);
        self::assertSame('01942f8b-5678-7890-abcd-ef1234567890', $result->tenantId);
        self::assertSame('test@example.com', $result->email);
        self::assertSame($registeredAt, $result->registeredAt);
        self::assertTrue($result->requiresVerification);
    }

    public function test_register_result_can_be_reconstructed_from_serialized_payload(): void
    {
        $registeredAt = new DateTimeImmutable('2026-01-15T22:00:00+00:00');
        $original = new RegisterUserResult(
            id: '01942f8a-1234-7890-abcd-ef1234567890',
            tenantId: '01942f8b-5678-7890-abcd-ef1234567890',
            email: 'hydrated@example.com',
            registeredAt: $registeredAt,
            requiresVerification: false,
        );

        /** @var array{id: string, tenantId: string, email: string, registeredAt: array{date: string, timezone_type: int, timezone: string}, requiresVerification: bool} $payload */
        $payload = json_decode(json_encode($original, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);

        $reconstituted = new RegisterUserResult(
            id: $payload['id'],
            tenantId: $payload['tenantId'],
            email: $payload['email'],
            registeredAt: new DateTimeImmutable($payload['registeredAt']['date']),
            requiresVerification: $payload['requiresVerification'],
        );

        self::assertSame($original->id, $reconstituted->id);
        self::assertSame($original->tenantId, $reconstituted->tenantId);
        self::assertSame($original->email, $reconstituted->email);
        self::assertSame(
            $original->registeredAt->format(DateTimeImmutable::ATOM),
            $reconstituted->registeredAt->format(DateTimeImmutable::ATOM),
        );
        self::assertFalse($reconstituted->requiresVerification);
    }
}
