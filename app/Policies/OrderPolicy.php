<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Order;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * OrderPolicy - Tenant-Aware Authorization for Orders
 *
 * ══════════════════════════════════════════════════════════════════════════════
 * SECURITY: Enforces tenant ownership + business rules for financial integrity
 * ══════════════════════════════════════════════════════════════════════════════
 *
 * BUSINESS RULES:
 * - Orders in processing/ready/delivered states cannot be deleted (audit trail)
 * - Only pending/confirmed orders can be cancelled
 */
class OrderPolicy extends TenantAwarePolicy
{
    /**
     * Determine whether the user can view any orders.
     */
    public function viewAny(User $user): bool
    {
        return $this->hasAnyTenant($user);
    }

    /**
     * Determine whether the user can view the order.
     */
    public function view(User $user, Order $order): bool
    {
        return $this->ownsResource($user, $order);
    }

    /**
     * Determine whether the user can create orders.
     *
     * Note: Orders are typically created by customers (no auth),
     * but authenticated users can also create orders.
     */
    public function create(User $user): bool
    {
        return $this->hasAnyTenant($user);
    }

    /**
     * Determine whether the user can update the order.
     */
    public function update(User $user, Order $order): bool
    {
        return $this->ownsResource($user, $order);
    }

    /**
     * Determine whether the user can delete the order.
     *
     * BUSINESS RULE: Cannot delete orders that have been processed.
     * This protects financial audit trail and transaction history.
     */
    public function delete(User $user, Order $order): Response
    {
        // First: Must own the resource
        if (! $this->ownsResource($user, $order)) {
            return Response::deny('No tienes permiso para eliminar esta orden.');
        }

        // Second: Cannot delete orders in processing or completed states
        $protectedStates = [
            Order::STATUS_PROCESSING,
            Order::STATUS_READY,
            Order::STATUS_DELIVERED,
        ];

        if (in_array($order->status, $protectedStates)) {
            return Response::deny(
                'No se puede eliminar una orden que ya fue procesada. '.
                'El estado actual es: '.$order->status_label
            );
        }

        return Response::allow();
    }

    /**
     * Determine whether the user can restore the order.
     */
    public function restore(User $user, Order $order): bool
    {
        return $this->ownsResource($user, $order);
    }

    /**
     * Determine whether the user can permanently delete the order.
     *
     * STRICT: Only super admins can force delete orders.
     */
    public function forceDelete(User $user, Order $order): bool
    {
        return $this->ownsResource($user, $order) && $this->isAdmin($user);
    }

    /**
     * Determine whether the user can update order status.
     *
     * Used for Kanban board status transitions.
     */
    public function updateStatus(User $user, Order $order): bool
    {
        return $this->ownsResource($user, $order);
    }

    /**
     * Determine whether the user can cancel the order.
     */
    public function cancel(User $user, Order $order): Response
    {
        if (! $this->ownsResource($user, $order)) {
            return Response::deny('No tienes permiso para cancelar esta orden.');
        }

        // Can only cancel pending or confirmed orders
        $cancellableStates = [
            Order::STATUS_PENDING,
            Order::STATUS_CONFIRMED,
        ];

        if (! in_array($order->status, $cancellableStates)) {
            return Response::deny(
                'Solo se pueden cancelar órdenes pendientes o confirmadas.'
            );
        }

        return Response::allow();
    }
}
