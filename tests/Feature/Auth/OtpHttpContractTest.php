<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Application\Auth\Commands\GenerateOtpCommand;
use App\Application\Auth\Commands\VerifyOtpCommand;
use App\Application\Auth\Contracts\AuthTokenServiceInterface;
use App\Application\Auth\Contracts\GenerateOtpHandlerInterface;
use App\Application\Auth\Handlers\VerifyOtpHandlerInterface;
use App\Application\Auth\Results\VerifyOtpResult;
use App\Domain\Auth\Exceptions\InvalidOtpException;
use App\Domain\Identity\Entities\UserEntity;
use App\Domain\TenantGovernance\Entities\TenantEntity;
use App\Domain\TenantGovernance\Repositories\TenantRepositoryInterface;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Mockery\MockInterface;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

final class OtpHttpContractTest extends TestCase
{
    use WithoutMiddleware;

    public function test_generate_uses_the_current_handler_pipeline_and_returns_a_generic_accepted_response(): void
    {
        $tenantId = Uuid::uuid7()->toString();
        $idempotencyKey = Uuid::uuid7()->toString();

        $this->mock(GenerateOtpHandlerInterface::class, function (MockInterface $mock) use ($tenantId, $idempotencyKey): void {
            $mock->shouldReceive('handle')
                ->once()
                ->withArgs(function (GenerateOtpCommand $command) use ($tenantId, $idempotencyKey): bool {
                    return $command->tenantId === $tenantId
                        && $command->identifier === 'owner@example.test'
                        && $command->type === 'email'
                        && $command->idempotencyKey === $idempotencyKey;
                });
        });
        $this->mockActiveTenant($tenantId);

        $response = $this->withHeaders([
            'X-Tenant-ID' => $tenantId,
            'Idempotency-Key' => $idempotencyKey,
        ])->postJson('/api/v1/otp/generate', [
            'identifier' => 'owner@example.test',
        ]);

        $response->assertAccepted()
            ->assertExactJson([
                'message' => 'Si los datos son válidos, recibirás un código.',
                'expires_in' => 600,
            ]);
    }

    public function test_verify_uses_identifier_and_tenant_instead_of_the_removed_action_contract(): void
    {
        $tenantId = Uuid::uuid7()->toString();
        $idempotencyKey = Uuid::uuid7()->toString();

        $this->mock(VerifyOtpHandlerInterface::class, function (MockInterface $mock) use ($tenantId, $idempotencyKey): void {
            $mock->shouldReceive('handle')
                ->once()
                ->withArgs(function (VerifyOtpCommand $command) use ($tenantId, $idempotencyKey): bool {
                    return $command->tenantId === $tenantId
                        && $command->identifier === 'new@example.test'
                        && $command->code === '123456'
                        && $command->idempotencyKey === $idempotencyKey;
                })
                ->andReturn(VerifyOtpResult::forNewUser('new@example.test'));
        });
        $this->mockActiveTenant($tenantId);
        $this->mock(AuthTokenServiceInterface::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('createTokenForUser');
        });

        $response = $this->withHeaders([
            'X-Tenant-ID' => $tenantId,
            'Idempotency-Key' => $idempotencyKey,
        ])->postJson('/api/v1/otp/verify', [
            'identifier' => 'new@example.test',
            'otp_code' => '123456',
        ]);

        $response->assertOk()->assertJson([
            'message' => 'Verificación exitosa. Completa tu registro.',
            'requires_registration' => true,
            'access_token' => null,
        ]);
    }

    public function test_otp_requests_require_tenant_and_idempotency_uuid_contracts(): void
    {
        $this->postJson('/api/v1/otp/generate', [
            'identifier' => 'owner@example.test',
        ])->assertUnprocessable()->assertJsonValidationErrors(['tenant_id', 'idempotency_key']);

        $this->postJson('/api/v1/otp/verify', [
            'identifier' => 'owner@example.test',
            'otp_code' => '123456',
        ])->assertUnprocessable()->assertJsonValidationErrors(['tenant_id', 'idempotency_key']);
    }

    public function test_verify_issues_a_tenant_scoped_token_for_an_existing_user(): void
    {
        $tenantId = Uuid::uuid7()->toString();
        $userId = Uuid::uuid7()->toString();
        $idempotencyKey = Uuid::uuid7()->toString();
        $user = UserEntity::fromStateArray([
            'id' => $userId,
            'name' => 'Owner',
            'email' => 'owner@example.test',
            'password' => '',
            'role' => 'seller',
            'tenant_id' => $tenantId,
        ]);

        $this->mock(VerifyOtpHandlerInterface::class, function (MockInterface $mock) use ($user): void {
            $mock->shouldReceive('handle')->once()->andReturn(VerifyOtpResult::forUser($user));
        });
        $this->mockActiveTenant($tenantId);
        $this->mock(AuthTokenServiceInterface::class, function (MockInterface $mock) use ($userId, $tenantId): void {
            $mock->shouldReceive('createTokenForUser')
                ->once()
                ->with($userId, 'otp-api-token', $tenantId)
                ->andReturn([
                    'user' => ['id' => $userId, 'email' => 'owner@example.test'],
                    'token' => 'plain-token',
                ]);
        });

        $this->withHeaders([
            'X-Tenant-ID' => $tenantId,
            'Idempotency-Key' => $idempotencyKey,
        ])->postJson('/api/v1/otp/verify', [
            'identifier' => 'owner@example.test',
            'otp_code' => '123456',
        ])->assertOk()->assertJson([
            'access_token' => 'plain-token',
            'token_type' => 'Bearer',
            'requires_registration' => false,
        ]);
    }

    public function test_verify_does_not_reveal_whether_the_code_is_missing_invalid_or_expired(): void
    {
        $this->mock(VerifyOtpHandlerInterface::class, function (MockInterface $mock): void {
            $mock->shouldReceive('handle')->once()->andThrow(new InvalidOtpException('internal detail'));
        });
        $this->mock(AuthTokenServiceInterface::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('createTokenForUser');
        });

        $tenantId = Uuid::uuid7()->toString();
        $this->mockActiveTenant($tenantId);

        $this->withHeaders([
            'X-Tenant-ID' => $tenantId,
            'Idempotency-Key' => Uuid::uuid7()->toString(),
        ])->postJson('/api/v1/otp/verify', [
            'identifier' => 'owner@example.test',
            'otp_code' => '000000',
        ])->assertUnprocessable()->assertExactJson([
            'message' => 'El código no es válido o ya expiró.',
            'errors' => ['otp_code' => ['El código no es válido o ya expiró.']],
        ]);
    }

    public function test_generate_keeps_the_same_generic_response_for_an_unknown_tenant_without_dispatching_delivery(): void
    {
        $tenantId = Uuid::uuid7()->toString();
        $this->mock(GenerateOtpHandlerInterface::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('handle');
        });
        $this->mock(TenantRepositoryInterface::class, function (MockInterface $mock) use ($tenantId): void {
            $mock->shouldReceive('findById')->once()->with($tenantId)->andReturnNull();
        });

        $this->withHeaders([
            'X-Tenant-ID' => $tenantId,
            'Idempotency-Key' => Uuid::uuid7()->toString(),
        ])->postJson('/api/v1/otp/generate', [
            'identifier' => 'owner@example.test',
        ])->assertAccepted()->assertExactJson([
            'message' => 'Si los datos son válidos, recibirás un código.',
            'expires_in' => 600,
        ]);
    }

    private function mockActiveTenant(string $tenantId): void
    {
        $tenant = TenantEntity::fromStateArray([
            'id' => $tenantId,
            'user_id' => Uuid::uuid7()->toString(),
            'name' => 'Tenant',
            'slug' => 'tenant',
            'is_active' => true,
        ]);

        $this->mock(TenantRepositoryInterface::class, function (MockInterface $mock) use ($tenantId, $tenant): void {
            $mock->shouldReceive('findById')->once()->with($tenantId)->andReturn($tenant);
        });
    }
}
