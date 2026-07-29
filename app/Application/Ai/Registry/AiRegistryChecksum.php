<?php

declare(strict_types=1);

namespace App\Application\Ai\Registry;

use JsonException;

final class AiRegistryChecksum
{
    /** @param array<string, mixed> $canonicalPayload */
    public function for(array $canonicalPayload): string
    {
        return hash('sha256', json_encode(
            $this->normalize($canonicalPayload),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        ));
    }

    /** @param array<string|int, mixed> $value */
    private function normalize(array $value): array
    {
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->normalize($item);
            }
        }

        if (! array_is_list($value)) {
            ksort($value);
        }

        return $value;
    }
}
