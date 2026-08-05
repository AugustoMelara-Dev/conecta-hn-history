<?php

declare(strict_types=1);

namespace App\Exceptions\Ai;

use RuntimeException;

final class AiExecutionDeniedException extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct('AI execution denied.');
    }
}
