<?php

declare(strict_types=1);

namespace App\Infrastructure\Common\Outbox;

use Illuminate\Support\Facades\DB;

/**
 * OutboxRepository - The Integration Tunnel
 * 
 * "Dumb" repository responsible ONLY for inserting messages into the outbox.
 * No logic, just SQL.
 */
final readonly class OutboxRepository
{
    /**
     * @param array<int, array> $messages
     */
    public function append(array $messages): void
    {
        if (empty($messages)) {
            return;
        }

        DB::table('outbox_messages')->insert($messages);
    }
}
