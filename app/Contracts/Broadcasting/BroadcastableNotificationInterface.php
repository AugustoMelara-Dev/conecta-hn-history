<?php

declare(strict_types=1);

namespace App\Contracts\Broadcasting;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

/**
 * Common interface for all broadcast events.
 */
interface BroadcastableNotificationInterface extends ShouldBroadcast
{
    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<PrivateChannel>
     */
    public function broadcastOn(): array;

    /**
     * Get the event name for broadcasting.
     */
    public function broadcastAs(): string;

    /**
     * Get the payload data for the broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array;
}
