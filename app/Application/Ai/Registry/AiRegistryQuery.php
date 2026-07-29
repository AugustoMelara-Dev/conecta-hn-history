<?php

declare(strict_types=1);

namespace App\Application\Ai\Registry;

use Illuminate\Support\Str;
use InvalidArgumentException;

final readonly class AiRegistryQuery
{
    public function __construct(
        public ?string $tenantId,
        public string $audience,
        public string $capability,
        public string $channel,
        public string $marketCountryCode,
        public int|string|null $actorId,
        public string $cohortSubject,
    ) {
        if ($tenantId !== null && ! Str::isUuid($tenantId)) {
            throw new InvalidArgumentException('AI registry tenant ID must be a UUID.');
        }

        if (! in_array($audience, ['public', 'private', 'internal'], true)) {
            throw new InvalidArgumentException('AI registry audience is invalid.');
        }

        if (! preg_match('/^[a-z][a-z0-9_.-]{1,63}$/', $capability)) {
            throw new InvalidArgumentException('AI registry capability is invalid.');
        }

        if (! preg_match('/^[a-z][a-z0-9_.-]{1,31}$/', $channel)) {
            throw new InvalidArgumentException('AI registry channel is invalid.');
        }

        if (! preg_match('/^[A-Z]{2}$/', $marketCountryCode)) {
            throw new InvalidArgumentException('AI registry market country code is invalid.');
        }

        if (trim($cohortSubject) === '' || mb_strlen($cohortSubject) > 255) {
            throw new InvalidArgumentException('AI registry cohort subject is invalid.');
        }
    }
}
