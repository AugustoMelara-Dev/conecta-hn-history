<?php

declare(strict_types=1);

namespace App\Application\Ai\Conversation;

final readonly class ConversationContinuationContext
{
    public function __construct(
        public string $tenantId,
        public string $conversationId,
        public string $actorId,
        public string $originType,
        public ?string $externalThreadHash,
        public string $locale,
        public string $marketCountryCode,
        public string $currency,
        public string $timezone,
    ) {}
}
