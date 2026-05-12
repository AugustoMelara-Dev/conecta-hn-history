<?php

declare(strict_types=1);

namespace App\Events\Broadcast;

use App\Broadcasting\ChannelRegistry;
use App\Contracts\Broadcasting\BroadcastableNotificationInterface;
use App\Traits\FormatsCurrency;
use App\Traits\LogsBroadcasts;
use DateTimeImmutable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * OrderPaidBroadcast - Real-Time UI Notification for Payment Confirmation
 */
final class OrderPaidBroadcast implements BroadcastableNotificationInterface
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
        public int $amountCents,
        public string $currency,
        public string $paymentMethod,
        public DateTimeImmutable $paidAt,
    ) {}

    public static function fromDomainData(
        int $tenantId,
        int $orderId,
        string $orderNumber,
        int $amountCents,
        string $currency,
        string $paymentMethod,
        DateTimeImmutable $paidAt,
    ): self {
        return new self(
            $tenantId,
            $orderId,
            $orderNumber,
            $amountCents,
            $currency,
            $paymentMethod,
            $paidAt
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
     * @return array<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [ChannelRegistry::tenant($this->tenantId)];
    }

    public function broadcastAs(): string
    {
        return 'order.paid';
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
     *   payment_method: string,
     *   tenant_id: int
     * }
     */
    public function broadcastWith(): array
    {
        $this->logBroadcast($this->broadcastAs(), [
            'tenant_id' => $this->tenantId,
            'order_id' => $this->orderId,
            'amount_cents' => $this->amountCents,
        ]);

        return [
            // Versioning
            '_version' => self::PAYLOAD_VERSION,
            '_timestamp' => (new DateTimeImmutable)->format(DateTimeImmutable::ATOM),

            // UI Contract
            'title' => __('notifications.order_paid.title', ['order' => $this->orderNumber]),
            'message' => __('notifications.order_paid.message', [
                'amount' => $this->formatMoney($this->amountCents, $this->currency),
                'order' => $this->orderNumber,
            ]),
            'status' => 'paid',
            'icon' => 'check-circle',

            // Domain Data
            'tenant_id' => $this->tenantId,
            'order_id' => $this->orderId,
            'order_number' => $this->orderNumber,
            'amount_formatted' => $this->formatMoney($this->amountCents, $this->currency),
            'payment_method' => $this->paymentMethod,
            'paid_at' => $this->paidAt->format(DateTimeImmutable::ATOM),
        ];
    }
}
