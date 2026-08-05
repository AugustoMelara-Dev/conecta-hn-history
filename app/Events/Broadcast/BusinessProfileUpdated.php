<?php

declare(strict_types=1);

namespace App\Events\Broadcast;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * BusinessProfileUpdated - Lightweight Real-Time Broadcast Event
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  PERFORMANCE OPTIMIZATION: Zero Database Queries on Broadcast            ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║                                                                          ║
 * ║  BEFORE (Anti-pattern):                                                  ║
 * ║    - Constructor accepted Tenant $model                                  ║
 * ║    - SerializesModels trait forced DB query on deserialization          ║
 * ║    - Queue worker: SELECT * FROM tenants WHERE id = ? (N+1 on scale)    ║
 * ║                                                                          ║
 * ║  AFTER (Optimized):                                                      ║
 * ║    - Constructor accepts primitives only (UUID tenant, array payload)   ║
 * ║    - No SerializesModels = No DB queries on broadcast                   ║
 * ║    - Payload is pre-computed at dispatch time, not at broadcast time    ║
 * ║                                                                          ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class BusinessProfileUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    // NOTE: SerializesModels REMOVED intentionally - we use primitives only

    /**
     * @param  string  $tenantId  The tenant UUID for channel routing
     * @param  int  $userId  The user ID for private channel targeting
     * @param  array  $payload  Pre-computed data to broadcast (no lazy loading)
     */
    public function __construct(
        public readonly string $tenantId,
        public readonly int|string $userId,
        public readonly array $payload
    ) {}

    /**
     * Get the channels the event should broadcast on.
     * Targeted strictly at the tenant owner.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        // Channel: "user.{id}"
        // Security: Ensures only the owner receives the sensitive profile update
        return [
            new PrivateChannel('user.'.$this->userId),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'business.profile.updated';
    }

    /**
     * Get the data to broadcast.
     *
     * PERFORMANCE: Returns pre-computed payload directly.
     * No model access, no attribute extraction, no DB queries.
     */
    public function broadcastWith(): array
    {
        return $this->payload;
    }

    /**
     * Factory method for creating event from Tenant model.
     *
     * Use this at the dispatch site to extract data BEFORE queueing.
     * This moves the DB read to the main process where it's already loaded.
     *
     * @param  \App\Models\Tenant  $tenant  The tenant model (already loaded)
     */
    public static function fromTenant(\App\Models\Tenant $tenant): self
    {
        return new self(
            tenantId: (string) $tenant->id,
            userId: $tenant->user_id,
            payload: [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'description' => $tenant->description,
                'seo_title' => $tenant->seo_title,
                'seo_description' => $tenant->seo_description,
                'tags' => $tenant->tags,
                'category_suggestion' => $tenant->category_suggestion,
            ]
        );
    }
}
