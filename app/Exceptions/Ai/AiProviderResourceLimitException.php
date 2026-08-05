<?php

declare(strict_types=1);

namespace App\Exceptions\Ai;

use RuntimeException;

final class AiProviderResourceLimitException extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct('AI provider exceeded an authorized resource limit.');
    }
}
