<?php

namespace Tests\Feature;

use App\Application\Actions\Auth\VerifyOtpAction;
use App\Application\Auth\Commands\VerifyOtpCommand;
use App\Application\Auth\Handlers\VerifyOtpHandlerInterface;
use App\Application\Auth\Results\VerifyOtpResult;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class OtpControllerTest extends TestCase
{
    use DatabaseTransactions;

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
        $handler = new class implements VerifyOtpHandlerInterface
        {
            public ?VerifyOtpCommand $command = null;

            public function handle(VerifyOtpCommand $command): VerifyOtpResult
            {
                $this->command = $command;

                return new VerifyOtpResult(
                    userId: 'user-1',
                    tenantId: 'tenant-test',
                    verifiedAt: new DateTimeImmutable,
                );
            }
        };

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
                'redirect' => '/app',
                'redirect_to' => '/app',
                'home_surface' => 'business_app',
                'audience' => 'owner',
            ]);

        $this->assertInstanceOf(VerifyOtpCommand::class, $handler->command);
        $this->assertSame('tenant-test', $handler->command->tenantId);
        $this->assertSame('550e8400-e29b-41d4-a716-446655440000', $handler->command->identifier);
        $this->assertSame('123456', $handler->command->code);
        $this->assertSame('127.0.0.1', $handler->command->ipAddress);
        $this->assertSame('otp-verify-success', $handler->command->idempotencyKey);
    }
}
