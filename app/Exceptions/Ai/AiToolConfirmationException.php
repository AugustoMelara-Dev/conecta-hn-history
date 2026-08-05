<?php

declare(strict_types=1);

namespace App\Exceptions\Ai;

use RuntimeException;

final class AiToolConfirmationException extends RuntimeException
{
    public function __construct(
        public readonly string $reason,
        public readonly int $status,
        string $message,
    ) {
        parent::__construct($message);
    }
}
