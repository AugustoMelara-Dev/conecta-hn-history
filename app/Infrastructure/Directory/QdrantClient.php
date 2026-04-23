<?php

declare(strict_types=1);

namespace App\Infrastructure\Directory;

use Illuminate\Support\Facades\Http;
use RuntimeException;

final class QdrantClient
{
    public function enabled(): bool
    {
        return (bool) config('directory.guide.qdrant.enabled', false);
    }

    /**
     * @param  list<array{id: int|string, vector: list<float>, payload: array<string, mixed>}>  $points
     */
    public function upsertPoints(array $points): void
    {
        if (! $this->enabled() || $points === []) {
            return;
        }

        $response = $this->request()->put(
            $this->collectionEndpoint('/points?wait=true'),
            ['points' => $points]
        );

        if (! $response->ok()) {
            $error = trim((string) $response->body());
            $suffix = $error !== '' ? ': '.$error : '';

            throw new RuntimeException('qdrant_upsert_failed_'.$response->status().$suffix);
        }
    }

    /**
     * @param  list<int>  $pointIds
     */
    public function deletePointsByIds(array $pointIds): void
    {
        if (! $this->enabled() || $pointIds === []) {
            return;
        }

        $response = $this->request()->post(
            $this->collectionEndpoint('/points/delete?wait=true'),
            [
                'points' => array_values(array_unique(array_map(
                    static fn (int $id): int => max(0, $id),
                    $pointIds
                ))),
            ]
        );

        if (! $response->ok()) {
            $error = trim((string) $response->body());
            $suffix = $error !== '' ? ': '.$error : '';

            throw new RuntimeException('qdrant_delete_failed_'.$response->status().$suffix);
        }
    }

    /**
     * @param  list<float>  $queryVector
     * @return list<int>
     */
    public function searchProductIds(
        array $queryVector,
        float $budgetAmount,
        string $currency,
        ?string $citySlug,
        ?string $categorySlug,
        int $limit,
    ): array {
        if (! $this->enabled() || $queryVector === []) {
            return [];
        }

        $safeLimit = max(1, min($limit, 100));
        $must = [
            ['key' => 'published', 'match' => ['value' => true]],
            ['key' => 'price', 'range' => ['lte' => max(0, $budgetAmount)]],
            ['key' => 'currency', 'match' => ['value' => strtoupper($currency)]],
        ];

        if (is_string($citySlug) && trim($citySlug) !== '') {
            $must[] = ['key' => 'city_slug', 'match' => ['value' => trim($citySlug)]];
        }

        if (is_string($categorySlug) && trim($categorySlug) !== '') {
            $must[] = ['key' => 'category_slug', 'match' => ['value' => trim($categorySlug)]];
        }

        $response = $this->request()->post(
            $this->collectionEndpoint('/points/search'),
            [
                'vector' => $queryVector,
                'limit' => $safeLimit,
                'with_payload' => true,
                'with_vector' => false,
                'filter' => [
                    'must' => $must,
                ],
            ]
        );

        if (! $response->ok()) {
            $error = trim((string) $response->body());
            $suffix = $error !== '' ? ': '.$error : '';

            throw new RuntimeException('qdrant_search_failed_'.$response->status().$suffix);
        }

        $result = $response->json('result');

        if (! is_array($result)) {
            return [];
        }

        $ids = [];

        foreach ($result as $item) {
            if (! is_array($item)) {
                continue;
            }

            $payloadProductId = data_get($item, 'payload.product_id');
            $resolvedId = is_numeric($payloadProductId) ? (int) $payloadProductId : null;

            if (! is_int($resolvedId) || $resolvedId <= 0) {
                $itemId = data_get($item, 'id');
                $resolvedId = is_numeric($itemId) ? (int) $itemId : null;
            }

            if (is_int($resolvedId) && $resolvedId > 0) {
                $ids[] = $resolvedId;
            }
        }

        return array_values(array_unique($ids));
    }

    private function collectionEndpoint(string $path = ''): string
    {
        $baseUrl = rtrim((string) config('directory.guide.qdrant.url', ''), '/');
        $collection = (string) config('directory.guide.qdrant.collection', 'directory_products');

        return $baseUrl.'/collections/'.$collection.$path;
    }

    private function request(): \Illuminate\Http\Client\PendingRequest
    {
        $apiKey = (string) config('directory.guide.qdrant.api_key', '');
        $timeout = max(2, (int) config('directory.guide.qdrant.timeout_seconds', 6));
        $request = Http::acceptJson()->timeout($timeout);

        if (trim($apiKey) !== '') {
            $request = $request->withHeaders([
                'api-key' => $apiKey,
            ]);
        }

        return $request;
    }
}
