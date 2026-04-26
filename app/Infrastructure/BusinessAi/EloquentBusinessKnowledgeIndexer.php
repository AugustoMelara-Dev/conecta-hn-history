<?php

declare(strict_types=1);

namespace App\Infrastructure\BusinessAi;

use App\Application\BusinessAi\Contracts\BusinessKnowledgeIndexerInterface;
use App\Infrastructure\Persistence\Eloquent\Scopes\TenantScope;
use App\Models\CustomerProfile;
use App\Models\Order;
use App\Models\Product;
use App\Models\Service;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final class EloquentBusinessKnowledgeIndexer implements BusinessKnowledgeIndexerInterface
{
    public function __construct(
        private readonly BusinessEmbeddingClient $embeddingClient,
        private readonly PrivateBusinessQdrantClient $qdrantClient,
        private readonly PrivateRagRedactor $redactor,
    ) {}

    public function indexTenant(Tenant $tenant): array
    {
        if (! Schema::hasTable('tenant_knowledge_embeddings')) {
            throw new \RuntimeException('tenant_knowledge_embeddings_table_missing');
        }

        $documents = $this->documentsForTenant($tenant);
        $indexed = 0;
        $skipped = 0;
        $sources = [];
        $model = (string) config('ai-rag.embedding_model', config('directory.guide.embedding_model', 'text-embedding-3-small'));
        $batchSize = max(1, (int) config('ai-rag.private.embedding_batch_size', 64));

        foreach (array_chunk($documents, $batchSize) as $batch) {
            $prepared = [];

            foreach ($batch as $document) {
                $sourceType = (string) $document['source_type'];
                $sources[$sourceType] = ($sources[$sourceType] ?? 0) + 1;
                $input = $this->normalizeInput((string) $document['embedding_input']);

                if ($input === '') {
                    $skipped++;
                    continue;
                }

                $prepared[] = [
                    'document' => $document,
                    'source_type' => $sourceType,
                    'input' => $input,
                ];
            }

            if ($prepared === []) {
                continue;
            }

            $embeddings = $this->embeddingsForPrepared($prepared);
            $vectorPoints = [];

            foreach ($prepared as $index => $entry) {
                $document = $entry['document'];
                $sourceType = $entry['source_type'];
                $input = $entry['input'];
                $embedding = $embeddings[$index] ?? [];
                $embeddingJson = $embedding !== [] ? json_encode($embedding, JSON_THROW_ON_ERROR) : null;

                $documentId = (string) (DB::table('tenant_knowledge_embeddings')
                    ->where('tenant_id', (string) $tenant->getKey())
                    ->where('source_type', $sourceType)
                    ->where('source_id', (string) ($document['source_id'] ?? ''))
                    ->where('embedding_model', $model)
                    ->value('id') ?: Str::uuid());

                DB::table('tenant_knowledge_embeddings')->updateOrInsert(
                    [
                        'tenant_id' => (string) $tenant->getKey(),
                        'source_type' => $sourceType,
                        'source_id' => (string) ($document['source_id'] ?? ''),
                        'embedding_model' => $model,
                    ],
                    [
                        'id' => $documentId,
                        'title' => (string) $document['title'],
                        'content_hash' => hash('sha256', $input),
                        'embedding_input' => $input,
                        'embedding_json' => $embeddingJson,
                        'metadata' => json_encode($this->redactor->redactMetadata((array) ($document['metadata'] ?? [])), JSON_THROW_ON_ERROR),
                        'indexed_at' => now(),
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );

                $indexed++;

                if ($embedding !== []) {
                    $vectorPoints[] = $this->vectorPoint($documentId, $sourceType, $document, $input, $embedding, $model);
                }
            }

            $this->syncVectorStore($tenant, $vectorPoints);
        }

        return [
            'indexed' => $indexed,
            'skipped' => $skipped,
            'sources' => $sources,
        ];
    }

    /**
     * @param  list<array{document:array<string,mixed>,source_type:string,input:string}>  $prepared
     * @return array<int, list<float>>
     */
    private function embeddingsForPrepared(array $prepared): array
    {
        try {
            return $this->embeddingClient->embed(array_map(
                static fn (array $entry): string => $entry['input'],
                $prepared
            ));
        } catch (\Throwable) {
            // The private RAG still works lexically when the provider is unavailable.
            return [];
        }
    }

    /**
     * @param  array<string, mixed>  $document
     * @param  list<float>  $embedding
     * @return array{id:string,embedding:list<float>,payload:array<string,mixed>}
     */
    private function vectorPoint(string $documentId, string $sourceType, array $document, string $input, array $embedding, string $model): array
    {
        $excerpt = mb_substr($this->redactor->redactText($input), 0, 700);

        return [
            'id' => $documentId,
            'embedding' => $embedding,
            'payload' => [
                'source_type' => $sourceType,
                'source_id' => (string) ($document['source_id'] ?? ''),
                'title' => $this->redactor->redactText((string) ($document['title'] ?? 'Fuente privada')),
                'excerpt' => $excerpt,
                'embedding_model' => $model,
                'content_hash' => hash('sha256', $input),
                'indexed_at' => now()->toIso8601String(),
                'metadata' => $this->redactor->redactMetadata((array) ($document['metadata'] ?? [])),
            ],
        ];
    }

    /**
     * @param  list<array{id:string,embedding:list<float>,payload:array<string,mixed>}>  $points
     */
    private function syncVectorStore(Tenant $tenant, array $points): void
    {
        if ($points === []) {
            return;
        }

        try {
            foreach (array_chunk($points, max(1, (int) config('ai-rag.private.qdrant.upsert_batch_size', 100))) as $chunk) {
                $this->qdrantClient->upsertKnowledgePoints($tenant, $chunk);
            }
        } catch (\Throwable $exception) {
            Log::warning('business_rag.qdrant_upsert_failed', [
                'tenant_id' => (string) $tenant->getKey(),
                'points' => count($points),
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function documentsForTenant(Tenant $tenant): array
    {
        $tenantId = (string) $tenant->getKey();
        $documents = [];

        $documents[] = [
            'source_type' => 'tenant_profile',
            'source_id' => $tenantId,
            'title' => 'Perfil del negocio',
            'embedding_input' => trim(implode("\n", array_filter([
                'Negocio: '.$tenant->name,
                'Rubro: '.$this->stringValue($tenant->business_type ?? $tenant->getRawOriginal('business_type') ?? ''),
                'Descripcion: '.(string) ($tenant->description ?? ''),
                'Tags: '.(is_array($tenant->tags ?? null) ? implode(', ', $tenant->tags) : (string) ($tenant->tags ?? '')),
            ]))),
            'metadata' => ['scope' => 'private', 'entity' => 'tenant'],
        ];

        Product::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenantId)
            ->select(['id', 'name', 'description', 'price', 'stock_quantity', 'stock_status', 'is_visible'])
            ->limit((int) config('ai-rag.private.max_index_products', 500))
            ->get()
            ->each(function (Product $product) use (&$documents): void {
                $documents[] = [
                    'source_type' => 'product',
                    'source_id' => (string) $product->getKey(),
                    'title' => 'Producto: '.$product->name,
                    'embedding_input' => trim(sprintf(
                        "Producto: %s\nDescripcion: %s\nPrecio: %s\nStock: %s\nEstado stock: %s\nVisible publicamente: %s",
                        $product->name,
                        (string) $product->description,
                        (string) $product->price,
                        (string) ($product->stock_quantity ?? ''),
                        (string) ($product->getRawOriginal('stock_status') ?? ''),
                        $product->is_visible ? 'si' : 'no'
                    )),
                    'metadata' => ['scope' => 'private', 'entity' => 'product'],
                ];
            });

        Service::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenantId)
            ->select(['id', 'name', 'description', 'duration_minutes', 'price', 'is_active'])
            ->limit((int) config('ai-rag.private.max_index_services', 300))
            ->get()
            ->each(function (Service $service) use (&$documents): void {
                $documents[] = [
                    'source_type' => 'service',
                    'source_id' => (string) $service->getKey(),
                    'title' => 'Servicio: '.$service->name,
                    'embedding_input' => trim(sprintf(
                        "Servicio: %s\nDescripcion: %s\nDuracion minutos: %s\nPrecio: %s\nActivo: %s",
                        $service->name,
                        (string) $service->description,
                        (string) $service->duration_minutes,
                        (string) $service->price,
                        $service->is_active ? 'si' : 'no'
                    )),
                    'metadata' => ['scope' => 'private', 'entity' => 'service'],
                ];
            });

        CustomerProfile::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenantId)
            ->select(['id', 'name', 'lifecycle_stage', 'customer_segment', 'orders_count', 'appointments_count', 'total_spent', 'average_ticket', 'last_seen_at'])
            ->limit((int) config('ai-rag.private.max_index_customers', 300))
            ->get()
            ->each(function (CustomerProfile $profile) use (&$documents): void {
                $documents[] = [
                    'source_type' => 'customer_profile',
                    'source_id' => (string) $profile->getKey(),
                    'title' => 'Cliente: '.$this->safeName((string) $profile->name),
                    'embedding_input' => trim(sprintf(
                        "Cliente: %s\nEtapa: %s\nSegmento: %s\nOrdenes: %s\nCitas: %s\nTotal gastado: %s\nTicket promedio: %s\nUltima actividad: %s",
                        $this->safeName((string) $profile->name),
                        (string) $profile->lifecycle_stage,
                        (string) $profile->customer_segment,
                        (string) $profile->orders_count,
                        (string) $profile->appointments_count,
                        (string) $profile->total_spent,
                        (string) $profile->average_ticket,
                        optional($profile->last_seen_at)->toDateTimeString() ?? ''
                    )),
                    'metadata' => ['scope' => 'private', 'entity' => 'customer_profile', 'pii_redacted' => true],
                ];
            });

        Order::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenantId)
            ->select(['id', 'order_number', 'status', 'payment_status', 'total', 'source', 'created_at'])
            ->latest('created_at')
            ->limit((int) config('ai-rag.private.max_index_orders', 300))
            ->get()
            ->each(function (Order $order) use (&$documents): void {
                $documents[] = [
                    'source_type' => 'order',
                    'source_id' => (string) $order->getKey(),
                    'title' => 'Orden: '.((string) $order->order_number ?: (string) $order->getKey()),
                    'embedding_input' => trim(sprintf(
                        "Orden: %s\nEstado: %s\nPago: %s\nTotal: %s\nCanal: %s\nFecha: %s",
                        (string) ($order->order_number ?: $order->getKey()),
                        (string) $order->status,
                        (string) $order->payment_status,
                        (string) $order->total,
                        (string) $order->source,
                        optional($order->created_at)->toDateTimeString() ?? ''
                    )),
                    'metadata' => ['scope' => 'private', 'entity' => 'order', 'pii_redacted' => true],
                ];
            });

        return $documents;
    }

    private function normalizeInput(string $value): string
    {
        $value = $this->redactor->redactText($value);
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return trim(mb_substr($value, 0, (int) config('ai-rag.private.max_document_chars', 3000)));
    }

    private function safeName(string $name): string
    {
        $name = trim($name);

        if ($name === '') {
            return 'Cliente sin nombre';
        }

        return mb_substr($name, 0, 1).'***';
    }

    private function stringValue(mixed $value): string
    {
        if ($value instanceof \BackedEnum) {
            return (string) $value->value;
        }

        return is_scalar($value) ? (string) $value : '';
    }
}
