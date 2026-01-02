<?php

declare(strict_types=1);

namespace App\Traits;

/**
 * ParsesUserAgent - Reusable Browser Detection Logic
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  LOGIC:                                                                 ║
 * ║    - Detects browser name from User-Agent string                        ║
 * ║    - Ordered by specificity (Edge -> Chrome -> Firefox -> Safari)       ║
 * ║    - Centralized logic to avoid duplication across events               ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
trait ParsesUserAgent
{
    /**
     * Parse user agent to extract readable device/browser name.
     *
     * IMPORTANT: Order matters! Check in order of specificity:
     *   1. Edge (contains both "Chrome" and "Safari" in UA)
     *   2. Chrome (contains "Safari" in UA)
     *   3. Firefox
     *   4. Safari (pure Safari)
     *   5. Opera
     */
    protected function parseUserAgent(?string $userAgent): ?string
    {
        if ($userAgent === null || $userAgent === '') {
            return null;
        }

        // Edge detection - most specific first
        // Edge uses "Edg/" in UA (not "Edge" - that's legacy)
        if (str_contains($userAgent, 'Edg/') || str_contains($userAgent, 'Edge/')) {
            return 'Edge';
        }

        // Opera detection
        if (str_contains($userAgent, 'OPR/') || str_contains($userAgent, 'Opera/')) {
            return 'Opera';
        }

        // Chrome detection - Chrome contains "Safari" but not vice versa
        if (str_contains($userAgent, 'Chrome/') || str_contains($userAgent, 'CriOS/')) {
            return 'Chrome';
        }

        // Firefox detection
        if (str_contains($userAgent, 'Firefox/') || str_contains($userAgent, 'FxiOS/')) {
            return 'Firefox';
        }

        // Safari detection - only if no Chrome/Edge indicators
        if (str_contains($userAgent, 'Safari/')) {
            return 'Safari';
        }

        return 'Unknown Browser';
    }
}
