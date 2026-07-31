<?php

declare(strict_types=1);

namespace App\Application\Ai\Conversation;

final readonly class ConversationMessage
{
    /**
     * @param  array<string, mixed>|string|null  $content
     */
    public function __construct(
        public string $id,
        public string $tenantId,
        public string $conversationId,
        public int $sequence,
        public string $direction,
        public string $role,
        public array|string|null $content,
        public string $contentType,
        public string $status,
        public ?string $agentVersionId,
        public ?string $promptVersionId,
        public ?string $providerVersionId,
        public ?string $modelVersionId,
        public ?string $executionId,
    ) {}
}
