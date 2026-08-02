<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Identity\IdentityGovernanceBackfill;

final class BackfillIdentityGovernanceCommand extends BackfillCommand
{
    protected $signature = 'dovra:backfill-identity-governance
        {--phase=backfill : expand, backfill, validate, or contract}
        {--dry-run : Run the transactional backfill and roll it back}
        {--json : Emit a deterministic JSON report}
        {--limit=1000 : Maximum source rows per bounded collection}
        {--offset=0 : Stable source offset for the bounded batch}
        {--resume : Resume from next_source_offset in --checkpoint}
        {--max-blocking-gaps=0 : Maximum tolerated blocking gaps or conflicts}
        {--checkpoint= : Write the deterministic checkpoint JSON path}
        {--report= : Write the deterministic report JSON path}';

    protected $description = 'Operate the identity governance expand/backfill/validate/contract boundary.';

    public function handle(IdentityGovernanceBackfill $backfill): int
    {
        return $this->executeBackfill(
            runner: static fn (bool $dryRun, int $limit, int $offset): array => $backfill->run($dryRun, $limit, $offset)->toArray(),
            blockingIssueCounter: static fn (array $report): int => (int) ($report['role_conflicts'] ?? 0),
            expandTables: ['tenant_memberships'],
        );
    }
}
