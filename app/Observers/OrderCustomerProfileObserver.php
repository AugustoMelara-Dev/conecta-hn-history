<?php

declare(strict_types=1);

namespace App\Observers;

use App\Domain\TenantGovernance\Services\CustomerProfileManager;
use App\Models\Order;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class OrderCustomerProfileObserver implements ShouldHandleEventsAfterCommit
{
    public function saved(Order $order): void
    {
        app(CustomerProfileManager::class)->syncOrder(
            $order,
            $this->originalProfileId($order),
        );
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
