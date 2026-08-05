<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure;

use App\Application\Shared\Contracts\OutboxRepositoryInterface as ApplicationOutbox;
use App\Domain\Shared\OutboxRepositoryInterface as DomainOutbox;
use App\Infrastructure\Shared\Persistence\Decorators\SchemaValidatingOutboxRepository;
use App\Infrastructure\Shared\Persistence\EloquentOutboxRepository;
use Tests\TestCase;

final class CanonicalOutboxBindingTest extends TestCase
{
    public function test_only_canonical_outbox_ports_are_registered(): void
    {
        self::assertFalse(
            $this->app->bound('App\\Application\\Common\\Interfaces\\OutboxRepositoryInterface'),
            'The retired Titanium outbox port must not remain registered.'
        );
        self::assertInstanceOf(
            SchemaValidatingOutboxRepository::class,
            $this->app->make(ApplicationOutbox::class)
        );
        self::assertInstanceOf(
            EloquentOutboxRepository::class,
            $this->app->make(DomainOutbox::class)
        );
    }
}
