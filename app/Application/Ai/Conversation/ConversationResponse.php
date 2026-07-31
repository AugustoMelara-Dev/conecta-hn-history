<?php

declare(strict_types=1);

namespace App\Application\Ai\Conversation;

final readonly class ConversationResponse
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  list<array<string, mixed>>  $evidence
     * @param  array<string, mixed>|null  $handoff
     */
    public function __construct(
        public string $conversationId,
        public string $requestMessageId,
        public ?string $responseMessageId,
        public string $status,
        public array $payload,
        public array $evidence = [],
        public ?array $handoff = null,
        public ?string $code = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $compatible = array_replace([
            'answer' => null,
            'items' => [],
            'data' => [],
            'source' => null,
            'fallback' => false,
            'meta' => [],
        ], $this->payload);

        $compatible['conversation_id'] = $this->conversationId;
        $compatible['request_message_id'] = $this->requestMessageId;
        $compatible['response_message_id'] = $this->responseMessageId;
        $compatible['status'] = $this->status;
        $compatible['evidence'] = $this->evidence;
        $compatible['handoff'] = $this->handoff;
        $compatible['code'] = $this->code;

        return $compatible;
    }
}
