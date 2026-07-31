<?php

declare(strict_types=1);

namespace App\Application\Ai\Conversation;

use App\Application\Ai\Registry\AiRegistryQuery;
use App\Application\Markets\MarketContext;
use Illuminate\Support\Str;
use InvalidArgumentException;

final readonly class PrivateConversationInput
{
    public function __construct(
        public string $tenantId,
        public string $actorId,
        public string $message,
        public string $idempotencyKey,
        public string $channel = 'web',
        public ?string $conversationId = null,
    ) {
        if (! Str::isUuid($tenantId)
            || trim($actorId) === ''
            || mb_strlen($actorId) > 64
            || trim($message) === ''
            || mb_strlen($message) > 500
            || trim($idempotencyKey) === ''
            || mb_strlen($idempotencyKey) > 160
            || preg_match('/^[A-Za-z0-9._:-]+$/', $idempotencyKey) !== 1
            || ! in_array($channel, ['web', 'api', 'mobile', 'whatsapp'], true)
            || ($conversationId !== null && ! Str::isUuid($conversationId))) {
            throw new InvalidArgumentException('Private AI conversation input is invalid.');
        }
    }

    public function toAcceptedMessage(MarketContext $market): AcceptConversationMessage
    {
        return new AcceptConversationMessage(
            tenantId: $this->tenantId,
            visibility: 'tenant_private',
            originType: $this->channel,
            externalThreadReference: null,
            actorId: $this->actorId,
            locale: $market->localeCode,
            marketCountryCode: $market->countryCode,
            currency: $market->currencyCode,
            timezone: $market->timezone,
            content: $this->message,
            contentType: 'text',
            idempotencyKey: $this->idempotencyKey,
            conversationId: $this->conversationId,
        );
    }

    public function toRegistryQuery(MarketContext $market): AiRegistryQuery
    {
        return new AiRegistryQuery(
            tenantId: $this->tenantId,
            audience: 'private',
            capability: 'private_tenant_agent',
            channel: $this->channel,
            marketCode: $market->countryCode,
            residencyClass: $market->countryCode,
            actorId: $this->actorId,
            cohortSubject: $this->actorId,
        );
    }
}
