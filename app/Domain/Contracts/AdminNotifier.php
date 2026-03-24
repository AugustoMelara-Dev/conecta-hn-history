<?php

declare(strict_types=1);

namespace App\Domain\Contracts;

interface AdminNotifier
{
    public function notifyNewLead(object $lead): void;

    public function notifyNewReview(object $review): void;

    public function notifyNewOrder(int|string $tenantId, int $orderId, float $total): void;

    public function notifyPaymentUpdate(int|string $tenantId, string $status, float $amount): void;

    public function notifyAppointmentBooked(object $appointment): void;

    public function notifyAppointmentReminder(object $appointment): void;

    public function notifyRecoveryOfferReady(object $tenant, ?string $customerName, string $couponCode, float $subtotal): void;

    public function notifyRepeatPurchaseOpportunity(object $order): void;

    public function notifyRenewalAttention(object $tenant, int $daysUntilExpiry): void;
}
