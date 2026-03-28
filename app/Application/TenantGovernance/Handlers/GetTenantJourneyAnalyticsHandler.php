<?php

declare(strict_types=1);

namespace App\Application\TenantGovernance\Handlers;

use App\Application\TenantGovernance\Contracts\TenantJourneyAnalyticsViewInterface;
use App\Application\TenantGovernance\Queries\GetTenantJourneyAnalyticsQuery;

final readonly class GetTenantJourneyAnalyticsHandler
{
    public function __construct(
        private TenantJourneyAnalyticsViewInterface $view,
    ) {}

    /**
     * @return array{
     *   templatePerformance:list<array<string,mixed>>,
     *   sequencePerformance:list<array<string,mixed>>,
     *   sequenceAttribution:list<array<string,mixed>>,
     *   conversationPathAttribution:list<array<string,mixed>>,
     *   ownerSequenceAttribution:list<array<string,mixed>>,
     *   locationSequenceAttribution:list<array<string,mixed>>,
     *   channelSequenceAttribution:list<array<string,mixed>>,
     *   sequenceOutcomeAttribution:list<array<string,mixed>>,
     *   upgradeAssistedRevenue30d:float,
     *   ownerConversationPerformance:list<array<string,mixed>>,
     *   locationConversationPerformance:list<array<string,mixed>>
     * }
     */
    public function handle(GetTenantJourneyAnalyticsQuery $query): array
    {
        return $this->view->summarize($query->tenantId);
    }
}
