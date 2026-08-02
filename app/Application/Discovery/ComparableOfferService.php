<?php

declare(strict_types=1);

namespace App\Application\Discovery;

final class ComparableOfferService
{
    /**
     * @param  array<string, mixed>  $selected
     * @param  list<array<string, mixed>>  $results
     * @return list<array<string, mixed>>
     */
    public function for(array $selected, array $results): array
    {
        $comparables = [];
        $limit = (int) config('dovra_discovery.search.comparable_limit', 3);

        foreach ($results as $candidate) {
            if (($candidate['projection_id'] ?? null) === ($selected['projection_id'] ?? null)) {
                continue;
            }

            $comparables[] = [
                'projection_id' => $candidate['projection_id'],
                'offer_name' => $candidate['offer_name'],
                'price_minor' => $candidate['price_minor'],
                'availability_status' => $candidate['availability_status'],
                'reason' => $this->reason($selected, $candidate),
            ];

            if (count($comparables) >= $limit) {
                break;
            }
        }

        return $comparables;
    }

    /** @param array<string, mixed> $selected @param array<string, mixed> $candidate */
    private function reason(array $selected, array $candidate): string
    {
        if (($candidate['price_minor'] ?? PHP_INT_MAX) < ($selected['price_minor'] ?? PHP_INT_MAX)) {
            return 'Lower published price.';
        }

        if (($candidate['freshness_score'] ?? 0) > ($selected['freshness_score'] ?? 0)) {
            return 'Fresher published evidence.';
        }

        return 'Comparable published alternative.';
    }
}
