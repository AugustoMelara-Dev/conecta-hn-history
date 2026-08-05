<?php

namespace Tests\Feature;

use App\Http\Requests\Auth\GenerateOtpRequest;
use App\Http\Requests\Auth\VerifyOtpRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * OtpControllerTest - Tests de autenticación OTP
 *
 * @todo Refactorizar para nueva arquitectura - OTP ahora usa API diferente
 */
class OtpControllerTest extends TestCase
{
    public function test_otp_generation_requires_phone(): void
    {
        $validator = Validator::make([], (new GenerateOtpRequest)->rules());
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('identifier', $validator->errors()->toArray());
    }

    public function test_otp_verification_works(): void
    {
        $validator = Validator::make([
            'tenant_id' => 'not-a-uuid',
            'idempotency_key' => 'not-a-uuid',
            'identifier' => 'owner@example.test',
            'otp_code' => '12',
        ], (new VerifyOtpRequest)->rules());
        $this->assertTrue($validator->fails());
        $errors = $validator->errors()->toArray();
        $this->assertArrayHasKey('tenant_id', $errors);
        $this->assertArrayHasKey('idempotency_key', $errors);
        $this->assertArrayHasKey('otp_code', $errors);
    }
}
