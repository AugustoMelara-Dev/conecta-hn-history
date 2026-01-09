<?php

declare(strict_types=1);

namespace App\Events\Broadcast;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Event fired when AI generation fails critically.
 * Allows the frontend to exit "Zombie Mode" (infinite loading).
 */
final class BusinessProfileUpdateFailed implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    /**
     * @param  int  $userId  The user to notify (channel owner)
     * @param  string  $message  The error message to display
     */
    public function __construct(
        public readonly int $userId,
        public readonly string $message
    ) {}

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.'.$this->userId),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'business.profile.failed';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'error' => $this->message,
        ];
    }
}
