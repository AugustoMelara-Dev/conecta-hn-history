<?php

declare(strict_types=1);

namespace App\Events\Broadcast;

use App\Broadcasting\ChannelRegistry;
use App\Contracts\Broadcasting\BroadcastableNotificationInterface;
use App\Traits\LogsBroadcasts;
use App\Traits\ParsesUserAgent;
use DateTimeImmutable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * NewLoginDetectedBroadcast - Real-Time Security Alert for New Logins
 */
final class NewLoginDetectedBroadcast implements BroadcastableNotificationInterface
{
    use Dispatchable, InteractsWithSockets, LogsBroadcasts, ParsesUserAgent;

    private const PAYLOAD_VERSION = 1;

    // SRE: Fail-Fast Configuration
    public $tries = 1;

    public $timeout = 30;

    public function __construct(
        public int|string $userId,
        public string $tenantId,
        public string $userEmail,
        public string $ipAddress,
        public ?string $userAgent,
        public DateTimeImmutable $loginAt
    ) {}

    public static function fromDomainData(
        int|string $userId,
        string $tenantId,
        string $userEmail,
        string $ipAddress,
        ?string $userAgent,
        DateTimeImmutable $loginAt
    ): self {
        return new self(
            $userId,
            $tenantId,
            $userEmail,
            $ipAddress,
            $userAgent,
            $loginAt
        );
    }

    /**
     * SRE: Expiration Strategy - Discard stale events > 60s old
     */
    public function retryUntil(): \DateTimeImmutable
    {
        return now()->addSeconds(60)->toImmutable();
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * SECURITY: Uses isolated ADMIN channel to prevent information disclosure.
     */
    public function broadcastOn(): array
    {
        return [
            ChannelRegistry::tenantAdmins($this->tenantId), // 🔒 Secure Admin Channel
            ChannelRegistry::user($this->userId),           // 👤 Personal user notification
        ];
    }

    public function broadcastAs(): string
    {
        return 'security.login.new';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, int|string|null>
     */
    public function broadcastWith(): array
    {
        $device = $this->parseUserAgent($this->userAgent);
        $formattedTime = $this->loginAt->format('H:i');

        $messageKey = $device !== null
            ? 'notifications.new_login.message_with_device'
            : 'notifications.new_login.message';

        return [
            // Versioning
            '_version' => self::PAYLOAD_VERSION,
            '_timestamp' => (new DateTimeImmutable)->format(DateTimeImmutable::ATOM),

            // UI Contract
            'title' => __('notifications.new_login.title'),
            'message' => __($messageKey, [
                'device' => $device,
                'ip' => $this->ipAddress,
                'time' => $formattedTime,
            ]),
            'status' => 'security_alert',
            'icon' => 'shield-alert',

            // Domain Data
            'tenant_id' => $this->tenantId,
            'user_id' => $this->userId,
            'user_email' => $this->userEmail,
            'ip_address' => $this->ipAddress,
            'device' => $device,
            'time' => $formattedTime,
            'login_at' => $this->loginAt->format(DateTimeImmutable::ATOM),
        ];
    }
}
