<?php

declare(strict_types=1);

namespace App\Exceptions\Ai;

use Exception;

/**
 * Exception thrown when the AI provider (OpenAI) is unreachable or overloaded.
 *
 * This is a SEMANTIC exception wrapping transport errors (timeouts, 500s, 429s).
 * It indicates that the system should pause or queue the operation for later.
 */
final class AiProviderUnavailableException extends Exception
{
    // Custom logic if needed, currently just a semantic wrapper
}
