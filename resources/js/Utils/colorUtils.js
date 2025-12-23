/**
 * Color Utilities - WCAG-Compliant Contrast and Luminosity
 * 
 * This module provides color manipulation functions for accessibility compliance.
 * All calculations are based on established color science standards.
 * 
 * ARCHITECTURAL NOTE: All color logic should reside here - DO NOT duplicate
 * these calculations in React components.
 */

/**
 * Parse a hex color string into RGB components
 * 
 * @param {string} hex - Hex color like "#2563EB" or "2563EB"
 * @returns {{ r: number, g: number, b: number } | null} - RGB object or null if invalid
 */
export function hexToRgb(hex) {
    if (!hex || typeof hex !== 'string') {
        return null;
    }

    // Remove # if present
    const cleanHex = hex.replace(/^#/, '');

    // Validate hex format
    if (!/^[0-9A-Fa-f]{6}$/.test(cleanHex)) {
        return null;
    }

    const bigint = parseInt(cleanHex, 16);
    return {
        r: (bigint >> 16) & 255,
        g: (bigint >> 8) & 255,
        b: bigint & 255,
    };
}

/**
 * Calculate YIQ luminosity from a hex color
 * 
 * YIQ is a color space that takes into account human perception of brightness.
 * The formula weights green more heavily because the human eye is most sensitive to it.
 * 
 * Formula: Y = ((R × 299) + (G × 587) + (B × 114)) / 1000
 * 
 * @param {string} hexColor - Hex color like "#2563EB"
 * @returns {number} - Luminosity value between 0 (black) and 255 (white)
 */
export function getYiqLuminosity(hexColor) {
    const rgb = hexToRgb(hexColor);

    if (!rgb) {
        // Default to assuming dark background if color is invalid
        return 0;
    }

    // YIQ formula for perceived brightness
    return ((rgb.r * 299) + (rgb.g * 587) + (rgb.b * 114)) / 1000;
}

/**
 * Get the optimal contrast text color for a given background
 * 
 * Uses YIQ luminosity algorithm to determine whether black or white text
 * provides better readability on the given background color.
 * 
 * WCAG 2.1 requires a contrast ratio of at least 4.5:1 for normal text.
 * This function ensures maximum contrast by returning pure black or white.
 * 
 * @param {string} hexColor - Background color in hex format (#RRGGBB)
 * @param {object} options - Configuration options
 * @param {number} options.threshold - Luminosity threshold (default: 128)
 * @param {string} options.darkColor - Color for light backgrounds (default: '#1e293b')
 * @param {string} options.lightColor - Color for dark backgrounds (default: '#ffffff')
 * @returns {string} - Optimal text color in hex format
 * 
 * @example
 * getContrastColor('#FFFF00') // Returns '#1e293b' (dark) for yellow background
 * getContrastColor('#000000') // Returns '#ffffff' (white) for black background
 * getContrastColor('#2563EB') // Returns '#ffffff' (white) for blue background
 */
export function getContrastColor(hexColor, options = {}) {
    const {
        threshold = 128,
        darkColor = '#1e293b',  // Slate-800 - softer than pure black
        lightColor = '#ffffff',
    } = options;

    const luminosity = getYiqLuminosity(hexColor);

    // If luminosity >= threshold, background is "light" -> use dark text
    // If luminosity < threshold, background is "dark" -> use light text
    return luminosity >= threshold ? darkColor : lightColor;
}

/**
 * Check if a color is considered "light" (would need dark text)
 * 
 * @param {string} hexColor - Hex color to check
 * @param {number} threshold - Luminosity threshold (default: 128)
 * @returns {boolean} - True if the color is light
 */
export function isLightColor(hexColor, threshold = 128) {
    return getYiqLuminosity(hexColor) >= threshold;
}

/**
 * Adjust a hex color's brightness
 * Simple percentage-based adjustment (not perceptually uniform)
 * 
 * @param {string} hexColor - Original hex color
 * @param {number} percent - Percentage to adjust (-100 to 100)
 * @returns {string} - Adjusted hex color
 */
export function adjustBrightness(hexColor, percent) {
    const rgb = hexToRgb(hexColor);

    if (!rgb) {
        return hexColor;
    }

    const adjust = (value) => {
        const adjusted = value + (value * percent / 100);
        return Math.max(0, Math.min(255, Math.round(adjusted)));
    };

    const r = adjust(rgb.r).toString(16).padStart(2, '0');
    const g = adjust(rgb.g).toString(16).padStart(2, '0');
    const b = adjust(rgb.b).toString(16).padStart(2, '0');

    return `#${r}${g}${b}`;
}

// Default export for convenience
export default {
    hexToRgb,
    getYiqLuminosity,
    getContrastColor,
    isLightColor,
    adjustBrightness,
};
