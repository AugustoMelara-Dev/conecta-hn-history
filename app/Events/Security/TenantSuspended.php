<?php

declare(strict_types=1);

namespace App\Events\Security;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * TenantSuspended - Security Audit Event for Tenant Suspension
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  PLATFORM SECURITY: Tenant Lifecycle Audit                              ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║                                                                          ║
 * ║  TRIGGERED WHEN:                                                        ║
 * ║    - Subscription expired and not renewed                               ║
 * ║    - Terms of Service violation detected                               ║
 * ║    - Manual suspension by platform admin                               ║
 * ║    - Fraudulent activity suspected                                     ║
 * ║                                                                          ║
 * ║  LISTENERS CAN:                                                         ║
 * ║    - Disable tenant access immediately                                 ║
 * ║    - Send notification to tenant owner                                 ║
 * ║    - Archive tenant data                                               ║
 * ║    - Log to compliance audit trail                                     ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
class TenantSuspended
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Tenant $tenant,
        public readonly string $reason,
        public readonly ?User $suspendedBy = null,
        public readonly ?\DateTimeInterface $suspendedUntil = null,
    ) {}

    /**
     * Check if this is a permanent suspension.
     */
    public function isPermanent(): bool
    {
        return $this->suspendedUntil === null;
    }

    /**
     * Convert to array for audit logging.
     */
    public function toAuditLog(): array
    {
        return [
            'event' => 'tenant_suspended',
            'tenant_id' => $this->tenant->id,
            'tenant_slug' => $this->tenant->slug,
            'tenant_name' => $this->tenant->name,
            'reason' => $this->reason,
            'suspended_by' => $this->suspendedBy?->id,
            'suspended_until' => $this->suspendedUntil?->format('Y-m-d H:i:s'),
            'is_permanent' => $this->isPermanent(),
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
