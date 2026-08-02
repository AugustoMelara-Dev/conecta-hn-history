<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Operations\BusinessOperationsBackfill;

final class BackfillBusinessOperationsCommand extends BackfillCommand
{
    protected $signature = 'dovra:backfill-business-operations
        {--phase=backfill : expand, backfill, validate, or contract}
        {--dry-run : Run the transactional backfill and roll it back}
        {--json : Emit a deterministic JSON report}
        {--limit=1000 : Maximum tenants in this bounded run}
        {--max-blocking-gaps=0 : Maximum tolerated blocking gaps or conflicts}
        {--checkpoint= : Write the deterministic checkpoint JSON path}
        {--report= : Write the deterministic report JSON path}';

    protected $description = 'Operate the business operations expand/backfill/validate/contract boundary.';

    public function handle(BusinessOperationsBackfill $backfill): int
    {
        return $this->executeBackfill(
            runner: static fn (bool $dryRun, int $limit): array => $backfill->run($dryRun, $limit)->toArray(),
            blockingIssueCounter: static fn (array $report): int => count($report['gaps'] ?? []) + count($report['conflicts'] ?? []),
            expandTables: [
                'service_locations',
                'service_provider_locations',
                'availability_rules',
            ],
        );
    }
}
