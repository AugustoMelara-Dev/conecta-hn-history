<?php

declare(strict_types=1);

namespace App\Application\Security;

/**
 * InputSanitizer - Sanitización de inputs de usuario.
 *
 * - stripHtml(): strip dangerous HTML tags
 * - validateUrl(): check URL no apunta a scripts
 * - escapeCsvFormula(): previene CSV formula injection
 */
class InputSanitizer
{
    /**
     * Strip dangerous HTML tags, conservando format básico.
     */
    public function stripHtml(string $html): string
    {
        $allowed = '<p><br><strong><b><em><i><u><a><span><h1><h2><h3><h4><h5><h6><ul><ol><li>';
        $clean = strip_tags($html, $allowed);
        // Remove event handlers
        $clean = preg_replace('/\s*on\w+\s*=\s*["\'][^"\']*["\']/i', '', $clean) ?? '';
        // Remove javascript:/data: URIs
        $clean = preg_replace('/(javascript|data|vbscript)\s*:/i', '', $clean) ?? '';
        return trim($clean);
    }

    /**
     * Validate URL: rechaza javascript:, data:, vbscript: protocols.
     */
    public function validateUrl(string $url): bool
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }
        $scheme = strtolower(parse_url($url, PHP_URL_SCHEME) ?? '');
        return in_array($scheme, ['http', 'https', 'mailto', 'tel'], true);
    }

    /**
     * Escape CSV formula injection prefixes (=, +, -, @).
     */
    public function escapeCsvFormula(string $value): string
    {
        $first = substr($value, 0, 1);
        if (in_array($first, ['=', '+', '-', '@'], true)) {
            return "'" . $value;
        }
        return $value;
    }
}
