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
     * @param  array<int, array>  $messages
     */
    public function append(array $messages): void
    {
        if (empty($messages)) {
            return;
        }

        $normalized = array_map(static function (array $message): array {
            if (array_key_exists('processed_at', $message) && ! array_key_exists('published_at', $message)) {
                $message['published_at'] = $message['processed_at'];
            }

            unset($message['processed_at']);

            return $message;
        }, $messages);

        DB::table('outbox_messages')->insert($normalized);
    }
}
