<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Tenant;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when AI generation completes.
 * Carries the full payload to "hydrate" the frontend reactively.
 */
final class BusinessProfileUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param Tenant $tenant The fully updated tenant model
     */
    public function __construct(
        public readonly Tenant $tenant
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
            new PrivateChannel('user.' . $this->tenant->user_id),
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
     * Ensure we send the new SEO fields.
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->tenant->id,
            'name' => $this->tenant->name,
            'description' => $this->tenant->description,
            'seo_title' => $this->tenant->seo_title,
            'seo_description' => $this->tenant->seo_description,
            'tags' => $this->tenant->tags,
            'category_suggestion' => $this->tenant->category_suggestion,
        ];
    }
}
