<?php

declare(strict_types=1);

namespace App\Infrastructure\Channels\Support;

final class IdentityNormalizer
{
    public static function normalize(string $type, string $value): string
    {
        $trimmed = trim($value);

        return match ($type) {
            'email' => mb_strtolower($trimmed),
            'phone_e164' => self::normalizePhone($trimmed),
            default => mb_strtolower($trimmed),
        };
    }

    private static function normalizePhone(string $value): string
    {
        $prefix = str_starts_with($value, '+') ? '+' : '';
        $digits = preg_replace('/\D+/', '', $value) ?? '';

        if ($digits === '') {
            return '';
        }

        return $prefix !== '' ? $prefix.$digits : '+'.$digits;
    }
}
