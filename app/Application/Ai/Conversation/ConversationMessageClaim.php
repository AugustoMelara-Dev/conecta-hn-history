<?php

declare(strict_types=1);

namespace App\Application\Ai\Conversation;

final readonly class ConversationMessageClaim
{
    public function __construct(
        public string $conversationId,
        public ConversationMessage $requestMessage,
        public bool $replayed,
        public ?ConversationMessage $response,
    ) {}
}
