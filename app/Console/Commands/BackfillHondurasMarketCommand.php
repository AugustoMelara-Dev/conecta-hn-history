<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Markets\HondurasMarketBackfill;

final class BackfillHondurasMarketCommand extends BackfillCommand
{
    protected $signature = 'dovra:backfill-honduras-market
        {--phase=backfill : expand, backfill, validate, or contract}
        {--dry-run : Run the transactional backfill and roll it back}
        {--json : Emit a deterministic JSON report}
        {--limit=1000 : Maximum source rows per bounded collection}
        {--max-blocking-gaps=0 : Maximum tolerated blocking gaps}
        {--checkpoint= : Write the deterministic checkpoint JSON path}
        {--report= : Write the deterministic report JSON path}';

    protected $description = 'Operate the Honduras market expand/backfill/validate/contract boundary.';

    public function handle(HondurasMarketBackfill $backfill): int
    {
        return $this->executeBackfill(
            runner: static fn (bool $dryRun, int $limit): array => $backfill->run($dryRun, $limit)->toArray(),
            blockingIssueCounter: static function (array $report): int {
                $gaps = $report['gaps'] ?? [];
                $blockingKeys = [
                    'departments',
                    'municipalities',
                    'cities',
                    'tenants_without_market',
                    'tenants_with_city_without_location',
                ];

                return array_sum(array_map(
                    static fn (string $key): int => (int) ($gaps[$key] ?? 0),
                    $blockingKeys,
                ));
            },
            expandTables: [
                'countries',
                'currencies',
                'locales',
                'timezones',
                'market_packs',
                'market_pack_versions',
                'administrative_areas',
                'localities',
                'tenant_markets',
                'locations',
            ],
        );
    }
}
