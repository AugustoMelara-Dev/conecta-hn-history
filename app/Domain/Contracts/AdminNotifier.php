<?php

declare(strict_types=1);

namespace App\Domain\Contracts;

// use App\Models\Lead; // REMOVED
// use App\Models\Review; // REMOVED

/**
 * AdminNotifier - Domain Contract for Administrative Notifications
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  DEPENDENCY INVERSION PRINCIPLE                                          ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║  This interface allows the Application layer to send notifications       ║
 * ║  without coupling to any specific notification infrastructure.           ║
 * ║                                                                           ║
 * ║  Implementations:                                                         ║
 * ║  - FilamentAdminNotifier (Filament database notifications)              ║
 * ║  - TwilioAdminNotifier (SMS - future)                                    ║
 * ║  - PusherAdminNotifier (Real-time - future)                              ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
interface AdminNotifier
{
    /**
     * Notify tenant owner about a new lead.
     *
     * @param  object  $lead  The newly created lead payload
     */
    public function notifyNewLead(object $lead): void;

    /**
     * Notify tenant owner about a new review.
     *
     * @param  object  $review  The newly submitted review payload
     */
    public function notifyNewReview(object $review): void;

    /**
     * Notify tenant owner about a new order.
     *
     * @param  int  $tenantId  The tenant ID
     * @param  int  $orderId  The order ID
     * @param  float  $total  The order total
     */
    public function notifyNewOrder(int $tenantId, int $orderId, float $total): void;

    /**
     * Notify tenant owner about payment status update.
     *
     * @param  int  $tenantId  The tenant ID
     * @param  string  $status  The payment status
     * @param  float  $amount  The payment amount
     */
    public function notifyPaymentUpdate(int $tenantId, string $status, float $amount): void;
}
