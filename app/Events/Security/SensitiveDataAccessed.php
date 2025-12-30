<?php

declare(strict_types=1);

namespace App\Events\Security;

use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * SensitiveDataAccessed - Security Audit Event for Data Access Logging
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  COMPLIANCE: PCI-DSS / GDPR Audit Trail                                 ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║                                                                          ║
 * ║  TRIGGERED WHEN:                                                        ║
 * ║    - Admin exports customer data                                        ║
 * ║    - Payment details viewed                                             ║
 * ║    - PII (Personal Identifiable Information) accessed                   ║
 * ║    - Bulk data download initiated                                       ║
 * ║                                                                          ║
 * ║  REQUIRED FOR:                                                          ║
 * ║    - GDPR Article 30 (Records of processing activities)                ║
 * ║    - PCI-DSS Requirement 10 (Track access to cardholder data)          ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
class SensitiveDataAccessed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly User $actor,
        public readonly string $resource,
        public readonly string $action,
        public readonly ?string $reason = null,
        public readonly ?int $affectedRecords = null,
        public readonly ?string $ipAddress = null,
    ) {}

    /**
     * Convert to array for audit logging.
     */
    public function toAuditLog(): array
    {
        return [
            'event' => 'sensitive_data_accessed',
            'actor_id' => $this->actor->id,
            'actor_email' => $this->actor->email,
            'actor_role' => $this->actor->role ?? null,
            'resource' => $this->resource,
            'action' => $this->action,
            'reason' => $this->reason,
            'affected_records' => $this->affectedRecords,
            'ip_address' => $this->ipAddress,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
