<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

final class RuntimeSmokeCommandsTest extends TestCase
{
    public function test_storage_smoke_uploads_reads_and_deletes_on_selected_disk(): void
    {
        Storage::fake('smoke-disk');

        $exitCode = Artisan::call('storage:smoke', [
            '--disk' => 'smoke-disk',
            '--json' => true,
        ]);

        $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(0, $exitCode);
        $this->assertSame('ok', $payload['status']);
        $this->assertSame('smoke-disk', $payload['disk']);
        Storage::disk('smoke-disk')->assertMissing($payload['path']);
    }

    public function test_observability_smoke_emits_structured_log_without_requiring_sentry_dsn(): void
    {
        Log::spy();
        config(['observability.error_tracking.sentry_dsn' => '']);

        $exitCode = Artisan::call('observability:smoke', [
            '--logs' => true,
            '--sentry' => true,
            '--json' => true,
        ]);

        $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(0, $exitCode);
        $this->assertSame('ok', $payload['status']);
        $this->assertSame('ok', $payload['checks']['logs']['status']);
        $this->assertSame('skipped', $payload['checks']['sentry']['status']);

        Log::shouldHaveReceived('info')
            ->once()
            ->with('observability.smoke', \Mockery::on(
                static fn (array $context): bool => isset($context['trace_id'], $context['provider'], $context['environment'])
                    && ! array_key_exists('token', $context)
            ));
    }

    public function test_cache_production_smoke_writes_reads_and_deletes_key(): void
    {
        config(['cache.default' => 'array']);

        $exitCode = Artisan::call('cache:production-smoke', [
            '--json' => true,
        ]);

        $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(0, $exitCode);
        $this->assertSame('ok', $payload['status']);
        $this->assertSame('array', $payload['cache_store']);
        $this->assertTrue($payload['read_matches_write']);
    }

    public function test_cache_production_smoke_reports_single_database_redis_failure(): void
    {
        Cache::shouldReceive('put')
            ->once()
            ->andThrow(new \RuntimeException('ERR DB index is out of range'));

        $exitCode = Artisan::call('cache:production-smoke', [
            '--json' => true,
        ]);

        $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(1, $exitCode);
        $this->assertSame('failed', $payload['status']);
        $this->assertStringContainsString('REDIS_CACHE_DB=0', $payload['message']);
    }

    public function test_ops_runtime_smoke_passes_local_runtime_with_sync_queue(): void
    {
        Storage::fake('smoke-disk');
        config([
            'app.env' => 'local',
            'queue.default' => 'sync',
            'cache.default' => 'array',
            'filesystems.default' => 'smoke-disk',
            'filesystems.media_disk' => 'smoke-disk',
            'filesystems.payment_evidence_disk' => 'smoke-disk',
        ]);

        $output = new BufferedOutput;
        $exitCode = app(\Illuminate\Contracts\Console\Kernel::class)->call('ops:runtime-smoke', [
            '--env' => 'local',
            '--disk' => 'smoke-disk',
            '--json' => true,
        ], $output);

        $payload = json_decode($output->fetch(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(0, $exitCode);
        $this->assertSame('ok', $payload['status']);
        $this->assertSame('ok', $payload['checks']['config_guard']['payload']['status']);
        $this->assertSame('ok', $payload['checks']['storage_guard']['payload']['status']);
        $this->assertSame('ok', $payload['checks']['storage_smoke']['payload']['status']);
        $this->assertSame('ok', $payload['checks']['cache_smoke']['payload']['status']);
        $this->assertSame('ok', $payload['checks']['cache_lock']['payload']['status']);
        $this->assertSame('ok', $payload['checks']['queue']['payload']['status']);
    }
}
