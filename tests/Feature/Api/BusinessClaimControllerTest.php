<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Application\Actions\Auth\VerifyOtpAction;
use App\Application\Auth\Commands\VerifyOtpCommand;
use App\Application\Auth\Handlers\VerifyOtpHandlerInterface;
use App\Application\Auth\Results\VerifyOtpResult;
use App\Domain\Auth\Exceptions\InvalidOtpException;
use App\Domain\Identity\Enums\UserRole;
use App\Models\Tenant;
use App\Models\TenantBusinessClaim;
use App\Models\User;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

final class BusinessClaimControllerTest extends TestCase
{
    use DatabaseTransactions;

    public function test_owner_submit_requires_valid_whatsapp_otp(): void
    {
        $tenant = Tenant::factory()->create([
            'verification_status' => Tenant::VERIFICATION_STATUS_PROVISIONAL,
            'verified_at' => null,
            'whatsapp_number' => '+50494445555',
        ]);
        $owner = User::factory()->create([
            'role' => UserRole::TENANT_OWNER,
            'tenant_id' => $tenant->getKey(),
        ]);

        $tenant->forceFill(['user_id' => $owner->getKey()])->save();

        $handler = new class implements VerifyOtpHandlerInterface
        {
            public ?VerifyOtpCommand $command = null;

            public function handle(VerifyOtpCommand $command): VerifyOtpResult
            {
                $this->command = $command;

                return new VerifyOtpResult(
                    userId: null,
                    tenantId: null,
                    verifiedAt: new DateTimeImmutable,
                    requiresRegistration: true
                );
            }
        };

        $this->app->instance(VerifyOtpAction::class, new VerifyOtpAction($handler));

        $response = $this->actingAs($owner, 'sanctum')->postJson("/api/v1/business-claims/{$tenant->slug}/submit", [
            'whatsapp_otp_code' => '123456',
            'evidence_type' => 'website',
            'evidence_url' => 'https://example.com/negocio',
        ], [
            'Idempotency-Key' => 'claim-owner-submit-success',
        ]);

        $response->assertStatus(202)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.tenant_verification_status', Tenant::VERIFICATION_STATUS_PROVISIONAL);

        $this->assertInstanceOf(VerifyOtpCommand::class, $handler->command);
        $this->assertSame((string) $tenant->getKey(), $handler->command->tenantId);
        $this->assertSame('+50494445555', $handler->command->identifier);
        $this->assertSame('123456', $handler->command->code);

        $claim = TenantBusinessClaim::query()
            ->where('tenant_id', (string) $tenant->getKey())
            ->first();

        $this->assertInstanceOf(TenantBusinessClaim::class, $claim);
        $this->assertSame('pending', $claim->status);
        $this->assertNotNull($claim->whatsapp_otp_verified_at);
    }

    public function test_owner_submit_returns_422_when_whatsapp_otp_is_invalid(): void
    {
        $tenant = Tenant::factory()->create([
            'verification_status' => Tenant::VERIFICATION_STATUS_PROVISIONAL,
            'verified_at' => null,
        ]);
        $owner = User::factory()->create([
            'role' => UserRole::TENANT_OWNER,
            'tenant_id' => $tenant->getKey(),
        ]);

        $tenant->forceFill([
            'user_id' => $owner->getKey(),
            'whatsapp_number' => '+50493334444',
        ])->save();

        $handler = new class implements VerifyOtpHandlerInterface
        {
            public function handle(VerifyOtpCommand $command): VerifyOtpResult
            {
                throw new InvalidOtpException('Codigo incorrecto.');
            }
        };

        $this->app->instance(VerifyOtpAction::class, new VerifyOtpAction($handler));

        $this->actingAs($owner, 'sanctum')->postJson("/api/v1/business-claims/{$tenant->slug}/submit", [
            'whatsapp_otp_code' => '999999',
            'evidence_type' => 'website',
            'evidence_url' => 'https://example.com/claim',
        ])->assertStatus(422)
            ->assertJsonPath('error_code', 'BUSINESS_CLAIM_WHATSAPP_OTP_INVALID');

        $this->assertDatabaseMissing('tenant_business_claims', [
            'tenant_id' => (string) $tenant->getKey(),
            'user_id' => (string) $owner->getKey(),
            'evidence_type' => 'website',
        ]);
    }

    public function test_non_owner_cannot_submit_business_claim_even_with_otp_payload(): void
    {
        $tenant = Tenant::factory()->create([
            'verification_status' => Tenant::VERIFICATION_STATUS_PROVISIONAL,
            'verified_at' => null,
            'whatsapp_number' => '+50492223333',
        ]);
        $owner = User::factory()->create([
            'role' => UserRole::TENANT_OWNER,
            'tenant_id' => $tenant->getKey(),
        ]);
        $customer = User::factory()->create([
            'role' => UserRole::CUSTOMER,
        ]);

        $tenant->forceFill(['user_id' => $owner->getKey()])->save();

        $this->actingAs($customer, 'sanctum')->postJson("/api/v1/business-claims/{$tenant->slug}/submit", [
            'whatsapp_otp_code' => '123456',
            'evidence_type' => 'social',
            'evidence_url' => 'https://instagram.com/negocio',
        ])->assertForbidden()
            ->assertJsonPath('error_code', 'BUSINESS_CLAIM_FORBIDDEN');
    }
}
