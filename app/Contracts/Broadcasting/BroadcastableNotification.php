<?php

declare(strict_types=1);

namespace App\Contracts\Broadcasting;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

/**
 * BroadcastableNotification - Common Interface for All Broadcast Events
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  POLYMORPHISM GUARANTEE:                                                ║
 * ║    - All Broadcast Events implement this interface                      ║
 * ║    - Extends ShouldBroadcast to ensure Laravel recognition             ║
 * ║    - Enables type-hinting in Listeners                                  ║
 * ║    - Ensures consistent payload structure                               ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * USAGE IN LISTENERS:
 * ```php
 * public function handle(BroadcastableNotification $event): void
 * {
 *     $payload = $event->broadcastWith();
 *     // Process any broadcast event polymorphically
 * }
 * ```
 */
interface BroadcastableNotification extends ShouldBroadcast
{
    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<PrivateChannel>
     */
    public function broadcastOn(): array;

    /**
     * Get the event name for broadcasting.
     *
     * @return string Event name (e.g., 'order.paid')
     */
    public function broadcastAs(): string;

    /**
     * Get the payload data for the broadcast.
     *
     * The payload MUST include:
     *   - '_version' (int): Payload version for backwards compatibility
     *   - '_timestamp' (string): ISO 8601 timestamp
     *   - 'title' (string): UI notification title
     *   - 'message' (string): UI notification message
     *   - 'status' (string): Event status identifier
     *   - 'icon' (string): Icon identifier for UI
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array;
}
