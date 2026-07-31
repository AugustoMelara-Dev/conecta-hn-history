<?php

declare(strict_types=1);

namespace App\Application\Ai\Conversation;

use InvalidArgumentException;

final readonly class PublicConversationSubject
{
    public function __construct(
        public string $actorId,
        public ?string $replacementCookie,
    ) {
        if (preg_match('/^[a-f0-9]{64}$/', $actorId) !== 1
            || ($replacementCookie !== null && $replacementCookie === '')) {
            throw new InvalidArgumentException('Public conversation subject is invalid.');
        }
    }
}
