<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Console\Commands\GenerateSitemapCommand;
use Illuminate\Console\OutputStyle;
use Illuminate\Support\Facades\Storage;
use ReflectionMethod;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

final class SitemapCloudStorageCompatibilityTest extends TestCase
{
    public function test_sitemaps_stream_through_flysystem_without_requiring_a_local_disk_path(): void
    {
        Storage::fake('public');
        $command = app(GenerateSitemapCommand::class);
        $command->setOutput(new OutputStyle(new ArrayInput([]), new BufferedOutput));

        $this->invoke($command, 'startNewSitemapFile', ['tenant-demo']);
        $this->invoke($command, 'writeUrlToSitemap', [
            'https://conecta.example/tienda/tenant-demo',
            now(),
            'daily',
            0.8,
        ]);
        $this->invoke($command, 'closeSitemapFile');
        $this->invoke($command, 'generateSitemapIndex', ['tenant-demo', 1]);

        Storage::disk('public')->assertExists('sitemaps/sitemap-tenant-demo.xml');
        Storage::disk('public')->assertExists('sitemaps/sitemap-tenant-demo-index.xml');
        self::assertStringContainsString(
            '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">',
            (string) Storage::disk('public')->get('sitemaps/sitemap-tenant-demo.xml'),
        );
        self::assertStringContainsString(
            'sitemap-tenant-demo.xml',
            (string) Storage::disk('public')->get('sitemaps/sitemap-tenant-demo-index.xml'),
        );
    }

    /**
     * @param  list<mixed>  $arguments
     */
    private function invoke(object $target, string $method, array $arguments = []): mixed
    {
        return (new ReflectionMethod($target, $method))->invokeArgs($target, $arguments);
    }

    public function test_scheduler_uses_the_cloud_storage_compatible_sitemap_command(): void
    {
        $bootstrap = file_get_contents(dirname(__DIR__, 3).'/bootstrap/app.php');
        self::assertStringContainsString("command('sitemap:generate')", $bootstrap);
        self::assertStringNotContainsString("command('seo:generate-sitemap')", $bootstrap);
        self::assertFileDoesNotExist(dirname(__DIR__, 3).'/app/Console/Commands/GenerateSitemap.php');
        self::assertFileDoesNotExist(dirname(__DIR__, 3).'/app/Console/Commands/SubscriptionLifecycleCommand.php');
    }
}
