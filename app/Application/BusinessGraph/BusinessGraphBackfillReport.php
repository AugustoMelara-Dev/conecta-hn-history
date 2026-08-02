<?php

declare(strict_types=1);

namespace App\Application\BusinessGraph;

final readonly class BusinessGraphBackfillReport
{
    /**
     * @param array<string, int> $sourceTypeCounts
     * @param list<string> $skippedSources
     */
    public function __construct(
        public int $factsWritten,
        public int $edgesWritten,
        public bool $dryRun,
        public ?string $tenantId,
        public array $sourceTypeCounts = [],
        public array $skippedSources = [],
    ) {}

    /**
     * @return array{facts_written: int, edges_written: int, dry_run: bool, tenant_id: string|null, source_type_counts: array<string, int>, skipped_sources: list<string>}
     */
    public function toArray(): array
    {
        return [
            'facts_written' => $this->factsWritten,
            'edges_written' => $this->edgesWritten,
            'dry_run' => $this->dryRun,
            'tenant_id' => $this->tenantId,
            'source_type_counts' => $this->sourceTypeCounts,
            'skipped_sources' => $this->skippedSources,
        ];
    }
}
