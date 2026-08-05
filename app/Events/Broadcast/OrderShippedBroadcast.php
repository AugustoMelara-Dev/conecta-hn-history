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
 * OrderShippedBroadcast - Real-Time UI Notification for Shipping
 */
final class OrderShippedBroadcast implements BroadcastableNotificationInterface
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
        public string $carrier,
        public string $trackingNumber,
        public ?string $estimatedDelivery,
        public DateTimeImmutable $shippedAt,
    ) {}

    public static function fromDomainData(
        string $tenantId,
        int|string $orderId,
        string $orderNumber,
        string $carrier,
        string $trackingNumber,
        ?string $estimatedDelivery,
        DateTimeImmutable $shippedAt,
    ): self {
        return new self(
            $tenantId,
            $orderId,
            $orderNumber,
            $carrier,
            $trackingNumber,
            $estimatedDelivery,
            $shippedAt
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
        return 'order.shipped';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, int|string>
     */
    public function broadcastWith(): array
    {
        return [
            // Versioning
            '_version' => self::PAYLOAD_VERSION,
            '_timestamp' => (new DateTimeImmutable)->format(DateTimeImmutable::ATOM),

            // UI Contract
            'title' => __('notifications.order_shipped.title', ['order' => $this->orderNumber]),
            'message' => __('notifications.order_shipped.message', [
                'carrier' => ucfirst($this->carrier),
                'order' => $this->orderNumber,
            ]),
            'status' => 'shipped',
            'icon' => 'heroicon-o-truck',

            // Domain Data
            'tenant_id' => $this->tenantId,
            'order_id' => $this->orderId,
            'order_number' => $this->orderNumber,
            'carrier' => $this->carrier,
            'tracking_number' => $this->trackingNumber,
            'estimated_delivery' => $this->estimatedDelivery,
            'shipped_at' => $this->shippedAt->format(DateTimeImmutable::ATOM),
        ];
    }
}
