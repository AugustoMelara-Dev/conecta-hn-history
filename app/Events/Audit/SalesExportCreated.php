<?php

declare(strict_types=1);

namespace App\Events\Audit;

use App\Contracts\Events\AuditEvent;
use App\Enums\Severity;
use App\ValueObjects\AuditContext;
use App\ValueObjects\IpAddress;
use App\ValueObjects\TenantId;
use DateTimeImmutable;

/**
 * SalesExportCreated - Audit Event for Export Operations
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  GDPR/SOC2 COMPLIANCE: Every export is forensically logged              ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║  This event is dispatched BEFORE the export starts, creating an         ║
 * ║  immutable audit trail that proves:                                     ║
 * ║    - WHO initiated the export (userId)                                  ║
 * ║    - WHEN it was initiated (occurredAt)                                 ║
 * ║    - WHAT data was requested (criteriaHash)                             ║
 * ║    - WHICH tenant context was active (tenantId)                         ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * PHP 8.2+ READONLY CLASS COMPLIANCE:
 * All properties are promoted in the constructor signature.
 * No dynamic or non-promoted properties allowed.
 */
final readonly class SalesExportCreated implements AuditEvent
{
    /**
     * Constructor with Property Promotion (PHP 8.1+ compliant)
     *
     * All properties are promoted directly in the constructor signature,
     * which is required for readonly classes in PHP 8.2+.
     */
    public function __construct(
        public string $exportId,
        public TenantId $tenantId,
        public int $userId,
        public string $format,
        public string $criteriaHash,
        public array $criteriaDetails,
        public DateTimeImmutable $occurredAt,
        public AuditContext $auditContext,
    ) {}

    /**
     * Factory method for clean construction with automatic AuditContext creation.
     */
    public static function create(
        string $exportId,
        TenantId $tenantId,
        int $userId,
        string $format,
        string $criteriaHash,
        array $criteriaDetails,
        string $ipAddress,
        string $userAgent,
    ): self {
        $occurredAt = new DateTimeImmutable();

        return new self(
            exportId: $exportId,
            tenantId: $tenantId,
            userId: $userId,
            format: $format,
            criteriaHash: $criteriaHash,
            criteriaDetails: $criteriaDetails,
            occurredAt: $occurredAt,
            auditContext: new AuditContext(
                actorId: $userId,
                ipAddress: new IpAddress($ipAddress),
                userAgent: $userAgent,
                occurredAt: $occurredAt,
            ),
        );
    }

    // ═══════════════════════════════════════════════════════════════════════
    // AuditEvent Interface Implementation
    // ═══════════════════════════════════════════════════════════════════════

    public function toAuditLog(): array
    {
        return [
            'event_type' => 'sales.export.created',
            'severity' => $this->severity()->value,
            'export_id' => $this->exportId,
            'tenant_id' => $this->tenantId->toInt(),
            'user_id' => $this->userId,
            'format' => $this->format,
            'criteria_hash' => $this->criteriaHash,
            'criteria' => $this->criteriaDetails,
            ...$this->auditContext->toArray(anonymize: true),
        ];
    }

    public function severity(): Severity
    {
        return Severity::INFO;
    }

    public function context(): AuditContext
    {
        return $this->auditContext;
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Serialization
    // ═══════════════════════════════════════════════════════════════════════

    public function toPrimitives(): array
    {
        return [
            'export_id' => $this->exportId,
            'tenant_id' => $this->tenantId->toInt(),
            'user_id' => $this->userId,
            'format' => $this->format,
            'criteria_hash' => $this->criteriaHash,
            'criteria_details' => $this->criteriaDetails,
            'occurred_at' => $this->occurredAt->format('c'),
            // Flatten context for backward compatibility or simple access, but source is contextual
            'ip_address' => $this->auditContext->ipAddress->toString(),
            'user_agent' => $this->auditContext->userAgent,
        ];
    }

    public static function fromPrimitives(array $data): self
    {
        // Defensive deserialization: tenant_id is required for multi-tenant context
        $tenantId = $data['tenant_id'] ?? null;
        if ($tenantId === null) {
            throw new \InvalidArgumentException('Missing required key: tenant_id');
        }

        $occurredAt = new DateTimeImmutable($data['occurred_at'] ?? 'now');
        $ipAddress = $data['ip_address'] ?? '0.0.0.0';
        $userAgent = $data['user_agent'] ?? 'Unknown';
        $userId = $data['user_id'] ?? 0;

        return new self(
            exportId: $data['export_id'] ?? uniqid('exp_'),
            tenantId: new TenantId($tenantId),
            userId: $userId,
            format: $data['format'] ?? 'unknown',
            criteriaHash: $data['criteria_hash'] ?? '',
            criteriaDetails: $data['criteria_details'] ?? [],
            occurredAt: $occurredAt,
            auditContext: new AuditContext(
                actorId: $userId,
                ipAddress: new IpAddress($ipAddress),
                userAgent: $userAgent,
                occurredAt: $occurredAt,
            ),
        );
    }
}
