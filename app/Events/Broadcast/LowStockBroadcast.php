<?php

declare(strict_types=1);

namespace App\Events\Broadcast;

use App\Broadcasting\ChannelRegistry;
use App\Contracts\Broadcasting\BroadcastableNotification;
use App\Enums\LowStockSeverity;
use App\Traits\LogsBroadcasts;
use DateTimeImmutable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * LowStockBroadcast - Real-Time UI Notification for Inventory Alerts
 */
final class LowStockBroadcast implements BroadcastableNotification
{
    use Dispatchable, InteractsWithSockets, LogsBroadcasts;

    private const PAYLOAD_VERSION = 1;

    // SRE: Fail-Fast Configuration
    public $tries = 1;

    public $timeout = 30;

    public function __construct(
        public int $tenantId,
        public int $productId,
        public string $productName,
        public ?string $productSku,
        public float $currentStock,
        public float $threshold,
        public LowStockSeverity $severity,
        public DateTimeImmutable $occurredAt,
    ) {}

    public static function fromDomainData(
        int $tenantId,
        int $productId,
        string $productName,
        ?string $productSku,
        float $currentStock,
        float $threshold,
        string $severity,
        DateTimeImmutable $occurredAt,
    ): self {
        return new self(
            $tenantId,
            $productId,
            $productName,
            $productSku,
            $currentStock,
            $threshold,
            LowStockSeverity::from($severity),
            $occurredAt
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
     * SECURITY: Uses isolated ADMIN channel to prevent noise/disclosure.
     */
    public function broadcastOn(): array
    {
        return [
            ChannelRegistry::tenantAdmins($this->tenantId), // 🔒 Secure Admin Channel
        ];
    }

    public function broadcastAs(): string
    {
        return 'inventory.stock.low';
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
     *   product_id: int,
     *   product_name: string,
     *   sku: string,
     *   current_stock: float,
     *   threshold: float,
     *   severity: string,
     *   tenant_id: int
     * }
     */
    public function broadcastWith(): array
    {
        return [
            // Versioning
            '_version' => self::PAYLOAD_VERSION,
            '_timestamp' => (new DateTimeImmutable)->format(DateTimeImmutable::ATOM),

            // UI Contract
            'title' => $this->severity->label(),
            'message' => $this->buildMessage(),
            'status' => 'low_stock',
            'icon' => $this->severity->icon(),

            // Domain Data
            'tenant_id' => $this->tenantId,
            'product_id' => $this->productId,
            'product_name' => $this->productName,
            'product_sku' => $this->productSku,
            'current_stock' => $this->currentStock,
            'threshold' => $this->threshold,
            'severity' => $this->severity->value,
            'severity_label' => $this->severity->label(),
            'severity_color' => $this->severity->color(),
            'occurred_at' => $this->occurredAt->format(DateTimeImmutable::ATOM),
        ];
    }

    private function buildMessage(): string
    {
        $key = $this->productSku !== null
            ? 'notifications.low_stock.message_with_sku'
            : 'notifications.low_stock.message';

        return trans_choice($key, (int) $this->currentStock, [
            'product' => $this->productName,
            'sku' => $this->productSku ?? '',
            'stock' => $this->currentStock,
            'threshold' => $this->threshold,
        ]);
    }
}
