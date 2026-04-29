<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Mail\OtpMail;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

final class AuthOtpSmokeCommandTest extends TestCase
{
    use DatabaseTransactions;

    public function test_it_sends_an_otp_smoke_without_printing_the_code(): void
    {
        Mail::fake();

        config([
            'mail.default' => 'resend',
            'mail.from.address' => 'no-reply@mail.vitobusinessos.test',
            'services.resend.key' => 're_rotated_smoke_test_key',
            'queue.default' => 'sync',
        ]);

        $tenant = Tenant::factory()->create();

        $exitCode = Artisan::call('auth:otp-smoke', [
            '--to' => 'smoke@example.com',
            '--tenant-id' => (string) $tenant->getKey(),
            '--json' => true,
        ]);

        $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(0, $exitCode);
        $this->assertSame('ok', $payload['status']);
        $this->assertArrayHasKey('otp_id', $payload['checks']);
        $this->assertDoesNotMatchRegularExpression('/\b\d{6}\b/', Artisan::output());

        Mail::assertSent(OtpMail::class, function (OtpMail $mail): bool {
            return $mail->hasTo('smoke@example.com');
        });
    }
}
