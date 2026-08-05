<?php

declare(strict_types=1);

namespace App\Application\Operations;

use Carbon\CarbonImmutable;

final readonly class OperationalCockpitSnapshot
{
    /**
     * @param  list<OperationalKnowledgeIssue>  $healthIssues
     * @param  list<string>  $evidenceIds
     * @param  list<string>  $degradedSources
     */
    public function __construct(
        public string $tenantId,
        public string $currency,
        public int $verifiedOutcomeMinor,
        public int $attentionRequired,
        public int $activeOpportunities,
        public int $agingOpportunities,
        public int $upcomingOrders,
        public int $upcomingReservations,
        public int $overdueQuotes,
        public int $failedImports,
        public int $failedAutomations,
        public float $aiAllowance,
        public float $aiCostToday,
        public array $healthIssues,
        public string $nextAction,
        public string $nextActionLabel,
        public string $nextActionUrl,
        public array $evidenceIds,
        public array $degradedSources,
        public CarbonImmutable $generatedAt,
    ) {}
}
