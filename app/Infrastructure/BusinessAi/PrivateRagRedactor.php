<?php

declare(strict_types=1);

namespace App\Infrastructure\BusinessAi;

final class PrivateRagRedactor
{
    public function redactText(string $value): string
    {
        $value = strip_tags($value);
        $value = preg_replace('/\b[\w.%+-]+@[\w.-]+\.[A-Za-z]{2,}\b/', '[email]', $value) ?? $value;
        $value = preg_replace('/\+?\d[\d\s().-]{7,}\d/', '[phone]', $value) ?? $value;
        $value = preg_replace('/\b(?:access|refresh|api|secret|bearer|token|password|passwd|pwd)[ _-]?(?:token|key|secret|password)?\s*[:=]\s*[^\s,;]+/i', '[secret]', $value) ?? $value;
        $value = preg_replace('/\b(?:direccion|address|ubicacion|location)\s*[:=]\s*[^.;\n]{8,}/i', '[address]', $value) ?? $value;

        return trim($value);
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    public function redactMetadata(array $metadata): array
    {
        $denyFields = collect((array) config('ai-rag.redaction.deny_fields', []))
            ->map(static fn (mixed $field): string => mb_strtolower((string) $field))
            ->filter()
            ->values()
            ->all();

        foreach ($metadata as $key => $value) {
            $normalizedKey = mb_strtolower((string) $key);
            $shouldDeny = in_array($normalizedKey, $denyFields, true)
                || str_contains($normalizedKey, 'token')
                || str_contains($normalizedKey, 'secret')
                || str_contains($normalizedKey, 'password');

            if ($shouldDeny) {
                $metadata[$key] = '[redacted]';

                continue;
            }

            if (is_string($value)) {
                $metadata[$key] = $this->redactText($value);
            }

            if (is_array($value)) {
                $metadata[$key] = $this->redactMetadata($value);
            }
        }

        return $metadata;
    }
}
