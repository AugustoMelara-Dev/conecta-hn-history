<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

final class BusinessAiQdrantEnsureCollectionCommand extends Command
{
    protected $signature = 'business-ai:qdrant-ensure-collection
        {--json : Emit JSON output}';

    protected $description = 'Create or verify the private tenant Qdrant collection for Business AI RAG.';

    public function handle(): int
    {
        $driver = (string) config('ai-rag.private.vector_store.driver', 'sql');
        $enabled = (bool) config('ai-rag.private.qdrant.enabled', false);
        $baseUrl = rtrim((string) config('ai-rag.private.qdrant.url', ''), '/');
        $collection = (string) config('ai-rag.scopes.tenant_private.collection', 'tenant_private_documents');
        $publicCollection = (string) config('ai-rag.scopes.directory_public.collection', 'directory_products');
        $dimension = max(8, (int) config('ai-rag.embedding_dimension', 1536));

        $summary = [
            'status' => 'pending',
            'collection' => $collection,
            'dimension' => $dimension,
            'distance' => 'Cosine',
            'checks' => [
                'driver_is_qdrant' => $driver === 'qdrant',
                'qdrant_enabled' => $enabled,
                'qdrant_url_present' => filter_var($baseUrl, FILTER_VALIDATE_URL) !== false,
                'collection_is_private' => $collection !== '' && $collection !== $publicCollection,
            ],
        ];

        $failed = array_keys(array_filter($summary['checks'], static fn (bool $ok): bool => ! $ok));
        if ($failed !== []) {
            $summary['status'] = 'failed';
            $summary['failed_checks'] = $failed;
            $this->emit($summary);

            return self::FAILURE;
        }

        $request = Http::acceptJson()
            ->timeout(max(2, (int) config('ai-rag.private.qdrant.timeout_seconds', 6)));

        $apiKey = (string) config('ai-rag.private.qdrant.api_key', '');
        if (trim($apiKey) !== '') {
            $request = $request->withHeaders(['api-key' => $apiKey]);
        }

        $collectionUrl = $baseUrl.'/collections/'.$collection;
        $existing = $request->get($collectionUrl);

        if ($existing->ok()) {
            $summary['qdrant_status'] = $existing->status();
            $vectorConfig = $this->vectorConfig($existing->json());
            $summary['remote_vector'] = $vectorConfig;

            if ((int) ($vectorConfig['size'] ?? 0) !== $dimension || strcasecmp((string) ($vectorConfig['distance'] ?? ''), 'Cosine') !== 0) {
                $summary['status'] = 'failed';
                $summary['error'] = 'Existing Qdrant collection has incompatible vector config.';
                $this->emit($summary);

                return self::FAILURE;
            }

            $summary['status'] = 'exists';
            $this->emit($summary);

            return self::SUCCESS;
        }

        $created = $request->put($collectionUrl, [
            'vectors' => [
                'size' => $dimension,
                'distance' => 'Cosine',
            ],
        ]);

        $summary['qdrant_status'] = $created->status();
        $summary['status'] = $created->ok() ? 'created' : 'failed';

        if (! $created->ok()) {
            $summary['error'] = $created->json('status.error') ?: $created->body();
        }

        $this->emit($summary);

        return $created->ok() ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{size?: int, distance?: string}
     */
    private function vectorConfig(array $payload): array
    {
        $vectors = data_get($payload, 'result.config.params.vectors');

        if (is_array($vectors) && isset($vectors['size'])) {
            return [
                'size' => (int) ($vectors['size'] ?? 0),
                'distance' => (string) ($vectors['distance'] ?? ''),
            ];
        }

        if (is_array($vectors)) {
            foreach ($vectors as $vector) {
                if (is_array($vector) && isset($vector['size'])) {
                    return [
                        'size' => (int) ($vector['size'] ?? 0),
                        'distance' => (string) ($vector['distance'] ?? ''),
                    ];
                }
            }
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    private function emit(array $summary): void
    {
        $summary['generated_at'] = now()->toIso8601String();

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return;
        }

        $this->line('Status: '.$summary['status']);
        foreach ((array) ($summary['checks'] ?? []) as $name => $ok) {
            $this->line(sprintf('%s %s', $ok ? 'OK   ' : 'FAIL ', $name));
        }
    }
}
