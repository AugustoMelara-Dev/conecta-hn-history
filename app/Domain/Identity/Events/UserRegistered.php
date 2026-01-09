<?php

declare(strict_types=1);

namespace App\Domain\Identity\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * UserRegistered - Domain Event for User Registration
 *
 * Implements ShouldDispatchAfterCommit for Transactional Outbox safety.
 * Replaces Illuminate\Auth\Events\Registered for at-least-once delivery.
 */
final readonly class UserRegistered implements ShouldDispatchAfterCommit
{
    public function __construct(
        public int $userId,
        public int $tenantId,
        public string $email,
    ) {}

    /**
     * Serialize to safe payload for Outbox storage.
     *
     * @return array<string, mixed>
     */
    public function toPayload(): array
    {
        return [
            'user_id' => $this->userId,
            'tenant_id' => $this->tenantId,
            'email' => $this->email,
            'occurred_at' => now()->toIso8601String(),
        ];
    }
}
