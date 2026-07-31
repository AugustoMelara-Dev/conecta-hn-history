<?php

declare(strict_types=1);

namespace App\Application\Ai\Conversation;

use App\Application\Ai\Registry\AiRegistryQuery;
use App\Application\Markets\MarketContext;
use Illuminate\Support\Str;
use InvalidArgumentException;
use JsonException;

final readonly class PublicConversationInput
{
    public function __construct(
        public string $message,
        public string $idempotencyKey,
        public string $channel = 'web',
        public ?string $conversationId = null,
        public ?float $budget = null,
        public ?string $city = null,
        public ?string $category = null,
        public string $rateLimitIdentity = 'anonymous',
        public int|string|null $actorId = null,
    ) {
        if (trim($message) === ''
            || mb_strlen($message) > 500
            || trim($idempotencyKey) === ''
            || mb_strlen($idempotencyKey) > 160
            || preg_match('/^[A-Za-z0-9._:-]+$/', $idempotencyKey) !== 1
            || ! in_array($channel, ['web', 'api', 'mobile', 'whatsapp'], true)
            || ($conversationId !== null && ! Str::isUuid($conversationId))
            || ($budget !== null && (! is_finite($budget) || $budget < 0 || $budget > 10_000_000))
            || ($city !== null && preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $city) !== 1)
            || ($category !== null && preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $category) !== 1)
            || trim($rateLimitIdentity) === '') {
            throw new InvalidArgumentException('Public AI conversation input is invalid.');
        }
    }

    public function toAcceptedMessage(string $tenantId, MarketContext $market): AcceptConversationMessage
    {
        return new AcceptConversationMessage(
            tenantId: $tenantId,
            visibility: 'platform_public',
            originType: $this->channel,
            externalThreadReference: null,
            actorId: $this->actorId === null ? null : (string) $this->actorId,
            locale: $market->localeCode,
            marketCountryCode: $market->countryCode,
            currency: $market->currencyCode,
            timezone: $market->timezone,
            content: $this->message,
            contentType: 'text',
            idempotencyKey: $this->idempotencyKey,
            conversationId: $this->conversationId,
            conversationSubjectHash: $this->actorId === null
                ? hash('sha256', 'public-anonymous-subject|'.$this->rateLimitIdentity)
                : null,
            responseInputsHash: $this->responseInputsHash(),
        );
    }

    public function toRegistryQuery(string $tenantId, MarketContext $market): AiRegistryQuery
    {
        return new AiRegistryQuery(
            tenantId: $tenantId,
            audience: 'public',
            capability: 'public_discovery',
            channel: $this->channel,
            marketCode: $market->countryCode,
            residencyClass: $market->countryCode,
            actorId: $this->actorId,
            cohortSubject: $this->actorId === null
                ? hash('sha256', $this->rateLimitIdentity)
                : (string) $this->actorId,
        );
    }

    /**
     * @throws JsonException
     */
    private function responseInputsHash(): string
    {
        return hash('sha256', json_encode([
            'budget' => $this->budget === null ? null : sprintf('%.17g', $this->budget),
            'city' => $this->city,
            'category' => $this->category,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }
}
