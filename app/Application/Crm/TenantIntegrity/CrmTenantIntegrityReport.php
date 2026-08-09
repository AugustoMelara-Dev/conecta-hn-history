<?php

declare(strict_types=1);

namespace App\Application\Crm\TenantIntegrity;

use JsonSerializable;

/**
 * Deterministic, redacted output from the CRM tenant-integrity preflight.
 *
 * The report deliberately contains counts and opaque samples only.  It never
 * serializes a customer row, owner email, name, phone number or arbitrary
 * payload from the database.
 */
final readonly class CrmTenantIntegrityReport implements JsonSerializable
{
    /**
     * @param list<string> $missingRequiredTables
     * @param array<string, int> $anomalies
     * @param array<string, array<string, mixed>> $inventory
     * @param array<string, mixed> $details
     * @param list<string> $blockingIssues
     */
    public function __construct(
        public string $phase,
        public string $status,
        public string $shape,
        public string $schemaFingerprint = '',
        public string $dataFingerprint = '',
        public bool $writesPerformed = false,
        public array $missingRequiredTables = [],
        public array $anomalies = [],
        public array $inventory = [],
        public array $blockingIssues = [],
        public array $details = [],
    ) {}

    public function fingerprint(): string
    {
        return $this->schemaFingerprint;
    }

    public function hasAnomalies(): bool
    {
        foreach ($this->anomalies as $count) {
            if ((int) $count > 0) {
                return true;
            }
        }

        return $this->missingRequiredTables !== [] || $this->blockingIssues !== [];
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $anomalies = $this->anomalies;
        ksort($anomalies);
        $inventory = $this->inventory;
        ksort($inventory);
        $missing = array_values(array_unique(array_map('strval', $this->missingRequiredTables)));
        sort($missing);
        $blocking = array_values(array_unique(array_map('strval', $this->blockingIssues)));
        sort($blocking);

        return [
            'phase' => $this->phase,
            'status' => $this->status,
            'shape' => $this->shape,
            'schema_fingerprint' => $this->schemaFingerprint,
            'data_fingerprint' => $this->dataFingerprint,
            'writes_performed' => $this->writesPerformed,
            'missing_required_tables' => $missing,
            'anomalies' => $anomalies,
            'inventory' => $inventory,
            'blocking_issues' => $blocking,
            'details' => $this->details,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
