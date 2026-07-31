<?php

declare(strict_types=1);

namespace App\Application\Ai\Conversation;

use Illuminate\Support\Str;
use InvalidArgumentException;

final readonly class AcceptConversationMessage
{
    public function __construct(
        public string $tenantId,
        public string $visibility,
        public string $originType,
        public ?string $externalThreadReference,
        public ?string $actorId,
        public string $locale,
        public string $marketCountryCode,
        public string $currency,
        public string $timezone,
        public string $content,
        public string $contentType,
        public string $idempotencyKey,
        public ?string $conversationId = null,
        public ?string $conversationSubjectHash = null,
        public ?string $responseInputsHash = null,
    ) {
        if (! Str::isUuid($tenantId)
            || ($conversationId !== null && ! Str::isUuid($conversationId))
            || ($conversationSubjectHash !== null && preg_match('/^[a-f0-9]{64}$/', $conversationSubjectHash) !== 1)
            || ($responseInputsHash !== null && preg_match('/^[a-f0-9]{64}$/', $responseInputsHash) !== 1)
            || ($conversationSubjectHash !== null && $externalThreadReference !== null)
            || trim($originType) === ''
            || mb_strlen($originType) > 32
            || ($externalThreadReference !== null
                && (trim($externalThreadReference) === '' || mb_strlen($externalThreadReference) > 500))
            || ($actorId !== null && (trim($actorId) === '' || mb_strlen($actorId) > 64))
            || $content === ''
            || mb_strlen($content) > 50_000
            || trim($idempotencyKey) === ''
            || mb_strlen($idempotencyKey) > 160) {
            throw new InvalidArgumentException('AI conversation message is invalid.');
        }
    }
}
