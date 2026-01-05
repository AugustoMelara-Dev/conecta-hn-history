<?php

declare(strict_types=1);

namespace App\Exceptions\Ai;

use Exception;

/**
 * Exception thrown when the AI provider returns a structurally invalid response.
 *
 * CRITICAL: This prevents corrupt or incomplete data from entering the database.
 * Thrown when required keys are missing from the JSON payload or the JSON is invalid.
 */
final class AiMalformedResponseException extends Exception
{
    // Custom logic if needed
}
