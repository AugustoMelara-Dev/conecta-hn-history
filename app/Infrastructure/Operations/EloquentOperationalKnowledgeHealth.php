<?php

declare(strict_types=1);

namespace App\Infrastructure\Operations;

// @withoutGlobalScopes-safety INFRA_TENANT_FILTER: every health query carries an explicit tenant predicate.

use App\Application\Operations\OperationalKnowledgeHealth;
use App\Application\Operations\OperationalKnowledgeIssue;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class EloquentOperationalKnowledgeHealth implements OperationalKnowledgeHealth
{
    /** @return list<OperationalKnowledgeIssue> */
    public function forTenant(string $tenantId): array
    {
        $tenant = Tenant::query()->withoutGlobalScopes()->findOrFail($tenantId);
        $checkedAt = CarbonImmutable::now();
        $issues = [];
        $productUrl = "/app/{$tenant->slug}/products";
        $serviceUrl = "/app/{$tenant->slug}/services";
        $importUrl = '/workspace/catalog-imports';

        $products = DB::table('products')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->get([
                'id', 'name', 'price_minor', 'currency', 'manage_stock',
                'stock_quantity', 'is_visible', 'updated_at',
            ]);
        $productIndex = DB::table('ai_knowledge_chunks')
            ->where('tenant_id', $tenantId)
            ->where('resource_type', 'product')
            ->whereIn('resource_id', $products->pluck('id')->map(
                static fn (mixed $id): string => (string) $id,
            ))
            ->get(['resource_id', 'indexed_at'])
            ->keyBy('resource_id');

        foreach ($products as $product) {
            $id = (string) $product->id;
            $label = (string) $product->name;
            if (! (bool) $product->is_visible) {
                $issues[] = $this->issue(
                    'product_unpublished',
                    'warning',
                    'product',
                    $id,
                    $label,
                    'Product is not available in the public catalog.',
                    "{$productUrl}/{$id}/edit",
                    $checkedAt,
                );
            }
            if ((int) ($product->price_minor ?? 0) < 1 || blank($product->currency)) {
                $issues[] = $this->issue(
                    'product_price_missing',
                    'critical',
                    'product',
                    $id,
                    $label,
                    'Product needs a valid price and currency.',
                    "{$productUrl}/{$id}/edit",
                    $checkedAt,
                );
            }
            if ((bool) $product->manage_stock && (int) $product->stock_quantity < 1) {
                $issues[] = $this->issue(
                    'product_inventory_unavailable',
                    'warning',
                    'product',
                    $id,
                    $label,
                    'Product has no sellable inventory.',
                    "{$productUrl}/{$id}/edit",
                    $checkedAt,
                );
            }
            $chunk = $productIndex->get($id);
            if ($chunk !== null && $chunk->indexed_at < $product->updated_at) {
                $issues[] = $this->issue(
                    'knowledge_index_stale',
                    'warning',
                    'product',
                    $id,
                    $label,
                    'Buyer-facing knowledge is older than the catalog record.',
                    "{$productUrl}/{$id}/edit",
                    $checkedAt,
                );
            }
        }

        $services = DB::table('services')
            ->where('tenant_id', $tenantId)
            ->get(['id', 'name', 'price_minor', 'currency', 'duration_minutes', 'is_active']);
        $serviceIds = $services->pluck('id');
        $located = $this->groupedIds(
            'service_locations',
            $tenantId,
            'service_id',
            $serviceIds,
            false,
        );
        $scheduled = $this->groupedIds(
            'availability_rules',
            $tenantId,
            'service_id',
            $serviceIds,
            true,
        );
        foreach ($services as $service) {
            $id = (string) $service->id;
            $label = (string) $service->name;
            if (! (bool) $service->is_active) {
                $issues[] = $this->issue(
                    'service_unpublished',
                    'warning',
                    'service',
                    $id,
                    $label,
                    'Service is not available to buyers.',
                    "{$serviceUrl}/{$id}/edit",
                    $checkedAt,
                );
            }
            if ((int) ($service->price_minor ?? 0) < 1 || blank($service->currency)) {
                $issues[] = $this->issue(
                    'service_price_missing',
                    'critical',
                    'service',
                    $id,
                    $label,
                    'Service needs a valid price and currency.',
                    "{$serviceUrl}/{$id}/edit",
                    $checkedAt,
                );
            }
            if ((int) $service->duration_minutes < 1) {
                $issues[] = $this->issue(
                    'service_duration_missing',
                    'critical',
                    'service',
                    $id,
                    $label,
                    'Service needs a positive duration.',
                    "{$serviceUrl}/{$id}/edit",
                    $checkedAt,
                );
            }
            if (! $located->contains((int) $service->id)) {
                $issues[] = $this->issue(
                    'service_location_missing',
                    'critical',
                    'service',
                    $id,
                    $label,
                    'Service is not assigned to an active business location.',
                    "{$serviceUrl}/{$id}/edit",
                    $checkedAt,
                );
            }
            if (! $scheduled->contains((int) $service->id)) {
                $issues[] = $this->issue(
                    'service_schedule_missing',
                    'critical',
                    'service',
                    $id,
                    $label,
                    'Service has no canonical availability schedule.',
                    "{$serviceUrl}/{$id}/edit",
                    $checkedAt,
                );
            }
        }

        $imports = DB::table('catalog_import_items')
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['needs_review', 'rejected', 'failed'])
            ->get(['id', 'original_name', 'status']);
        foreach ($imports as $item) {
            $code = match ($item->status) {
                'rejected' => 'import_rejected',
                'failed' => 'import_failed',
                default => 'import_unpublished',
            };
            $issues[] = $this->issue(
                $code,
                $item->status === 'failed' ? 'critical' : 'warning',
                'catalog_import_item',
                (string) $item->id,
                (string) ($item->original_name ?: "Import item {$item->id}"),
                'Import row requires review or remediation before publication.',
                $importUrl,
                $checkedAt,
            );
        }

        return $issues;
    }

    /** @param Collection<int, mixed> $ids */
    private function groupedIds(
        string $table,
        string $tenantId,
        string $column,
        Collection $ids,
        bool $activeOnly,
    ): Collection {
        if ($ids->isEmpty()) {
            return collect();
        }

        return DB::table($table)
            ->where('tenant_id', $tenantId)
            ->whereIn($column, $ids)
            ->when($activeOnly, fn ($query) => $query->where('is_active', true))
            ->distinct()
            ->pluck($column);
    }

    private function issue(
        string $code,
        string $severity,
        string $type,
        string $id,
        string $label,
        string $message,
        string $url,
        CarbonImmutable $checkedAt,
    ): OperationalKnowledgeIssue {
        return new OperationalKnowledgeIssue(
            code: $code,
            severity: $severity,
            evidenceType: $type,
            evidenceId: $id,
            evidenceLabel: $label,
            message: $message,
            remediationUrl: $url,
            checkedAt: $checkedAt,
        );
    }
}
