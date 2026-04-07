<?php

declare(strict_types=1);

namespace App\Support\Ui;

final class AvatarPlaceholder
{
    /** @var list<string> */
    private const BACKGROUNDS = [
        '#14532d',
        '#1d4ed8',
        '#7c2d12',
        '#5b21b6',
        '#0f766e',
        '#9f1239',
        '#334155',
    ];

    public static function forName(string $name): string
    {
        $cleanName = trim($name) !== '' ? trim($name) : 'CN';
        $initials = self::initials($cleanName);
        $background = self::backgroundFor($cleanName);

        $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 120 120" role="img" aria-label="{$initials}">
  <rect width="120" height="120" rx="24" fill="{$background}" />
  <text x="50%" y="50%" dy=".35em" text-anchor="middle" fill="#ffffff" font-family="Inter, Arial, sans-serif" font-size="40" font-weight="700">{$initials}</text>
</svg>
SVG;

        return 'data:image/svg+xml;charset=UTF-8,'.rawurlencode($svg);
    }

    private static function initials(string $name): string
    {
        $parts = preg_split('/\s+/', $name) ?: [];
        $letters = '';

        foreach (array_slice($parts, 0, 2) as $part) {
            $letters .= mb_strtoupper(mb_substr($part, 0, 1));
        }

        return $letters !== '' ? $letters : 'CN';
    }

    private static function backgroundFor(string $name): string
    {
        $index = abs(crc32(mb_strtolower($name))) % count(self::BACKGROUNDS);

        return self::BACKGROUNDS[$index];
    }
}
