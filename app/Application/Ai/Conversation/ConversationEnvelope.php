<?php

declare(strict_types=1);

namespace App\Application\Ai\Conversation;

final readonly class ConversationEnvelope
{
    public function __construct(
        public string $tenantId,
        public string $visibility,
        public string $originType,
        public ?string $externalThreadHash,
        public ?string $actorId,
        public string $locale,
        public string $marketCountryCode,
        public string $currency,
        public string $timezone,
        public string $content,
        public string $contentType,
        public string $idempotencyKey,
        public ?string $conversationId,
        public ?string $responseInputsHash,
    ) {}

    public static function fromCommand(AcceptConversationMessage $command): self
    {
        return new self(
            tenantId: strtolower($command->tenantId),
            visibility: $command->visibility,
            originType: $command->originType,
            externalThreadHash: $command->conversationSubjectHash
                ?? ($command->externalThreadReference === null
                    ? null
                    : hash('sha256', $command->externalThreadReference)),
            actorId: $command->actorId,
            locale: $command->locale,
            marketCountryCode: $command->marketCountryCode,
            currency: $command->currency,
            timezone: $command->timezone,
            content: str_replace(["\r\n", "\r"], "\n", $command->content),
            contentType: $command->contentType,
            idempotencyKey: $command->idempotencyKey,
            conversationId: $command->conversationId === null
                ? null
                : strtolower($command->conversationId),
            responseInputsHash: $command->responseInputsHash,
        );
    }

    /** @return array<string, string|null> */
    public function canonicalPayload(string $conversationId): array
    {
        $payload = [
            'tenant_id' => $this->tenantId,
            'conversation_id' => $conversationId,
            'visibility' => $this->visibility,
            'origin_type' => $this->originType,
            'external_thread_hash' => $this->externalThreadHash,
            'actor_id' => $this->actorId,
            'locale' => $this->locale,
            'market_country_code' => $this->marketCountryCode,
            'currency' => $this->currency,
            'timezone' => $this->timezone,
            'content' => $this->content,
            'content_type' => $this->contentType,
            'idempotency_key' => $this->idempotencyKey,
        ];

        if ($this->responseInputsHash !== null) {
            $payload['response_inputs_hash'] = $this->responseInputsHash;
        }

        return $payload;
    }
}
