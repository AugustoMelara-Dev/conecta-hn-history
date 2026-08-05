<?php

declare(strict_types=1);

namespace Tests\Feature\Discovery;

use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

final class DiscoveryProjectionScheduleTest extends TestCase
{
    public function test_published_discovery_projection_is_scheduled_hourly_with_a_30_minute_mutex_and_append_log(): void
    {
        $this->artisan('schedule:list')->assertSuccessful();

        $events = collect(app(Schedule::class)->events())
            ->filter(static fn (object $event): bool => str_ends_with((string) $event->command, 'dovra:discover-project'))
            ->values();

        self::assertCount(1, $events, 'The discovery projector must be scheduled exactly once.');

        $event = $events->first();

        self::assertSame('0 * * * *', $event->getExpression());
        self::assertTrue($event->withoutOverlapping);
        self::assertSame(30, $event->expiresAt);
        self::assertSame(storage_path('logs/dovra-discovery-projector.log'), $event->output);
        self::assertTrue($event->shouldAppendOutput);
    }

    public function test_route_declares_only_one_discovery_projector_schedule(): void
    {
        $source = file_get_contents(base_path('routes/console.php'));

        self::assertIsString($source);
        self::assertSame(
            1,
            preg_match_all("/Schedule::command\\('dovra:discover-project'\\)/", $source),
        );
    }
}
