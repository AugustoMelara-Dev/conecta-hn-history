<?php

declare(strict_types=1);

namespace App\Events\Broadcast;

use App\Broadcasting\ChannelRegistry;
use App\Contracts\Broadcasting\BroadcastableNotificationInterface;
use App\Traits\FormatsCurrency;
use App\Traits\LogsBroadcasts;
use DateTimeImmutable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * OrderRefundedBroadcast - Real-Time UI Notification for Refund Updates
 */
final class OrderRefundedBroadcast implements BroadcastableNotificationInterface
{
    use Dispatchable, FormatsCurrency, InteractsWithSockets, LogsBroadcasts;

    private const PAYLOAD_VERSION = 1;

    // SRE: Fail-Fast Configuration
    public $tries = 1;

    public $timeout = 30;

    public function __construct(
        public int $tenantId,
        public int $orderId,
        public string $orderNumber,
        public int $refundAmountCents,
        public string $currency,
        public bool $isPartial,
        public ?string $reason,
        public DateTimeImmutable $refundedAt,
    ) {}

    public static function fromDomainData(
        int $tenantId,
        int $orderId,
        string $orderNumber,
        int $refundAmountCents,
        string $currency,
        bool $isPartial,
        ?string $reason,
        DateTimeImmutable $refundedAt,
    ): self {
        return new self(
            $tenantId,
            $orderId,
            $orderNumber,
            $refundAmountCents,
            $currency,
            $isPartial,
            $reason,
            $refundedAt
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
        return 'order.refunded';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array{
     *   _version: int,
     *   _timestamp: string,
     *   title: string,
     *   message: string,
     *   status: string,
     *   icon: string,
     *   order_id: int,
     *   order_number: string,
     *   amount: string,
     *   reason: string,
     *   tenant_id: int
     * }
     */
    public function broadcastWith(): array
    {
        $formattedAmount = $this->formatMoney($this->refundAmountCents, $this->currency);
        $messageKey = $this->isPartial
            ? 'notifications.order_refunded.message_partial'
            : 'notifications.order_refunded.message_full';

        return [
            // Versioning
            '_version' => self::PAYLOAD_VERSION,
            '_timestamp' => (new DateTimeImmutable)->format(DateTimeImmutable::ATOM),

            // UI Contract
            'title' => __('notifications.order_refunded.title', ['order' => $this->orderNumber]),
            'message' => __($messageKey, [
                'amount' => $formattedAmount,
                'order' => $this->orderNumber,
            ]),
            'status' => 'refunded',
            'icon' => 'heroicon-o-arrow-path',

            // Domain Data
            'tenant_id' => $this->tenantId,
            'order_id' => $this->orderId,
            'order_number' => $this->orderNumber,
            'refund_amount_formatted' => $formattedAmount,
            'is_partial' => $this->isPartial,
            'reason' => $this->reason,
            'refunded_at' => $this->refundedAt->format(DateTimeImmutable::ATOM),
        ];
    }
}
