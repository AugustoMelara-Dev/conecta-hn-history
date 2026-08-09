<?php

declare(strict_types=1);

namespace App\Application\Crm\LegacyUuid;

use JsonSerializable;

final readonly class CrmLegacyUuidReconciliationReport implements JsonSerializable
{
    /**
     * @param list<string> $blockingIssues
     * @param list<string> $quarantineCodes
     * @param list<string> $childGaps
     * @param array<string, mixed> $details
     */
    public function __construct(
        public string $phase,
        public string $status,
        public string $shape,
        public bool $dryRun = false,
        public int $limit = 0,
        public int $offset = 0,
        public int $sourceRows = 0,
        public int $processedRows = 0,
        public int $mappedRows = 0,
        public int $quarantinedRows = 0,
        public int $remainingBatches = 0,
        public int $childGaps = 0,
        public array $blockingIssues = [],
        public array $quarantineCodes = [],
        public array $childGapsByType = [],
        public array $details = [],
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'phase' => $this->phase,
            'status' => $this->status,
            'shape' => $this->shape,
            'dry_run' => $this->dryRun,
            'limit' => $this->limit,
            'offset' => $this->offset,
            'source_rows' => $this->sourceRows,
            'processed_rows' => $this->processedRows,
            'mapped_rows' => $this->mappedRows,
            'quarantined_rows' => $this->quarantinedRows,
            'remaining_batches' => $this->remainingBatches,
            'child_gaps' => $this->childGaps,
            'blocking_issues' => array_values(array_unique($this->blockingIssues)),
            'quarantine_codes' => array_values(array_unique($this->quarantineCodes)),
            'child_gaps_by_type' => $this->childGapsByType,
            'details' => $this->details,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
