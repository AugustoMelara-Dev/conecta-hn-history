<?php

declare(strict_types=1);

namespace App\Application\Discovery;

use App\Domain\Discovery\DiscoveryQuery;
use App\Domain\Discovery\DiscoveryResultSet;
use App\Domain\Discovery\SemanticRetrievalAdapter;
use App\Infrastructure\Discovery\CanonicalAvailabilityInspector;
use App\Infrastructure\Discovery\DiscoveryProjectionStore;
use App\Infrastructure\Discovery\NullSemanticRetrievalAdapter;
use Illuminate\Support\Str;

final class DiscoveryQueryService
{
    public function __construct(
        private readonly DiscoveryProjectionStore $store,
        private readonly CanonicalAvailabilityInspector $availability,
        private readonly ComparableOfferService $comparables,
        ?SemanticRetrievalAdapter $semanticRetrieval = null,
    ) {
        $this->semanticRetrieval = $semanticRetrieval ?? new NullSemanticRetrievalAdapter();
    }

    private readonly SemanticRetrievalAdapter $semanticRetrieval;

    public function search(DiscoveryQuery $query): DiscoveryResultSet
    {
        $tokens = $this->normalizedTokens($query->searchText);
        $rows = $this->store->searchEligible(
            countryCode: $query->marketCountryCode,
            currencyCode: $query->currencyCode,
            budgetMinor: $query->budgetMinor,
            offerType: $query->offerType,
        );
        $semanticScores = $this->semanticRetrieval->score($query->searchText, $rows);

        $scored = [];
        foreach ($rows as $row) {
            if ($query->desiredLocalDate !== null && $row['source_type'] === 'service') {
                $status = $this->availability->statusForDate(
                    tenantId: (string) $row['source_tenant_id'],
                    locationId: $row['location_id'] !== null ? (string) $row['location_id'] : null,
                    serviceId: (int) $row['source_record_id'],
                    localDate: $query->desiredLocalDate,
                );

                if (! in_array($status, ['available', 'limited'], true)) {
                    continue;
                }

                $row['availability_status'] = $status;
            }

            $semanticScore = max(0, min(100, (int) ($semanticScores[(string) $row['id']] ?? 0)));
            $score = $this->score($row, $tokens, $query->budgetMinor, $semanticScore);
            if ($score <= 0) {
                continue;
            }

            $row['ranking_score'] = $score;
            $row['semantic_score'] = $semanticScore;
            $row['projection_id'] = (string) $row['id'];
            $row['reasons'] = $this->reasons($row, $tokens, $query->budgetMinor);
            $scored[] = $row;
        }

        usort($scored, static function (array $left, array $right): int {
            $score = ($right['ranking_score'] <=> $left['ranking_score']);
            if ($score !== 0) {
                return $score;
            }

            $price = ((int) ($left['price_minor'] ?? PHP_INT_MAX)) <=> ((int) ($right['price_minor'] ?? PHP_INT_MAX));
            if ($price !== 0) {
                return $price;
            }

            return strcmp((string) $left['offer_name'], (string) $right['offer_name']);
        });

        $limited = array_slice($scored, 0, max(1, min($query->limit, (int) config('dovra_discovery.search.max_results', 6))));
        $evidenceKeys = $this->store->evidenceKeysForProjectionIds(array_column($limited, 'projection_id'));

        $results = array_map(function (array $row) use ($evidenceKeys): array {
            return [
                'projection_id' => $row['projection_id'],
                'offer_name' => $row['offer_name'],
                'business_name' => $row['business_name'],
                'country_code' => $row['country_code'],
                'currency_code' => $row['currency_code'],
                'availability_status' => $row['availability_status'],
                'price_minor' => (int) $row['price_minor'],
                'price_display' => $row['price_display'],
                'freshness_score' => (int) $row['freshness_score'],
                'semantic_score' => (int) ($row['semantic_score'] ?? 0),
                'reasons' => $row['reasons'],
                'evidence_keys' => $evidenceKeys[$row['projection_id']] ?? [],
            ];
        }, $limited);

        $comparables = $results === [] ? [] : $this->comparables->for($results[0], $results);

        return new DiscoveryResultSet(
            results: $results,
            comparables: $comparables,
            meta: [
                'market_country_code' => $query->marketCountryCode,
                'normalized_terms' => $tokens,
                'total_results' => count($results),
                'semantic_retrieval' => 'adapter_contract',
            ],
        );
    }

    /** @param list<string> $tokens @param array<string, mixed> $row */
    private function score(array $row, array $tokens, ?int $budgetMinor, int $semanticScore): int
    {
        $haystack = Str::lower((string) ($row['search_document'] ?? ''));
        $matches = 0;
        foreach ($tokens as $token) {
            if ($token !== '' && str_contains($haystack, $token)) {
                $matches++;
            }
        }

        $score = ($matches * 30)
            + (int) ($row['freshness_score'] ?? 0)
            + (int) ($row['completeness_score'] ?? 0)
            + (int) ($row['verification_score'] ?? 0)
            + $semanticScore;

        if ($budgetMinor !== null && (int) ($row['price_minor'] ?? PHP_INT_MAX) <= $budgetMinor) {
            $score += 10;
        }

        if (in_array($row['availability_status'], ['available', 'limited'], true)) {
            $score += 15;
        }

        return $score;
    }

    /** @param list<string> $tokens @return list<string> @param array<string, mixed> $row */
    private function reasons(array $row, array $tokens, ?int $budgetMinor): array
    {
        $reasons = [];
        if ($tokens !== []) {
            $reasons[] = 'Matches the constrained need.';
        }
        if ($budgetMinor !== null && (int) ($row['price_minor'] ?? PHP_INT_MAX) <= $budgetMinor) {
            $reasons[] = 'Within the published budget.';
        }
        if ((int) ($row['freshness_score'] ?? 0) >= 100) {
            $reasons[] = 'Fresh public evidence.';
        }
        if (($row['verification_level'] ?? 'unknown') === 'verified') {
            $reasons[] = 'Verified business profile.';
        }
        if ((int) ($row['semantic_score'] ?? 0) > 0) {
            $reasons[] = 'Deterministic semantic adapter score.';
        }

        return $reasons;
    }

    /** @return list<string> */
    private function normalizedTokens(string $text): array
    {
        $normalized = Str::of(Str::lower($text));

        foreach (config('dovra_discovery.search.injection_terms', []) as $term) {
            $normalized = $normalized->replace(Str::lower((string) $term), ' ');
        }

        $normalized = $normalized
            ->replaceMatches('/[^[:alnum:]\s]/u', ' ')
            ->squish();

        $stopWords = array_map('strtolower', config('dovra_discovery.search.stop_words', []));

        return collect(explode(' ', $normalized->toString()))
            ->map(static fn (string $token): string => trim($token))
            ->filter(static fn (string $token): bool => $token !== '' && ! in_array($token, $stopWords, true))
            ->unique()
            ->values()
            ->all();
    }
}
