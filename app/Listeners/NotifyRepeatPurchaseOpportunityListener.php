<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Domain\Contracts\AdminNotifier;
use App\Domain\TenantGovernance\Services\OperationalPlaybookTaskManager;
use App\Events\Order\OrderDelivered;
use App\Models\Order;
use App\Models\Tenant;

final class NotifyRepeatPurchaseOpportunityListener
{
    public function __construct(
        private readonly AdminNotifier $notifier,
        private readonly OperationalPlaybookTaskManager $playbookTaskManager,
    ) {}

    public function handle(OrderDelivered $event): void
    {
        $order = Order::query()
            ->withoutGlobalScopes()
            ->whereKey((int) $event->aggregateId())
            ->where('tenant_id', $event->tenantId()->toString())
            ->first();

        if (! $order instanceof Order) {
            return;
        }

        $tenant = Tenant::query()->find($event->tenantId()->toString());

        if ($tenant instanceof Tenant) {
            $this->playbookTaskManager->syncForTrigger($tenant, 'repeat_purchase', [
                'related' => $order,
                'customer_name' => (string) ($order->customer_name ?? 'este cliente'),
                'order_number' => (string) ($order->order_number ?? 'la orden'),
            ]);
        }

        $this->notifier->notifyRepeatPurchaseOpportunity($order);
    }
}
