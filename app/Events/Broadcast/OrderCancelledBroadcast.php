<?php

declare(strict_types=1);

namespace App\Events\Broadcast;

use App\Broadcasting\ChannelRegistry;
use App\Contracts\Broadcasting\BroadcastableNotificationInterface;
use App\Traits\LogsBroadcasts;
use DateTimeImmutable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * OrderCancelledBroadcast - Real-Time UI Notification for Order Cancellation
 */
final class OrderCancelledBroadcast implements BroadcastableNotificationInterface
{
    use Dispatchable, InteractsWithSockets, LogsBroadcasts;

    private const PAYLOAD_VERSION = 1;

    // SRE: Fail-Fast Configuration
    public $tries = 1;

    public $timeout = 30;

    public function __construct(
        public string $tenantId,
        public int|string $orderId,
        public string $orderNumber,
        public string $reason,
        public bool $shouldRefund,
        public DateTimeImmutable $cancelledAt,
    ) {}

    public static function fromDomainData(
        string $tenantId,
        int|string $orderId,
        string $orderNumber,
        string $reason,
        bool $shouldRefund,
        DateTimeImmutable $cancelledAt,
    ): self {
        return new self(
            $tenantId,
            $orderId,
            $orderNumber,
            $reason,
            $shouldRefund,
            $cancelledAt
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
     */
    public function broadcastOn(): array
    {
        return [
            ChannelRegistry::tenant($this->tenantId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'order.cancelled';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, int|string|bool>
     */
    public function broadcastWith(): array
    {
        $messageKey = $this->shouldRefund
            ? 'notifications.order_cancelled.message_with_refund'
            : 'notifications.order_cancelled.message';

        return [
            // Versioning
            '_version' => self::PAYLOAD_VERSION,
            '_timestamp' => (new DateTimeImmutable)->format(DateTimeImmutable::ATOM),

            // UI Contract
            'title' => __('notifications.order_cancelled.title'),
            'message' => __($messageKey, ['order' => $this->orderNumber]),
            'status' => 'cancelled',
            'icon' => 'x-circle',

            // Domain Data
            'tenant_id' => $this->tenantId,
            'order_id' => $this->orderId,
            'order_number' => $this->orderNumber,
            'reason' => $this->reason,
            'should_refund' => $this->shouldRefund,
            'cancelled_at' => $this->cancelledAt->format(DateTimeImmutable::ATOM),
        ];
    }
}
