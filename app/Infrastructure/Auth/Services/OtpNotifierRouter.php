<?php

declare(strict_types=1);

namespace App\Infrastructure\Auth\Services;

use App\Domain\Auth\Contracts\OtpNotifierInterface;
use App\Domain\Identity\Entities\UserEntity;
use App\Infrastructure\Notifications\EmailOtpNotifier;
use App\Infrastructure\Notifications\SmsOtpNotifier;

final readonly class OtpNotifierRouter implements OtpNotifierInterface
{
    public function __construct(
        private EmailOtpNotifier $emailNotifier,
        private SmsOtpNotifier $smsNotifier,
    ) {}

    public function send(
        ?UserEntity $recipient,
        string $plainTextCode,
        string $identifier,
        string $tenantId,
        int $expiryMinutes,
        ?string $magicUrl = null
    ): void {
        $notifier = str_contains($identifier, '@')
            ? $this->emailNotifier
            : $this->smsNotifier;

        $notifier->send($recipient, $plainTextCode, $identifier, $tenantId, $expiryMinutes, $magicUrl);
    }
}
