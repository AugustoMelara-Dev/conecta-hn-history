<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class ProductionOpsCommandsTest extends TestCase
{
    public function test_production_config_guard_blocks_debug_and_sync_queue(): void
    {
        config()->set('app.debug', true);
        config()->set('queue.default', 'sync');
        config()->set('cache.default', 'file');
        config()->set('services.payment.provider', 'generic');

        $exitCode = Artisan::call('config:production-guard', [
            '--env' => 'production',
            '--json' => true,
        ]);

        $payload = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(1, $exitCode);
        self::assertSame('failed', $payload['status']);
        self::assertContains('APP_DEBUG', array_column($payload['violations'], 'key'));
        self::assertContains('QUEUE_CONNECTION', array_column($payload['violations'], 'key'));
    }

    public function test_storage_smoke_can_round_trip_a_file_on_selected_disk(): void
    {
        Storage::fake('smoke');

        $exitCode = Artisan::call('storage:smoke', [
            '--disk' => 'smoke',
            '--json' => true,
        ]);

        $payload = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(0, $exitCode);
        self::assertSame('ok', $payload['status']);
        self::assertSame('smoke', $payload['disk']);
        self::assertSame(['put' => true, 'read' => true, 'delete' => true], $payload['steps']);
    }

    public function test_observability_smoke_emits_structured_log_without_sentry_requirement(): void
    {
        $exitCode = Artisan::call('observability:smoke', [
            '--logs' => true,
            '--json' => true,
        ]);

        $payload = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(0, $exitCode);
        self::assertSame('ok', $payload['status']);
        self::assertTrue($payload['checks']['structured_log']['ok']);
    }
}
