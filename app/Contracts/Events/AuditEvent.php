<?php

declare(strict_types=1);

namespace App\Contracts\Events;

use App\Enums\Severity;
use App\ValueObjects\AuditContext;

/**
 * AuditEvent - Contract for Security Traces
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  SECURITY LAYER: Immutable Forensic Evidence                            ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║                                                                          ║
 * ║  AUDIT EVENTS vs DOMAIN EVENTS:                                         ║
 * ║                                                                          ║
 * ║  DOMAIN EVENTS (OrderPaid, ProductCreated):                             ║
 * ║    - Represent business facts                                           ║
 * ║    - Can trigger side effects (emails, webhooks)                        ║
 * ║    - Dispatched after commit (ShouldDispatchAfterCommit)               ║
 * ║    - May implement ShouldBroadcast for real-time UI                    ║
 * ║                                                                          ║
 * ║  AUDIT EVENTS (FailedLogin, DataAccessed):                              ║
 * ║    - Represent security-relevant occurrences                            ║
 * ║    - ❌ MUST NOT implement ShouldBroadcast (security risk)             ║
 * ║    - Are SYNCHRONOUS (logged immediately)                              ║
 * ║    - Must NEVER trigger business logic                                  ║
 * ║    - Must preserve privacy (IP anonymization via IpAddress VO)         ║
 * ║                                                                          ║
 * ║  COMPLIANCE DRIVERS:                                                    ║
 * ║    - GDPR Article 30: Records of processing activities                 ║
 * ║    - PCI-DSS Requirement 10: Track access to cardholder data           ║
 * ║    - SOC 2: Security event logging                                     ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * IMPLEMENTERS MUST:
 * - Anonymize IPs before logging (use AuditContext::withAnonymizedIp())
 * - Return appropriate Severity enum value
 * - Never include raw passwords or tokens in toAuditLog()
 * - Never implement ShouldBroadcast
 */
interface AuditEvent
{
    /**
     * Convert to a flat array for secure logging.
     *
     * SECURITY REQUIREMENTS:
     * - IP addresses MUST be anonymized
     * - No raw passwords, tokens, or secrets
     * - All fields should be serializable (primitives/strings/dates)
     *
     * @return array<string, mixed> Flat array suitable for JSON logging
     */
    public function toAuditLog(): array;

    /**
     * Get the PSR-3 severity level for this event.
     *
     * Uses Severity enum for type safety.
     */
    public function severity(): Severity;

    /**
     * Get the standardized audit context.
     *
     * Contains: actorId, ipAddress, userAgent, occurredAt
     */
    public function context(): AuditContext;
}
