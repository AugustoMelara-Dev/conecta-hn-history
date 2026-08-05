<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Application\Auth\Commands\VerifyOtpCommand;
use App\Application\Auth\Handlers\VerifyOtpHandlerInterface;
use App\Domain\Auth\Exceptions\InvalidOtpException;
use App\Domain\Auth\Repositories\OtpRepositoryInterface;
use App\Domain\Auth\Services\OtpHasherInterface;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

final class OtpPersistenceSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_invalid_attempt_is_committed_after_the_safe_exception_crosses_the_transaction_boundary(): void
    {
        $tenant = Tenant::factory()->create();
        $repository = app(OtpRepositoryInterface::class);
        $hasher = app(OtpHasherInterface::class);
        $repository->create(
            userId: null,
            identifier: 'owner@example.test',
            tenantId: (string) $tenant->getKey(),
            hashedCode: $hasher->hash('123456'),
            type: 'email',
            ipAddress: '203.0.113.8',
            userAgent: 'test',
            expiryMinutes: 10,
        );

        try {
            app(VerifyOtpHandlerInterface::class)->handle(new VerifyOtpCommand(
                tenantId: (string) $tenant->getKey(),
                identifier: 'owner@example.test',
                code: '000000',
                ipAddress: '203.0.113.8',
                idempotencyKey: Uuid::uuid7()->toString(),
            ));
            self::fail('Expected invalid OTP rejection.');
        } catch (InvalidOtpException) {
            self::assertSame(1, (int) DB::table('one_time_passwords')->value('attempts'));
        }
    }

    public function test_generation_reuses_one_unique_slot_per_tenant_and_identifier(): void
    {
        $tenant = Tenant::factory()->create();
        $repository = app(OtpRepositoryInterface::class);

        $first = $repository->create(null, 'owner@example.test', (string) $tenant->getKey(), 'first', 'email', null, null, 10);
        $second = $repository->create(null, 'owner@example.test', (string) $tenant->getKey(), 'second', 'email', null, null, 10);

        self::assertSame($first->id, $second->id);
        self::assertSame(1, DB::table('one_time_passwords')->count());
        self::assertSame('second', DB::table('one_time_passwords')->value('code'));
    }
}
