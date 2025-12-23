/**
 * Phone Utilities - Centralized Phone Number Normalization
 * 
 * This module provides a single source of truth for phone number
 * normalization across the application, particularly for WhatsApp integration.
 * 
 * GEOGRAPHIC CONTEXT: Honduras
 * - Country Code: 504
 * - Mobile numbers: 8 digits (starting with 3, 8, 9)
 * - Landlines: 8 digits (starting with 2)
 * 
 * E.164 FORMAT (modified): 504XXXXXXXX (without + symbol for wa.me API)
 */

// Honduras country code
const HONDURAS_COUNTRY_CODE = '504';
const HONDURAS_LOCAL_LENGTH = 8;

/**
 * Normalize a phone number to Honduras E.164 format (without +)
 * 
 * NORMALIZATION PIPELINE:
 * 1. Guard clause: Return null for invalid input
 * 2. Aggressive sanitization: Remove all non-digits
 * 3. Prefix inference: Add 504 if local 8-digit number
 * 4. Validation: Ensure minimum length
 * 
 * @param {string|null|undefined} rawPhone - Raw phone input from database/user
 * @returns {string|null} - Normalized phone number or null if invalid
 * 
 * @example
 * normalizeHonduranPhoneNumber('9999-9999')       // '50499999999'
 * normalizeHonduranPhoneNumber('+504 (99) 88-77') // '50499887766' (if 8 digits after 504)
 * normalizeHonduranPhoneNumber('504 3344 5566')   // '50433445566'
 * normalizeHonduranPhoneNumber('')                // null
 * normalizeHonduranPhoneNumber(null)              // null
 */
export function normalizeHonduranPhoneNumber(rawPhone) {
    // ═══════════════════════════════════════════════════════════════
    // STAGE 1: Guard Clause - Reject invalid inputs immediately
    // ═══════════════════════════════════════════════════════════════
    if (rawPhone === null || rawPhone === undefined) {
        return null;
    }

    if (typeof rawPhone !== 'string') {
        // Try to convert to string if possible
        rawPhone = String(rawPhone);
    }

    if (rawPhone.trim() === '') {
        return null;
    }

    // ═══════════════════════════════════════════════════════════════
    // STAGE 2: Aggressive Sanitization - Remove ALL non-digit characters
    // This handles: spaces, dashes, parentheses, plus signs, etc.
    // ═══════════════════════════════════════════════════════════════
    const cleanedPhone = rawPhone.replace(/\D/g, '');

    // Validate we have something to work with
    if (cleanedPhone.length === 0) {
        return null;
    }

    // ═══════════════════════════════════════════════════════════════
    // STAGE 3: Prefix Inference - Determine if we need to add 504
    // ═══════════════════════════════════════════════════════════════

    // Case A: Exactly 8 digits = Local Honduran number, add 504
    if (cleanedPhone.length === HONDURAS_LOCAL_LENGTH) {
        return `${HONDURAS_COUNTRY_CODE}${cleanedPhone}`;
    }

    // Case B: Starts with 504 = Already has country code
    if (cleanedPhone.startsWith(HONDURAS_COUNTRY_CODE)) {
        // Validate total length (504 + 8 digits = 11)
        if (cleanedPhone.length >= 11) {
            return cleanedPhone;
        }
    }

    // Case C: Longer number that doesn't start with 504
    // Could be international number, return as-is
    if (cleanedPhone.length > HONDURAS_LOCAL_LENGTH) {
        return cleanedPhone;
    }

    // Case D: Too short (less than 8 digits) - Invalid
    if (cleanedPhone.length < HONDURAS_LOCAL_LENGTH) {
        return null;
    }

    // Fallback: Return cleaned number
    return cleanedPhone;
}

/**
 * Build a complete WhatsApp URL with normalized phone and encoded message
 * 
 * @param {string|null} rawPhone - Raw phone number
 * @param {string} message - Message to pre-fill (will be URI encoded)
 * @returns {string|null} - Complete wa.me URL or null if phone is invalid
 * 
 * @example
 * buildWhatsAppUrl('9999-9999', 'Hola!')
 * // Returns: 'https://wa.me/50499999999?text=Hola!'
 */
export function buildWhatsAppUrl(rawPhone, message = '') {
    const normalizedPhone = normalizeHonduranPhoneNumber(rawPhone);

    if (!normalizedPhone) {
        return null;
    }

    // Encode message for URL safety
    const encodedMessage = message ? `?text=${encodeURIComponent(message)}` : '';

    return `https://wa.me/${normalizedPhone}${encodedMessage}`;
}

/**
 * Check if a phone number appears to be valid for WhatsApp
 * 
 * @param {string|null} rawPhone - Raw phone number
 * @returns {boolean} - True if phone normalizes to a valid number
 */
export function isValidWhatsAppPhone(rawPhone) {
    const normalized = normalizeHonduranPhoneNumber(rawPhone);
    return normalized !== null && normalized.length >= 11;
}

/**
 * Format a normalized phone for display (human-readable)
 * 
 * @param {string} normalizedPhone - Already normalized phone number
 * @returns {string} - Formatted phone like "+504 9999-9999"
 */
export function formatPhoneForDisplay(normalizedPhone) {
    if (!normalizedPhone || normalizedPhone.length < 11) {
        return normalizedPhone || '';
    }

    // Format: +504 9999-9999
    const countryCode = normalizedPhone.substring(0, 3);
    const localPart = normalizedPhone.substring(3);
    const formattedLocal = localPart.replace(/(\d{4})(\d{4})/, '$1-$2');

    return `+${countryCode} ${formattedLocal}`;
}

// Default export for convenience
export default {
    normalizeHonduranPhoneNumber,
    buildWhatsAppUrl,
    isValidWhatsAppPhone,
    formatPhoneForDisplay,
};
