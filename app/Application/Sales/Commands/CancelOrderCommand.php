<?php

declare(strict_types=1);

namespace App\Application\Sales\Commands;

/**
 * CancelOrderCommand - Immutable Input Contract for Order Cancellation
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  AUDIT READY: Contains actor ID + reason for forensic trail.            ║
 * ║  TENANT ISOLATED: Requires tenant ID for ownership verification.        ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class CancelOrderCommand
{
    public function __construct(
        /** Tenant ID for ownership verification */
        public int $tenantId,

        /** Order ID to cancel */
        public int $orderId,

        /** Mandatory cancellation reason (audited) */
        public string $reason,

        /** User ID performing the cancellation (audit trail) */
        public int $userId,
    ) {}
}
