<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Actions\Auth\GenerateOtpAction;
use App\Domain\Auth\Events\OtpGenerated;
use App\Infrastructure\Auth\Listeners\SendOtpNotificationListener;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

final class AuthOtpSmokeCommand extends Command
{
    protected $signature = 'auth:otp-smoke
        {--to= : Email address that should receive the smoke OTP}
        {--tenant-id= : Tenant UUID used to scope the OTP; defaults to the first tenant}
        {--json : Emit machine-readable JSON}';

    protected $description = 'Send a real OTP through the configured transactional email provider without exposing the code.';

    public function handle(GenerateOtpAction $generateOtp, SendOtpNotificationListener $listener): int
    {
        $to = trim((string) $this->option('to'));
        $tenantId = trim((string) $this->option('tenant-id'));
        $checks = [];
        $errors = [];

        if ($to === '' || filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
            $errors[] = 'A valid --to=email@example.com is required.';
        }

        if ((string) config('mail.default') !== 'resend') {
            $errors[] = 'MAIL_MAILER must be resend for the OTP smoke.';
        }

        if ($this->isBlankOrPlaceholder((string) config('services.resend.key', ''))) {
            $errors[] = 'RESEND_API_KEY is missing or placeholder.';
        }

        if ($this->isBlankOrPlaceholder((string) config('mail.from.address', ''))) {
            $errors[] = 'MAIL_FROM_ADDRESS is missing or placeholder.';
        }

        if ($tenantId === '') {
            $tenantId = (string) Tenant::query()->orderBy('created_at')->value('id');
        }

        if ($tenantId === '') {
            $errors[] = 'No tenant was found. Pass --tenant-id=<uuid> or seed a tenant first.';
        }

        $checks = [
            'mail_mailer' => (string) config('mail.default'),
            'mail_from_configured' => ! $this->isBlankOrPlaceholder((string) config('mail.from.address', '')),
            'resend_key_configured' => ! $this->isBlankOrPlaceholder((string) config('services.resend.key', '')),
            'tenant_id' => $tenantId !== '' ? $tenantId : null,
            'queue_connection' => (string) config('queue.default'),
        ];

        if ($errors !== []) {
            return $this->finish('failed', $checks, $errors);
        }

        $result = $generateOtp->execute(
            tenantId: $tenantId,
            identifier: $to,
            ipAddress: '127.0.0.1',
            userAgent: 'auth:otp-smoke',
            idempotencyKey: 'otp-smoke-'.Str::lower(Str::random(16)),
        );

        $otpId = is_string($result['otp_id'] ?? null) ? $result['otp_id'] : null;

        if ($otpId === null) {
            return $this->finish('failed', $checks, ['OTP was generated without a resolvable otp_id.']);
        }

        $listener->handle(OtpGenerated::now(
            otpId: $otpId,
            userId: null,
            tenantId: $tenantId,
        ));

        $checks['otp_id'] = $otpId;
        $checks['expires_at'] = (string) $result['expires_at'];

        return $this->finish('ok', $checks, []);
    }

    /**
     * @param  array<string, mixed>  $checks
     * @param  list<string>  $errors
     */
    private function finish(string $status, array $checks, array $errors): int
    {
        $payload = [
            'status' => $status,
            'checks' => $checks,
            'errors' => $errors,
        ];

        if ($this->option('json')) {
            $this->line((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } elseif ($status === 'ok') {
            $this->info('OTP smoke sent. Check the destination inbox; no OTP code was printed.');
        } else {
            $this->error('OTP smoke failed.');

            foreach ($errors as $error) {
                $this->line('- '.$error);
            }
        }

        return $status === 'ok' ? self::SUCCESS : self::FAILURE;
    }

    private function isBlankOrPlaceholder(string $value): bool
    {
        $normalized = strtolower(trim($value));

        if ($normalized === '' || $normalized === 'null') {
            return true;
        }

        foreach (['dummy', 'replace-me', 'changeme', 'placeholder', 'local_dev', 'example', 'xxxxx', '<', '>'] as $marker) {
            if (str_contains($normalized, $marker)) {
                return true;
            }
        }

        return false;
    }
}
