<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure;

use App\Infrastructure\Persistence\AdaptiveSequenceGenerator;
use App\Infrastructure\Persistence\AtomicSequenceGenerator;
use App\Infrastructure\Persistence\BufferedSequenceGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class AtomicSequenceGeneratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_atomically_increments_each_aggregate_portably(): void
    {
        $generator = app(AtomicSequenceGenerator::class);

        DB::transaction(function () use ($generator): void {
            self::assertSame(1, $generator->getNextSequence('aggregate-a'));
            self::assertSame(2, $generator->getNextSequence('aggregate-a'));
            self::assertSame(1, $generator->getNextSequence('aggregate-b'));
        });

        self::assertSame(2, $generator->getCurrentSequence('aggregate-a'));
        self::assertSame(1, $generator->getCurrentSequence('aggregate-b'));
    }

    public function test_adaptive_generator_reserves_sequences_with_portable_upsert(): void
    {
        $generator = new AdaptiveSequenceGenerator;

        self::assertSame(1, $generator->getNextSequence('adaptive-a'));
        self::assertSame(2, $generator->getNextSequence('adaptive-a'));
        self::assertSame(2, (int) DB::table('outbox_sequences')->where('aggregate_id', 'adaptive-a')->value('last_sequence'));
    }

    public function test_buffered_generator_reserves_blocks_with_portable_upsert(): void
    {
        $generator = new BufferedSequenceGenerator;

        self::assertSame(1, $generator->getNextSequence('buffered-a'));
        self::assertSame(2, $generator->getNextSequence('buffered-a'));
        self::assertSame(50, (int) DB::table('outbox_sequences')->where('aggregate_id', 'buffered-a')->value('last_sequence'));

        $nextWorker = new BufferedSequenceGenerator;
        self::assertSame(51, $nextWorker->getNextSequence('buffered-a'));
        self::assertSame(100, (int) DB::table('outbox_sequences')->where('aggregate_id', 'buffered-a')->value('last_sequence'));
    }
}
