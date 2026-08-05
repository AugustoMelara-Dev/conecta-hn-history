<?php

declare(strict_types=1);

namespace App\Infrastructure\AI;

use App\Application\Ai\Catalog\CatalogImportPublicationResult;
use App\Application\Ai\Catalog\CatalogImportPublisherInterface;
use App\Application\Products\CanonicalProductWriter;
use App\Domain\Catalog\Enums\CatalogImportStatus;
use App\Models\CatalogImportBatch;
use App\Models\CatalogImportItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

final class LaravelCatalogImportPublisher implements CatalogImportPublisherInterface
{
    public function __construct(
        private readonly CanonicalProductWriter $productWriter,
        private readonly CatalogImportPublicFileCompensation $compensation,
    ) {}

    public function publish(string $tenantId, string $batchId): CatalogImportPublicationResult
    {
        $batch = CatalogImportBatch::query()
            ->whereKey($batchId)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        $itemIds = CatalogImportItem::query()
            ->where('batch_id', (string) $batch->getKey())
            ->where('tenant_id', $tenantId)
            ->where('status', CatalogImportStatus::Approved->value)
            ->pluck('id');

        foreach ($itemIds as $itemId) {
            $this->publishItem($tenantId, (string) $batch->getKey(), (string) $itemId);
        }

        $publishedItems = CatalogImportItem::query()
            ->where('batch_id', (string) $batch->getKey())
            ->where('tenant_id', $tenantId)
            ->where('status', CatalogImportStatus::Published->value)
            ->count();
        $unfinishedItems = CatalogImportItem::query()
            ->where('batch_id', (string) $batch->getKey())
            ->where('tenant_id', $tenantId)
            ->whereNotIn('status', [
                CatalogImportStatus::Published->value,
                CatalogImportStatus::Rejected->value,
            ])
            ->count();
        $status = $unfinishedItems === 0
            ? CatalogImportStatus::Published
            : CatalogImportStatus::NeedsReview;

        $batch->forceFill([
            'status' => $status,
            'completed_at' => $status === CatalogImportStatus::Published ? now() : null,
        ])->save();

        return new CatalogImportPublicationResult(
            (string) $batch->getKey(),
            $status,
            $publishedItems,
        );
    }

    private function publishItem(string $tenantId, string $batchId, string $itemId): void
    {
        $expectedPrefix = "catalog-imports/{$tenantId}/{$batchId}/";
        $privateDisk = Storage::disk('local');
        $publicDisk = Storage::disk('public');
        $publicPath = null;
        $sourcePath = null;
        $promoted = false;

        try {
            DB::transaction(function () use ($batchId, $itemId, $tenantId, $expectedPrefix, $privateDisk, $publicDisk, &$publicPath, &$sourcePath, &$promoted): void {
                $lockedItem = CatalogImportItem::query()
                    ->whereKey($itemId)
                    ->where('batch_id', $batchId)
                    ->where('tenant_id', $tenantId)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($lockedItem->status === CatalogImportStatus::Published) {
                    return;
                }

                if ($lockedItem->status !== CatalogImportStatus::Approved || $lockedItem->product_id === null) {
                    throw new RuntimeException('Catalog import item changed before publication.');
                }
                if ($lockedItem->source_disk !== 'local' || ! str_starts_with($lockedItem->source_path, $expectedPrefix)) {
                    throw new RuntimeException('Catalog import source path is outside its tenant boundary.');
                }
                $extension = match ($lockedItem->mime_type) {
                    'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp',
                    default => throw new RuntimeException('Catalog import MIME type is not publishable.'),
                };
                if (! $privateDisk->exists($lockedItem->source_path)) {
                    throw new RuntimeException('Catalog import source image is missing.');
                }

                $sourcePath = $lockedItem->source_path;
                $publicPath = "tenants/{$tenantId}/products/{$lockedItem->product_id}.".Str::uuid().".{$extension}";
                if (! $publicDisk->put($publicPath, $privateDisk->get($sourcePath))) {
                    throw new RuntimeException('Catalog import image could not be promoted.');
                }
                $promoted = true;

                // Canonical writer owns the tenant-scoped media/update and publication transition.
                $this->productWriter->update($tenantId, (int) $lockedItem->product_id, [
                    'image_path' => $publicPath,
                ]);
                $this->productWriter->publish($tenantId, (int) $lockedItem->product_id);

                $lockedItem->forceFill([
                    'status' => CatalogImportStatus::Published,
                    'published_at' => now(),
                ])->save();
            });
        } catch (\Throwable $exception) {
            $this->compensation->deleteIfOwned($publicDisk, $publicPath, $promoted);

            throw $exception;
        }

        if ($sourcePath !== null) {
            $privateDisk->delete($sourcePath);
        }
    }
}
