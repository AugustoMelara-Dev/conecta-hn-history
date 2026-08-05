<?php

declare(strict_types=1);

namespace App\Exceptions\Ai;

use RuntimeException;

final class AiCircuitOpenException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('AI provider circuit is open.');
    }
}
