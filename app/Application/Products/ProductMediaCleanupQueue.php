<?php

declare(strict_types=1);

namespace App\Application\Products;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Throwable;

final class ProductMediaCleanupQueue
{
    private const UUID_PATTERN = '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-8][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}';

    public function enqueue(
        string $tenantId,
        string $disk,
        string $path,
        ?int $productId = null,
    ): void {
        $this->assertOwnedPath($tenantId, $disk, $path, $productId);
        $now = now();

        DB::table('product_media_cleanup_tasks')->updateOrInsert(
            ['disk' => $disk, 'path_hash' => hash('sha256', $path)],
            [
                'tenant_id' => $tenantId,
                'product_id' => $productId,
                'path' => $path,
                'available_at' => $now,
                'last_error' => null,
                'updated_at' => $now,
                'created_at' => $now,
            ],
        );
    }

    /**
     * @return array{processed:int,failed:int}
     */
    public function drain(int $limit = 100): array
    {
        $tasks = DB::table('product_media_cleanup_tasks')
            ->where(function ($query): void {
                $query->whereNull('available_at')->orWhere('available_at', '<=', now());
            })
            ->orderBy('id')
            ->limit(max(1, min($limit, 500)))
            ->get();

        $processed = 0;
        $failed = 0;

        foreach ($tasks as $task) {
            try {
                $tenantId = (string) $task->tenant_id;
                $disk = (string) $task->disk;
                $path = (string) $task->path;
                $productId = $task->product_id === null ? null : (int) $task->product_id;
                $this->assertOwnedPath($tenantId, $disk, $path, $productId);
                $filesystem = Storage::disk($disk);

                if ($filesystem->exists($path) && ! $filesystem->delete($path)) {
                    throw new \RuntimeException('Storage returned false while deleting the object.');
                }

                DB::table('product_media_cleanup_tasks')->where('id', $task->id)->delete();
                $processed++;
            } catch (Throwable $exception) {
                $attempts = (int) $task->attempts + 1;
                DB::table('product_media_cleanup_tasks')->where('id', $task->id)->update([
                    'attempts' => $attempts,
                    'available_at' => now()->addMinutes(min(60, 2 ** min($attempts, 5))),
                    'last_error' => mb_substr($exception->getMessage(), 0, 2000),
                    'updated_at' => now(),
                ]);
                Log::warning('Product media cleanup task failed and will be retried.', [
                    'task_id' => $task->id,
                    'tenant_id' => $task->tenant_id,
                    'disk' => $task->disk,
                    'attempts' => $attempts,
                    'exception' => $exception::class,
                ]);
                $failed++;
            }
        }

        return ['processed' => $processed, 'failed' => $failed];
    }

    private function assertOwnedPath(
        string $tenantId,
        string $disk,
        string $path,
        ?int $productId,
    ): void {
        $tenant = preg_quote($tenantId, '#');
        $uuid = self::UUID_PATTERN;

        $valid = match ($disk) {
            'local' => $productId === null
                && preg_match("#^manual-product-staging/{$tenant}/[A-Za-z0-9][A-Za-z0-9._-]{0,127}$#D", $path) === 1,
            'public' => $productId !== null
                && preg_match("#^tenants/{$tenant}/products/{$productId}/{$uuid}\\.(?:jpg|png|webp)$#D", $path) === 1,
            default => false,
        };

        if (! $valid || str_contains($path, '..') || preg_match('/[[:cntrl:]]/', $path) === 1) {
            throw new InvalidArgumentException('Product media cleanup path is not canonical for its tenant and product.');
        }
    }
}
