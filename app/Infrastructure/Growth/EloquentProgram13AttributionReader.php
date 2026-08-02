<?php

declare(strict_types=1);

namespace App\Infrastructure\Growth;

use App\Domain\Analytics\Enums\CommercialOutcome;
use App\Domain\Analytics\Enums\InteractionType;
use App\Domain\Growth\Contracts\Program13AttributionReader;
use App\Domain\Growth\Data\AttributionBucket;
use App\Domain\Growth\Data\AttributionSnapshot;
use App\Domain\Growth\Enums\AttributionStage;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class EloquentProgram13AttributionReader implements Program13AttributionReader
{
    private const VERIFIED_OUTCOMES_TABLE = 'verified_commercial_outcomes';

    /**
     * These are preparation signals only. They never grant commercial-outcome
     * authority; that authority belongs to VERIFIED_OUTCOMES_TABLE.
     *
     * @var list<string>
     */
    private const PREPARED_INTERACTION_TYPES = [
        InteractionType::CHECKOUT_STARTED->value,
        InteractionType::UPGRADE_STARTED->value,
    ];

    /**
     * @var list<string>
     */
    private const UTM_COLUMNS = [
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content',
    ];

    public function snapshot(string $tenantId): AttributionSnapshot
    {
        $preparedCount = (int) $this->preparedInteractions($tenantId)->count();
        $influencedCount = (int) $this->influencedInteractions($tenantId)->count();
        $verifiedCount = (int) $this->authoritativeVerifiedOutcomes($tenantId)->count();

        $verifiedRevenueByCurrencyMinor = $this->authoritativeVerifiedOutcomes($tenantId)
            ->selectRaw('currency, SUM(amount_minor) as total_minor')
            ->whereIn('outcome', [
                CommercialOutcome::OrderPaid->value,
                CommercialOutcome::OrderRefunded->value,
            ])
            ->whereNotNull('currency')
            ->groupBy('currency')
            ->pluck('total_minor', 'currency')
            ->mapWithKeys(static fn (mixed $amount, mixed $currency): array => [(string) $currency => (int) $amount])
            ->all();

        ksort($verifiedRevenueByCurrencyMinor);

        return new AttributionSnapshot(
            buckets: [
                new AttributionBucket(
                    stage: AttributionStage::Prepared,
                    count: $preparedCount,
                    revenueByCurrencyMinor: [],
                    definition: 'Server-observed preparation signals such as checkout_started and upgrade_started.',
                    evidenceSource: 'interactions.type',
                ),
                new AttributionBucket(
                    stage: AttributionStage::Influenced,
                    count: $influencedCount,
                    revenueByCurrencyMinor: [],
                    definition: 'Attributed interaction signals with UTM evidence, excluding prepared rows.',
                    evidenceSource: 'interactions.utm_*',
                ),
                new AttributionBucket(
                    stage: AttributionStage::Verified,
                    count: $verifiedCount,
                    revenueByCurrencyMinor: $verifiedRevenueByCurrencyMinor,
                    definition: 'Server-authoritative rows from verified_commercial_outcomes only.',
                    evidenceSource: 'verified_commercial_outcomes',
                ),
            ],
            limitations: [
                'Prepared and influenced buckets are operational signals, not verified revenue.',
                'The verified bucket is populated only from repository-authoritative verified_commercial_outcomes rows; interaction flags and metadata cannot populate it.',
                'Cross-channel attribution outside repository-managed interactions requires external analytics or provider exports.',
            ],
        );
    }

    private function preparedInteractions(string $tenantId): Builder
    {
        return DB::table('interactions')
            ->where('tenant_id', $tenantId)
            ->whereIn('type', self::PREPARED_INTERACTION_TYPES);
    }

    private function influencedInteractions(string $tenantId): Builder
    {
        return DB::table('interactions')
            ->where('tenant_id', $tenantId)
            ->whereNotIn('type', self::PREPARED_INTERACTION_TYPES)
            ->where(function (Builder $query): void {
                foreach (self::UTM_COLUMNS as $index => $column) {
                    $method = $index === 0 ? 'where' : 'orWhere';

                    $query->{$method}(function (Builder $query) use ($column): void {
                        $query
                            ->whereNotNull($column)
                            ->where($column, '<>', '');
                    });
                }
            });
    }

    private function authoritativeVerifiedOutcomes(string $tenantId): Builder
    {
        return DB::table(self::VERIFIED_OUTCOMES_TABLE)
            ->where('tenant_id', $tenantId);
    }
}
