<?php

declare(strict_types=1);

namespace App\Application\Ai\Evaluation;

use JsonException;

final class EvaluationChecksum
{
    /** @throws JsonException */
    public static function hash(mixed $value): string
    {
        return hash('sha256', json_encode(
            self::sort($value),
            JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        ));
    }

    private static function sort(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        if (array_is_list($value)) {
            return array_map(self::sort(...), $value);
        }
        ksort($value);
        foreach ($value as $key => $item) {
            $value[$key] = self::sort($item);
        }

        return $value;
    }
}
