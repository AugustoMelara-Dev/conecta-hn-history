<?php

declare(strict_types=1);

namespace App\Application\Ai\Knowledge;

use App\Infrastructure\AI\Knowledge\EloquentKnowledgeIndexRepository;
use App\Models\Product;
use App\Models\Service;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use InvalidArgumentException;

final readonly class CatalogKnowledgeIndexer
{
    public function __construct(
        private EmbeddingUsageMeter $embeddings,
        private EloquentKnowledgeIndexRepository $index,
        private KnowledgeIngressGuard $ingress,
    ) {}

    public function indexTenant(string $tenantId): int
    {
        if (! config('ai.knowledge.enabled', false)) {
            return 0;
        }

        if (! Str::isUuid($tenantId)) {
            throw new InvalidArgumentException('Invalid tenant knowledge scope.');
        }

        /** @var Tenant|null $tenant */
        // MIGRATION_OR_BACKFILL: trusted UUID is validated above and every catalog query is explicitly tenant-filtered.
        $tenant = Tenant::query()->withoutGlobalScopes()->find($tenantId);
        if ($tenant === null || $tenant->trashed()) {
            $this->index->pruneTenant($tenantId, []);

            return 0;
        }

        $tenantIsPublic = (bool) $tenant->is_active
            && in_array((string) $tenant->subscription_status, ['active', 'trial'], true);
        $retained = [];
        $indexed = 0;

        Product::query()
            // MIGRATION_OR_BACKFILL: rebuild includes unpublished rows but retains the exact tenant predicate.
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->each(function (Product $product) use ($tenant, $tenantIsPublic, &$retained, &$indexed): void {
                $content = $this->content('Producto', $tenant, $product, [
                    'Precio' => $product->price,
                    'Disponibilidad' => (bool) $product->is_active ? 'activo' : 'inactivo',
                ]);
                $visibility = $tenantIsPublic
                    && (bool) $product->is_active
                    && (bool) $product->is_visible
                    && ! (bool) $product->is_locked
                    ? 'public'
                    : 'private';

                if ($this->store($tenant, 'product', $product, $visibility, $content)) {
                    $retained[] = 'product:'.(string) $product->getKey();
                    $indexed++;
                }
            });

        Service::query()
            // MIGRATION_OR_BACKFILL: rebuild includes private rows but retains the exact tenant predicate.
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->orderBy('id')
            ->each(function (Service $service) use ($tenant, $tenantIsPublic, &$retained, &$indexed): void {
                $content = $this->content('Servicio', $tenant, $service, [
                    'Precio' => $service->price,
                    'Duración' => $service->duration_minutes.' minutos',
                    'Disponibilidad' => (bool) $service->is_active ? 'activo' : 'inactivo',
                ]);
                $visibility = $tenantIsPublic && (bool) $service->is_active ? 'public' : 'private';

                if ($this->store($tenant, 'service', $service, $visibility, $content)) {
                    $retained[] = 'service:'.(string) $service->getKey();
                    $indexed++;
                }
            });

        $this->index->pruneTenant($tenantId, $retained);

        return $indexed;
    }

    /**
     * @param  array<string, mixed>  $facts
     */
    private function content(string $kind, Tenant $tenant, Model $resource, array $facts): string
    {
        $parts = [
            $kind.': '.$this->plainText((string) $resource->getAttribute('name')),
            'Negocio: '.$this->plainText((string) $tenant->name),
        ];

        $description = $this->plainText((string) ($resource->getAttribute('description') ?? ''));
        if ($description !== '') {
            $parts[] = 'Descripción: '.$description;
        }

        foreach ($facts as $label => $value) {
            if ($value !== null && $value !== '') {
                $parts[] = $label.': '.$this->plainText((string) $value);
            }
        }

        return mb_substr(implode("\n", $parts), 0, 8000);
    }

    private function store(
        Tenant $tenant,
        string $resourceType,
        Model $resource,
        string $visibility,
        string $content,
    ): bool {
        $tenantId = (string) $tenant->getKey();
        $resourceId = (string) $resource->getKey();
        $redacted = $this->ingress->inspect($tenantId, [
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'visibility' => $visibility,
            'content' => $content,
            'business_name' => $this->plainText((string) $tenant->name),
        ]);
        if ($redacted === null) {
            return false;
        }

        $embedding = $this->embeddings->embed(
            $tenantId,
            'knowledge.index.catalog',
            $redacted['content'],
        );
        $this->index->upsert(
            $tenantId,
            $redacted['resource_type'],
            $redacted['resource_id'],
            $redacted['visibility'],
            $redacted['content'],
            $embedding->vector,
            $embedding->model,
            [
                'business_name' => $redacted['business_name'],
                'input_tokens' => $embedding->inputTokens,
            ],
        );

        return true;
    }

    private function plainText(string $value): string
    {
        $withoutScripts = preg_replace(
            '#<(script|style)\b[^>]*>.*?</\1>#is',
            ' ',
            $value,
        ) ?? $value;
        $plain = html_entity_decode(strip_tags($withoutScripts), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/\s+/u', ' ', $plain));
    }
}
