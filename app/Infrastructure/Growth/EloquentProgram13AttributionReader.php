<?php

declare(strict_types=1);

namespace App\Infrastructure\Growth;

use App\Domain\Analytics\Enums\CommercialOutcome;
use App\Domain\Growth\Contracts\Program13AttributionReader;
use App\Domain\Growth\Data\AttributionBucket;
use App\Domain\Growth\Data\AttributionSnapshot;
use App\Domain\Growth\Enums\AttributionStage;
use App\Models\Interaction;
use Illuminate\Support\Facades\DB;

final class EloquentProgram13AttributionReader implements Program13AttributionReader
{
    public function snapshot(string $tenantId): AttributionSnapshot
    {
        $preparedTypes = [
            Interaction::TYPE_CHECKOUT_STARTED,
            Interaction::TYPE_UPGRADE_STARTED,
        ];

        $preparedCount = (int) DB::table('interactions')
            ->where('tenant_id', $tenantId)
            ->whereIn('type', $preparedTypes)
            ->count();

        $influencedCount = (int) DB::table('interactions')
            ->where('tenant_id', $tenantId)
            ->whereNotIn('type', $preparedTypes)
            ->where(function ($query): void {
                $query
                    ->whereNotNull('utm_source')
                    ->orWhereNotNull('utm_medium')
                    ->orWhereNotNull('utm_campaign')
                    ->orWhereNotNull('utm_term')
                    ->orWhereNotNull('utm_content');
            })
            ->count();

        $verifiedCount = (int) DB::table('verified_commercial_outcomes')
            ->where('tenant_id', $tenantId)
            ->count();

        $verifiedRevenueByCurrencyMinor = DB::table('verified_commercial_outcomes')
            ->selectRaw('currency, SUM(amount_minor) as total_minor')
            ->where('tenant_id', $tenantId)
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
                ),
                new AttributionBucket(
                    stage: AttributionStage::Influenced,
                    count: $influencedCount,
                    revenueByCurrencyMinor: [],
                    definition: 'Attributed interaction signals with UTM evidence, excluding prepared and verified rows.',
                ),
                new AttributionBucket(
                    stage: AttributionStage::Verified,
                    count: $verifiedCount,
                    revenueByCurrencyMinor: $verifiedRevenueByCurrencyMinor,
                    definition: 'Server-authoritative rows from verified_commercial_outcomes only.',
                ),
            ],
            limitations: [
                'Prepared and influenced buckets are operational signals, not verified revenue.',
                'Cross-channel attribution outside repository-managed interactions requires external analytics or provider exports.',
            ],
        );
    }
}
