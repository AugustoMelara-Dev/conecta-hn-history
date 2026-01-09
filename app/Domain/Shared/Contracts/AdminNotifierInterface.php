<?php

declare(strict_types=1);

namespace App\Domain\Shared\Contracts;

use App\Domain\Sales\Entities\LeadEntity;

/**
 * AdminNotifierInterface - Pure Domain Contract for Administrative Notifications
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  DEPENDENCY INVERSION PRINCIPLE                                          ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║  Uses domain entities (LeadEntity) instead of Eloquent models.           ║
 * ║                                                                           ║
 * ║  Implementations:                                                         ║
 * ║  - FilamentAdminNotifier (Filament database notifications)              ║
 * ║  - SlackAdminNotifier (Slack webhooks - future)                          ║
 * ║  - TwilioAdminNotifier (SMS - future)                                    ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
interface AdminNotifierInterface
{
    /**
     * Notify tenant owner about a new lead.
     *
     * @param LeadEntity $lead The newly created lead (domain entity)
     */
    public function notifyNewLead(LeadEntity $lead): void;

    /**
     * Notify tenant owner about a new order.
     *
     * @param int $tenantId The tenant ID
     * @param int $orderId The order ID
     * @param float $total The order total
     */
    public function notifyNewOrder(int $tenantId, int $orderId, float $total): void;

    /**
     * Notify tenant owner about payment status update.
     *
     * @param int $tenantId The tenant ID
     * @param string $status The payment status
     * @param float $amount The payment amount
     */
    public function notifyPaymentUpdate(int $tenantId, string $status, float $amount): void;
}
