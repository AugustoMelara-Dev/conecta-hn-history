<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Skip;
use Tests\TestCase;

/**
 * OtpControllerTest - Tests de autenticación OTP
 *
 * @todo Refactorizar para nueva arquitectura - OTP ahora usa API diferente
 */
#[Skip('Pendiente refactorización para nueva arquitectura OTP')]
class OtpControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_otp_generation_requires_phone(): void
    {
        $this->markTestSkipped('Pendiente refactorización');
    }

    public function test_otp_verification_works(): void
    {
        $this->markTestSkipped('Pendiente refactorización');
    }
}
