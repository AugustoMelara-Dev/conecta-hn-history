<?php

declare(strict_types=1);

namespace App\Domain\Identity\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * SocialIdentityLinked - Domain Event for Account Linking
 *
 * Fired when an existing user links a new social identity.
 * Used for security audit and notifications.
 */
final readonly class SocialIdentityLinked implements ShouldDispatchAfterCommit
{
    public function __construct(
        public int $userId,
        public string $providerName,
        public \DateTimeImmutable $occurredAt,
    ) {}

    /**
     * Create from context.
     */
    public static function create(int $userId, string $providerName): self
    {
        return new self(
            userId: $userId,
            providerName: $providerName,
            occurredAt: new \DateTimeImmutable(),
        );
    }

    /**
     * Serialize to safe payload for Outbox storage.
     *
     * @return array<string, mixed>
     */
    public function toPayload(): array
    {
        return [
            'user_id' => $this->userId,
            'provider_name' => $this->providerName,
            'occurred_at' => $this->occurredAt->format(\DATE_ATOM),
        ];
    }
}
