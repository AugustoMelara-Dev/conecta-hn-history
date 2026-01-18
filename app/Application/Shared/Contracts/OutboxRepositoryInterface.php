<?php

declare(strict_types=1);

namespace App\Application\Shared\Contracts;

use App\Domain\Shared\Entities\DomainEventMessage;

/**
 * OutboxRepositoryInterface
 *
 * Port for persisting domain events within the business transaction.
 * Follows the "Transactional Outbox" pattern.
 */
interface OutboxRepositoryInterface
{
    public function save(DomainEventMessage $message): void;
}
