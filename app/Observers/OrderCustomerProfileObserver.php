<?php

declare(strict_types=1);

namespace App\Observers;

use App\Domain\TenantGovernance\Services\CustomerLifecycleTriggerManager;
use App\Domain\TenantGovernance\Services\CustomerJourneySignalRecorder;
use App\Domain\TenantGovernance\Services\CustomerProfileManager;
use App\Models\Order;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class OrderCustomerProfileObserver implements ShouldHandleEventsAfterCommit
{
    public function saved(Order $order): void
    {
        $profile = app(CustomerProfileManager::class)->syncOrder(
            $order,
            $this->originalProfileId($order),
        );

        if ($profile !== null && ($order->wasRecentlyCreated || $order->wasChanged('status'))) {
            app(CustomerJourneySignalRecorder::class)->recordForProfile(
                $profile,
                'purchase',
                [
                    'order_id' => (int) $order->getKey(),
                    'order_status' => (string) $order->status,
                    'order_total' => (float) ($order->total ?? 0),
                    'delivered' => $order->status === Order::STATUS_DELIVERED,
                ],
                'crm',
            );
        }

        if ($order->wasRecentlyCreated || ($order->wasChanged('status') && $order->status === Order::STATUS_DELIVERED)) {
            app(CustomerLifecycleTriggerManager::class)->handleOrderSaved($order->fresh());
        }
    }

    public function deleted(Order $order): void
    {
        app(CustomerProfileManager::class)->syncProfileById(
            $this->originalProfileId($order) ?? $this->currentProfileId($order),
        );
    }

    private function originalProfileId(Order $order): ?int
    {
        $value = $order->getOriginal('customer_profile_id');

        return is_numeric($value) ? (int) $value : null;
    }

    private function currentProfileId(Order $order): ?int
    {
        $value = $order->getAttribute('customer_profile_id');

        return is_numeric($value) ? (int) $value : null;
    }
}
