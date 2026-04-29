<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Auth;

use App\Application\Actions\Auth\GenerateOtpAction;
use App\Application\Auth\Commands\GenerateOtpCommand;
use App\Application\Auth\Contracts\GenerateOtpHandlerInterface;
use App\Domain\Auth\Entities\OtpEntity;
use App\Domain\Auth\Events\OtpGenerated;
use App\Domain\Auth\Repositories\OtpRepositoryInterface;
use DateTimeImmutable;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

final class GenerateOtpActionTest extends TestCase
{
    public function test_it_dispatches_otp_generated_for_immediate_delivery_after_persistence(): void
    {
        Event::fake();

        $handler = new RecordingGenerateOtpHandler;
        $repository = new FixedOtpRepository(
            new OtpEntity(
                id: 'otp-123',
                userId: null,
                identifier: 'cliente@example.com',
                tenantId: 'tenant-123',
                hashedCode: 'hashed',
                type: 'email',
                expiresAt: new DateTimeImmutable('+10 minutes'),
                attempts: 0,
            )
        );

        $action = new GenerateOtpAction($handler, $repository);

        $result = $action->execute(
            tenantId: 'tenant-123',
            identifier: 'cliente@example.com',
            ipAddress: '127.0.0.1',
            userAgent: 'PHPUnit',
            idempotencyKey: 'otp-action-test',
        );

        $this->assertSame('otp-123', $result['otp_id']);
        $this->assertSame('cliente@example.com', $handler->command?->identifier);
        $this->assertSame('email', $handler->command?->type);

        Event::assertDispatched(
            OtpGenerated::class,
            fn (OtpGenerated $event): bool => $event->otpId === 'otp-123'
                && $event->tenantId === 'tenant-123'
                && $event->userId === null
        );
    }
}

final class RecordingGenerateOtpHandler implements GenerateOtpHandlerInterface
{
    public ?GenerateOtpCommand $command = null;

    public function handle(GenerateOtpCommand $command): void
    {
        $this->command = $command;
    }
}

final class FixedOtpRepository implements OtpRepositoryInterface
{
    public function __construct(private readonly ?OtpEntity $otp) {}

    public function findValidByIdentifier(string $identifier, string $tenantId): ?OtpEntity
    {
        return $this->otp;
    }

    public function create(
        ?string $userId,
        string $identifier,
        string $tenantId,
        string $hashedCode,
        string $type,
        ?string $ipAddress,
        ?string $userAgent,
        int $expiryMinutes
    ): OtpEntity {
        throw new \BadMethodCallException('Not used in this test.');
    }

    public function incrementAttempts(string $otpId): void {}

    public function incrementAttemptsAtomically(string $otpId, int $maxAttempts): void {}

    public function invalidateByIdentifier(string $identifier, string $tenantId): void {}

    public function delete(OtpEntity $otp): void {}

    public function save(OtpEntity $otp): void {}
}
