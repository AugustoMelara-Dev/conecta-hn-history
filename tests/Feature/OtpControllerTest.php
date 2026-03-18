<?php

namespace Tests\Feature;

use App\Application\Actions\Auth\VerifyOtpAction;
use App\Application\Auth\Commands\VerifyOtpCommand;
use App\Application\Auth\Handlers\VerifyOtpHandlerInterface;
use App\Application\Auth\Results\VerifyOtpResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OtpControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_otp_generation_requires_identifier(): void
    {
        $response = $this->postJson('/api/v1/otp/generate', [], [
            'Idempotency-Key' => 'otp-generate-missing-identifier',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['identifier']);
    }

    public function test_otp_verification_works(): void
    {
        $handler = \Mockery::mock(VerifyOtpHandlerInterface::class);
        $handler->shouldReceive('handle')
            ->once()
            ->with(\Mockery::on(function (VerifyOtpCommand $command): bool {
                return $command->tenantId === 'tenant-test'
                    && $command->identifier === '550e8400-e29b-41d4-a716-446655440000'
                    && $command->code === '123456'
                    && $command->ipAddress === '127.0.0.1'
                    && $command->idempotencyKey === 'otp-verify-success';
            }))
            ->andReturn(new VerifyOtpResult(
                userId: 'user-1',
                tenantId: 'tenant-test',
                verifiedAt: new \DateTimeImmutable,
            ));
        $this->app->instance(VerifyOtpAction::class, new VerifyOtpAction($handler));

        $response = $this->postJson('/api/v1/otp/verify', [
            'tenant_id' => 'tenant-test',
            'uuid' => '550e8400-e29b-41d4-a716-446655440000',
            'otp_code' => '123456',
        ], [
            'Idempotency-Key' => 'otp-verify-success',
        ]);

        $response->assertOk()
            ->assertJson([
                'requires_registration' => false,
                'redirect' => '/dashboard',
            ]);
    }
}
