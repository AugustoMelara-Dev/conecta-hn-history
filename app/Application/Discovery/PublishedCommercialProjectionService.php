<?php

declare(strict_types=1);

namespace App\Application\Discovery;

use App\Infrastructure\Discovery\CanonicalPublishedOfferSource;
use App\Infrastructure\Discovery\DiscoveryProjectionStore;
use Carbon\CarbonImmutable;

final class PublishedCommercialProjectionService
{
    public function __construct(
        private readonly CanonicalPublishedOfferSource $source,
        private readonly DiscoveryProjectionStore $store,
    ) {}

    public function projectCurrent(): void
    {
        $freshnessCutoff = CarbonImmutable::now()->subDays((int) config('dovra_discovery.freshness_days', 30));
        $verificationScores = config('dovra_discovery.integrity.verification_scores', []);
        $prohibitedTerms = config('dovra_discovery.integrity.prohibited_terms', []);
        $sourceProjectionKeys = [];

        foreach ($this->source->currentOfferCandidates() as $candidate) {
            $sourceProjectionKeys[] = (string) $candidate['projection_key'];
            $isCurrent = $candidate['source_updated_at'] >= $freshnessCutoff;
            $freshnessScore = $isCurrent ? 100 : 0;
            $completenessScore = $this->completenessScore($candidate);
            $verificationLevel = $candidate['verification_level'] !== '' ? $candidate['verification_level'] : 'unknown';
            $verificationScore = (int) ($verificationScores[$verificationLevel] ?? 0);
            $prohibited = $this->containsProhibitedTerms($candidate, $prohibitedTerms);
            $moderationStatus = $prohibited ? 'rejected' : 'approved';
            $moderationScore = $prohibited ? 0 : 100;
            $isPublished = $candidate['country_code'] !== null
                && $candidate['currency_code'] !== null
                && $candidate['business_name'] !== '';
            $isEligible = $isPublished
                && $isCurrent
                && ! $prohibited
                && ! in_array($candidate['availability_status'], ['unknown', 'unavailable'], true);

            $projection = array_merge($candidate, [
                'freshness_score' => $freshnessScore,
                'completeness_score' => $completenessScore,
                'verification_level' => $verificationLevel,
                'verification_score' => $verificationScore,
                'moderation_status' => $moderationStatus,
                'moderation_score' => $moderationScore,
                'evidence_snapshot' => array_column($candidate['evidence'], 'evidence_key'),
                'published_at' => now(),
                'projected_at' => now(),
                'is_published' => $isPublished,
                'is_current' => $isCurrent,
                'is_eligible' => $isEligible,
                'prohibited_offer' => $prohibited,
                'source_updated_at' => $candidate['source_updated_at']->toDateTimeString(),
            ]);

            $integrity = [
                [
                    'record_type' => 'freshness',
                    'status' => $isCurrent ? 'current' : 'stale',
                    'score' => $freshnessScore,
                    'reason' => $isCurrent ? 'within_freshness_window' : 'stale_source',
                ],
                [
                    'record_type' => 'completeness',
                    'status' => $completenessScore >= 80 ? 'complete' : 'partial',
                    'score' => $completenessScore,
                    'reason' => 'published_projection_fields_scored',
                ],
                [
                    'record_type' => 'verification',
                    'status' => $verificationLevel,
                    'score' => $verificationScore,
                    'reason' => 'tenant_verification_status',
                ],
                [
                    'record_type' => 'moderation',
                    'status' => $moderationStatus,
                    'score' => $moderationScore,
                    'reason' => $prohibited ? 'prohibited_offer' : 'approved_public_offer',
                ],
            ];

            $this->store->upsertProjection($projection, $candidate['evidence'], $integrity);
        }

        $this->store->reconcileMissingProjections($sourceProjectionKeys);
    }

    /** @param array<string, mixed> $candidate */
    private function completenessScore(array $candidate): int
    {
        $score = 0;

        foreach ([
            $candidate['offer_name'] !== '',
            $candidate['offer_description'] !== null && trim((string) $candidate['offer_description']) !== '',
            $candidate['price_minor'] !== null,
            $candidate['currency_code'] !== null,
            $candidate['business_url'] !== null,
        ] as $present) {
            $score += $present ? 20 : 0;
        }

        return min(100, $score);
    }

    /** @param list<string> $prohibitedTerms @param array<string, mixed> $candidate */
    private function containsProhibitedTerms(array $candidate, array $prohibitedTerms): bool
    {
        $haystack = strtolower(trim(implode(' ', [
            (string) $candidate['offer_name'],
            (string) ($candidate['offer_description'] ?? ''),
        ])));

        foreach ($prohibitedTerms as $term) {
            if ($term !== '' && str_contains($haystack, strtolower((string) $term))) {
                return true;
            }
        }

        return false;
    }
}
