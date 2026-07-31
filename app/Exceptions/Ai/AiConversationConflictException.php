<?php

declare(strict_types=1);

namespace App\Exceptions\Ai;

use RuntimeException;

final class AiConversationConflictException extends RuntimeException
{
    public function __construct(public readonly string $publicCode = 'ai_request_conflict')
    {
        parent::__construct('AI conversation request conflicts with an existing request.');
    }
}
