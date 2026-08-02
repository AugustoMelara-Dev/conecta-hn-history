<?php

declare(strict_types=1);

namespace App\Application\Channels;

use Illuminate\Support\Str;
use InvalidArgumentException;

final readonly class ChannelAuthorizationEnvelope
{
    public function __construct(
        public string $tenantId,
        public string $actorId,
        public string $scope = 'channels',
    ) {
        if (! Str::isUuid($tenantId)
            || ! Str::isUuid($actorId)
            || ! preg_match('/^[a-z][a-z0-9_.-]{1,63}$/', $scope)) {
            throw new InvalidArgumentException('Channel authorization envelope is invalid.');
        }
    }
}
