<?php

declare(strict_types=1);

namespace App\Domain\Crm\ValueObjects;

/**
 * CrmMetrics - Aggregate metrics para un tenant.
 */
final readonly class CrmMetrics
{
    public function __construct(
        public int $totalContacts,
        public int $totalCompanies,
        public int $totalDeals,
        public int $openDeals,
        public int $wonDeals,
        public int $lostDeals,
        public float $totalPipelineValue,
        public float $weightedPipelineValue,
        public float $wonValue,
        public float $winRate,
        public float $avgDealValue,
        public int $avgDaysToClose,
        public int $activitiesLast30Days,
    ) {}

    public function toArray(): array
    {
        return [
            'total_contacts' => $this->totalContacts,
            'total_companies' => $this->totalCompanies,
            'total_deals' => $this->totalDeals,
            'open_deals' => $this->openDeals,
            'won_deals' => $this->wonDeals,
            'lost_deals' => $this->lostDeals,
            'total_pipeline_value' => $this->totalPipelineValue,
            'weighted_pipeline_value' => $this->weightedPipelineValue,
            'won_value' => $this->wonValue,
            'win_rate' => $this->winRate,
            'avg_deal_value' => $this->avgDealValue,
            'avg_days_to_close' => $this->avgDaysToClose,
            'activities_last_30_days' => $this->activitiesLast30Days,
        ];
    }
}
