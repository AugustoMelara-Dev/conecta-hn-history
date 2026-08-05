<?php

declare(strict_types=1);

namespace Tests\Feature\Discovery;

use App\Application\Discovery\PublishedCommercialProjectionService;
use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

final class RefreshPublishedProjectionCommandTest extends TestCase
{
    use BuildsDiscoveryFixtures;
    use RefreshDatabase;

    public function test_command_projects_current_offers_with_evidence_and_integrity_records(): void
    {
        $product = $this->createPublishedProduct();

        $this->artisan('dovra:discover-project')
            ->expectsOutput('Published discovery projection refreshed.')
            ->assertSuccessful();

        $projection = DB::table('discovery_published_offers')
            ->where('projection_key', 'product:'.$product->id)
            ->sole();

        self::assertTrue((bool) $projection->is_published);
        self::assertTrue((bool) $projection->is_current);
        self::assertTrue((bool) $projection->is_eligible);
        self::assertGreaterThanOrEqual(
            2,
            DB::table('discovery_offer_evidence')
                ->where('projection_id', $projection->id)
                ->count(),
        );
        self::assertSame(
            4,
            DB::table('discovery_marketplace_integrity_records')
                ->where('projection_id', $projection->id)
                ->whereIn('record_type', ['freshness', 'completeness', 'moderation', 'verification'])
                ->count(),
        );
    }

    public function test_command_reruns_are_idempotent_without_duplicate_projection_rows(): void
    {
        $product = $this->createPublishedProduct();

        $this->artisan('dovra:discover-project')->assertSuccessful();
        $first = DB::table('discovery_published_offers')
            ->where('projection_key', 'product:'.$product->id)
            ->sole();
        $firstEvidenceCount = DB::table('discovery_offer_evidence')
            ->where('projection_id', $first->id)
            ->count();
        $firstIntegrityCount = DB::table('discovery_marketplace_integrity_records')
            ->where('projection_id', $first->id)
            ->whereIn('record_type', ['freshness', 'completeness', 'moderation', 'verification'])
            ->count();

        $this->artisan('dovra:discover-project')->assertSuccessful();
        $second = DB::table('discovery_published_offers')
            ->where('projection_key', 'product:'.$product->id)
            ->sole();

        self::assertSame($first->id, $second->id);
        self::assertSame(
            1,
            DB::table('discovery_published_offers')
                ->where('projection_key', 'product:'.$product->id)
                ->count(),
        );
        self::assertSame(
            $firstEvidenceCount,
            DB::table('discovery_offer_evidence')
                ->where('projection_id', $second->id)
                ->count(),
        );
        self::assertSame(
            $firstIntegrityCount,
            DB::table('discovery_marketplace_integrity_records')
                ->where('projection_id', $second->id)
                ->whereIn('record_type', ['freshness', 'completeness', 'moderation', 'verification'])
                ->count(),
        );
    }

    public function test_command_revokes_a_projection_when_the_canonical_offer_is_hidden(): void
    {
        $product = $this->createPublishedProduct();

        $this->artisan('dovra:discover-project')->assertSuccessful();
        $before = DB::table('discovery_published_offers')
            ->where('projection_key', 'product:'.$product->id)
            ->sole();

        DB::table('products')->where('id', $product->id)->update([
            'is_visible' => false,
            'updated_at' => now(),
        ]);

        $this->artisan('dovra:discover-project')->assertSuccessful();
        $after = DB::table('discovery_published_offers')
            ->where('projection_key', 'product:'.$product->id)
            ->sole();

        self::assertSame($before->id, $after->id);
        self::assertFalse((bool) $after->is_published);
        self::assertFalse((bool) $after->is_current);
        self::assertFalse((bool) $after->is_eligible);
        self::assertSame('revoked', $after->moderation_status);
        self::assertSame(
            0,
            DB::table('discovery_offer_evidence')
                ->where('projection_id', $after->id)
                ->where('is_current', true)
                ->count(),
        );
    }

    public function test_command_revokes_a_projection_when_the_canonical_offer_is_deleted(): void
    {
        $product = $this->createPublishedProduct();

        $this->artisan('dovra:discover-project')->assertSuccessful();

        DB::table('products')->where('id', $product->id)->update([
            'deleted_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('dovra:discover-project')->assertSuccessful();
        $projection = DB::table('discovery_published_offers')
            ->where('projection_key', 'product:'.$product->id)
            ->sole();

        self::assertFalse((bool) $projection->is_published);
        self::assertFalse((bool) $projection->is_current);
        self::assertFalse((bool) $projection->is_eligible);
        self::assertSame('revoked', $projection->moderation_status);
    }

    public function test_command_returns_a_failure_code_when_projection_refresh_fails(): void
    {
        app()->bind(
            PublishedCommercialProjectionService::class,
            static function (): PublishedCommercialProjectionService {
                throw new RuntimeException('projection refresh failed');
            },
        );

        $output = new BufferedOutput;
        $exitCode = app(ConsoleKernel::class)->handle(
            new ArrayInput(['dovra:discover-project']),
            $output,
        );

        self::assertSame(1, $exitCode);
        self::assertStringNotContainsString('Published discovery projection refreshed.', $output->fetch());
    }

    private function createPublishedProduct(): Product
    {
        $tenant = Tenant::factory()->create([
            'name' => 'Projection Fixture Cafe',
            'slug' => 'projection-fixture-cafe',
            'subscription_status' => 'active',
            'verification_status' => 'verified',
        ]);
        $this->assignHonduras($tenant);

        return Product::factory()->forTenant($tenant)->create([
            'name' => 'Published coffee',
            'description' => 'Freshly roasted beans.',
            'price' => 70.00,
            'price_minor' => 7000,
            'currency' => 'HNL',
            'is_active' => true,
            'is_visible' => true,
        ]);
    }
}
