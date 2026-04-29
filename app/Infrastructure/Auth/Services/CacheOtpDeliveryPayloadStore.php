<?php

declare(strict_types=1);

namespace App\Infrastructure\Auth\Services;

use App\Application\Auth\Contracts\OtpDeliveryPayloadStoreInterface;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

final readonly class CacheOtpDeliveryPayloadStore implements OtpDeliveryPayloadStoreInterface
{
    private const KEY_PREFIX = 'auth:otp:delivery:';

    private const SENT_KEY_PREFIX = 'auth:otp:delivery-sent:';

    public function __construct(
        private CacheRepository $cache,
    ) {}

    public function put(
        string $otpId,
        string $plainTextCode,
        string $identifier,
        int $expiryMinutes
    ): void {
        $this->cache->put(
            $this->key($otpId),
            [
                'plain_text_code' => $plainTextCode,
                'identifier' => $identifier,
                'expiry_minutes' => $expiryMinutes,
            ],
            now()->addMinutes(max(1, $expiryMinutes))
        );
    }

    public function get(string $otpId): ?array
    {
        $payload = $this->cache->get($this->key($otpId));

        return is_array($payload) ? $payload : null;
    }

    public function forget(string $otpId): void
    {
        $this->cache->forget($this->key($otpId));
    }

    public function markSent(string $otpId, int $expiryMinutes): void
    {
        $this->cache->put(
            $this->sentKey($otpId),
            true,
            now()->addMinutes(max(10, $expiryMinutes * 2))
        );
    }

    public function wasSent(string $otpId): bool
    {
        return (bool) $this->cache->get($this->sentKey($otpId), false);
    }

    private function key(string $otpId): string
    {
        return self::KEY_PREFIX.$otpId;
    }

    private function sentKey(string $otpId): string
    {
        return self::SENT_KEY_PREFIX.$otpId;
    }
}
