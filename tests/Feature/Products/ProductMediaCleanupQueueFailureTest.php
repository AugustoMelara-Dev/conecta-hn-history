<?php

declare(strict_types=1);

namespace Tests\Feature\Products;

use App\Application\Products\ProductMediaCleanupQueue;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

final class ProductMediaCleanupQueueFailureTest extends TestCase
{
    use RefreshDatabase;

    public function test_filesystem_failure_is_redacted_retained_and_then_retried_successfully(): void
    {
        $now = CarbonImmutable::parse('2026-08-09 12:00:00');
        $rawMarker = 'RAW_PRODUCT_MEDIA_FILESYSTEM_SECRET';
        $tenantId = (string) Str::uuid();
        $path = "manual-product-staging/{$tenantId}/cleanup.png";
        CarbonImmutable::setTestNow($now);
        Storage::fake('local');
        Storage::disk('local')->put($path, 'orphan');
        $workingDisk = Storage::disk('local');
        $storageManager = Storage::getFacadeRoot();
        $failingDisk = $this->createMock(Filesystem::class);
        $failingDisk->method('exists')->willThrowException(new RuntimeException($rawMarker));
        $originalLogger = Log::getFacadeRoot();
        Log::spy();

        try {
            app(ProductMediaCleanupQueue::class)->enqueue($tenantId, 'local', $path);
            $taskId = (int) DB::table('product_media_cleanup_tasks')->value('id');
            Storage::shouldReceive('disk')->with('local')->andReturn($failingDisk);
            DB::table('product_media_cleanup_tasks')->where('id', $taskId)->update(['available_at' => $now->subMinute()]);
            self::assertSame(1, DB::table('product_media_cleanup_tasks')->where('id', $taskId)->count());
            self::assertSame($failingDisk, Storage::disk('local'));
            self::assertSame(
                1,
                DB::table('product_media_cleanup_tasks')->where('available_at', '<=', now())->count(),
            );

            $failure = app(ProductMediaCleanupQueue::class)->drain();

            self::assertSame(['processed' => 0, 'failed' => 1], $failure);
            $task = DB::table('product_media_cleanup_tasks')->where('id', $taskId)->first();
            self::assertNotNull($task);
            self::assertSame(1, (int) $task->attempts);
            self::assertSame('product_media_cleanup_unavailable', $task->last_error);
            self::assertSame($now->addMinutes(2)->format('Y-m-d H:i:s'), $task->available_at);
            self::assertSame($now->format('Y-m-d H:i:s'), $task->updated_at);

            Log::shouldHaveReceived('warning')
                ->once()
                ->with('Product media cleanup task failed and will be retried.', [
                    'task_id' => $taskId,
                    'tenant_id' => $tenantId,
                    'disk' => 'local',
                    'attempts' => 1,
                    'error_code' => 'product_media_cleanup_unavailable',
                ]);

            Log::swap($originalLogger);
            DB::table('product_media_cleanup_tasks')->where('id', $taskId)->update(['available_at' => $now->subMinute()]);
            self::assertSame(1, Artisan::call('storage:process-product-media-cleanup'));
            self::assertStringNotContainsString($rawMarker, Artisan::output());
            self::assertSame(
                2,
                (int) DB::table('product_media_cleanup_tasks')->where('id', $taskId)->value('attempts'),
            );

            Storage::swap($storageManager);
            Storage::set('local', $workingDisk);
            DB::table('product_media_cleanup_tasks')->where('id', $taskId)->update(['available_at' => $now]);

            self::assertSame(0, Artisan::call('storage:process-product-media-cleanup'));

            self::assertSame(0, DB::table('product_media_cleanup_tasks')->where('id', $taskId)->count());
            self::assertFalse($workingDisk->exists($path));
        } finally {
            Storage::swap($storageManager);
            Storage::set('local', $workingDisk);
            Log::swap($originalLogger);
            CarbonImmutable::setTestNow();
        }
    }
}
