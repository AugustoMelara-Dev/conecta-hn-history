<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Application\Products\MerchantProductCatalogAdapter;
use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Tests\Support\GlobalMarketFixture;
use Tests\TestCase;

final class ProductMediaConcurrencyTest extends TestCase
{
    use DatabaseTruncation;

    public function test_concurrent_replacements_serialize_and_leave_only_the_referenced_object(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'pgsql'], true)) {
            self::markTestSkipped('Row-lock concurrency contract requires MySQL or PostgreSQL.');
        }

        $tenant = Tenant::factory()->create();
        GlobalMarketFixture::assignCanada($tenant);
        $adapter = app(MerchantProductCatalogAdapter::class);
        $initialStage = "manual-product-staging/{$tenant->getKey()}/initial.png";
        Storage::disk('local')->put($initialStage, $this->png());
        $product = $adapter->create($tenant, [
            'name' => 'Concurrente',
            'price' => '10.00',
            'pending_image_path' => $initialStage,
        ], 'e1062ca4-d32d-48ad-b15a-32e0aec3ada1');

        $stageA = "manual-product-staging/{$tenant->getKey()}/replacement-a.png";
        $stageB = "manual-product-staging/{$tenant->getKey()}/replacement-b.png";
        Storage::disk('local')->put($stageA, $this->png());
        Storage::disk('local')->put($stageB, $this->png());

        $worker = <<<'PHP'
require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$tenant = App\Models\Tenant::query()->findOrFail($argv[1]);
$product = App\Models\Product::withoutGlobalScopes()->findOrFail((int) $argv[2]);
$delay = (int) $argv[5];
if ($delay > 0) {
    App\Models\Product::updating(static function (App\Models\Product $candidate) use ($delay): void {
        if ($candidate->isDirty('image_path')) {
            usleep($delay);
        }
    });
}
app(App\Application\Products\MerchantProductCatalogAdapter::class)->update(
    $tenant,
    $product,
    ['pending_image_path' => $argv[3]],
    $argv[4],
);
PHP;

        $first = new Process([
            PHP_BINARY, '-r', $worker,
            (string) $tenant->getKey(), (string) $product->getKey(),
            $stageA, 'concurrent-media-a', '1500000',
        ], base_path(), null, null, 20);
        $second = new Process([
            PHP_BINARY, '-r', $worker,
            (string) $tenant->getKey(), (string) $product->getKey(),
            $stageB, 'concurrent-media-b', '0',
        ], base_path(), null, null, 20);

        try {
            $first->start();
            usleep(250000);
            $second->start();
            $first->wait();
            $second->wait();

            self::assertTrue($first->isSuccessful(), $first->getErrorOutput().$first->getOutput());
            self::assertTrue($second->isSuccessful(), $second->getErrorOutput().$second->getOutput());

            $fresh = Product::withoutGlobalScopes()->findOrFail($product->getKey());
            self::assertIsString($fresh->image_path);
            Storage::disk('public')->assertExists($fresh->image_path);
            self::assertSame(
                [$fresh->image_path],
                Storage::disk('public')->allFiles("tenants/{$tenant->getKey()}/products/{$product->getKey()}"),
            );
            Storage::disk('local')->assertMissing($stageA);
            Storage::disk('local')->assertMissing($stageB);
            self::assertSame(3, DB::table('product_media_operations')->where('product_id', $product->getKey())->count());
            self::assertSame(0, DB::table('product_media_cleanup_tasks')->where('tenant_id', $tenant->getKey())->count());
        } finally {
            if ($first->isRunning()) {
                $first->stop();
            }
            if ($second->isRunning()) {
                $second->stop();
            }
            Storage::disk('local')->deleteDirectory("manual-product-staging/{$tenant->getKey()}");
            Storage::disk('public')->deleteDirectory("tenants/{$tenant->getKey()}");
            DB::table('product_media_cleanup_tasks')->where('tenant_id', $tenant->getKey())->delete();
            DB::table('product_media_operations')->where('tenant_id', $tenant->getKey())->delete();
            DB::table('inventory_movements')->where('product_id', $product->getKey())->delete();
            DB::table('products')->where('id', $product->getKey())->delete();
            DB::table('tenants')->where('id', $tenant->getKey())->delete();
        }
    }

    private function png(): string
    {
        $image = imagecreatetruecolor(4, 4);
        ob_start();
        imagepng($image);
        $contents = ob_get_clean();
        imagedestroy($image);

        self::assertIsString($contents);

        return $contents;
    }
}
