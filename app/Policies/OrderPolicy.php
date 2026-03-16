<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Access\Enums\Permission;
use App\Domain\TenantGovernance\Enums\Capability;
use App\Models\Order;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class OrderPolicy extends TenantAwarePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->allows($user, Capability::CHECKOUT_BASIC, Permission::ORDERS_VIEW);
    }

    public function view(User $user, Order $order): bool
    {
        return $this->allows($user, Capability::CHECKOUT_BASIC, Permission::ORDERS_VIEW, $order);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, Capability::CHECKOUT_BASIC, Permission::ORDERS_CREATE);
    }

    public function update(User $user, Order $order): bool
    {
        return $this->allows($user, Capability::CHECKOUT_BASIC, Permission::ORDERS_MANAGE, $order);
    }

    public function delete(User $user, Order $order): Response
    {
        if (! $this->allows($user, Capability::CHECKOUT_BASIC, Permission::ORDERS_CANCEL, $order)) {
            return Response::deny('No tienes permiso para eliminar esta orden.');
        }

        $protectedStates = [
            Order::STATUS_PROCESSING,
            Order::STATUS_READY,
            Order::STATUS_DELIVERED,
        ];

        if (in_array($order->status, $protectedStates, true)) {
            return Response::deny(
                'No se puede eliminar una orden que ya fue procesada. '.
                'El estado actual es: '.$order->status_label
            );
        }

        return Response::allow();
    }

    public function restore(User $user, Order $order): bool
    {
        return $this->allows($user, Capability::CHECKOUT_BASIC, Permission::ORDERS_MANAGE, $order);
    }

    public function forceDelete(User $user, Order $order): bool
    {
        return $this->allows($user, Capability::CHECKOUT_BASIC, Permission::ORDERS_CANCEL, $order)
            && $this->isTenantOwner($user, $this->resolveTenant($user, $order));
    }

    public function updateStatus(User $user, Order $order): bool
    {
        return $this->allows($user, Capability::CHECKOUT_BASIC, Permission::ORDERS_FULFILL, $order);
    }

    public function cancel(User $user, Order $order): Response
    {
        if (! $this->allows($user, Capability::CHECKOUT_BASIC, Permission::ORDERS_CANCEL, $order)) {
            return Response::deny('No tienes permiso para cancelar esta orden.');
        }

        $cancellableStates = [
            Order::STATUS_PENDING,
            Order::STATUS_CONFIRMED,
        ];

        if (! in_array($order->status, $cancellableStates, true)) {
            return Response::deny('Solo se pueden cancelar ordenes pendientes o confirmadas.');
        }

        return Response::allow();
    }
}
