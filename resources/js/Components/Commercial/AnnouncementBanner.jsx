import { usePage } from '@inertiajs/react';
import { useState, useEffect, useMemo, useRef, useCallback } from 'react';

/**
 * AnnouncementBanner - Enterprise-Grade Component (v12 - Scalability Optimized)
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  MODULE B: ENV-DRIVEN CONFIG - No hardcoded domains                     ║
 * ║  MODULE C: CLS PROTECTION - Inline styles for layout stability          ║
 * ║  MODULE D: PRODUCTION LOGGING - Sentry/NewRelic integration             ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * ARCHITECTURE:
 * ─────────────────────────────────────────────────────────────────────────────
 * 1. HEURISTIC LOADING
 *    - Regex detects if content contains HTML tags
 *    - Plain text renders without DOMPurify (saves 15kb+)
 *
 * 2. TWELVE-FACTOR COMPLIANCE
 *    - Trusted domains from VITE_ALLOWED_IMAGE_DOMAINS env var
 *    - No hardcoded external dependencies
 *
 * 3. CLS MITIGATION
 *    - Inline styles enforce max-height, width constraints
 *    - Core Web Vitals score protected
 *
 * 4. OBSERVABILITY
 *    - Security events routed to Sentry/NewRelic/Bugsnag
 *    - Console.warn only in development mode
 */

/** @constant {number} Maximum content length (DoS mitigation) */
const MAX_CONTENT_LENGTH = 5000;

/** @constant {number} LocalStorage key version */
const STORAGE_VERSION = 1;

/** @constant {RegExp} Detects HTML tags in content */
const HTML_TAG_REGEX = /<[a-z][\s\S]*>/i;

/** @constant {number} Max image height for CLS protection (px) */
const MAX_IMAGE_HEIGHT = 300;

// ═══════════════════════════════════════════════════════════════════════════════
// MODULE B: ENVIRONMENT-DRIVEN TRUSTED ORIGINS (Twelve-Factor Compliance)
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Parse trusted image domains from environment variable.
 * Supports comma-separated list with whitespace trimming.
 *
 * ENV: VITE_ALLOWED_IMAGE_DOMAINS=res.cloudinary.com,images.unsplash.com
 */
const parseTrustedDomains = () => {
    const envDomains = import.meta.env.VITE_ALLOWED_IMAGE_DOMAINS || '';
    const cdnUrl = import.meta.env.VITE_CDN_URL || '';
    const currentOrigin = typeof window !== 'undefined' ? window.location.origin : '';

    // Parse comma-separated domains from env
    const parsedDomains = envDomains
        .split(',')
        .map(d => d.trim())
        .filter(Boolean)
        .map(d => d.startsWith('https://') ? d : `https://${d}`);

    // Merge with CDN and current origin
    return [
        cdnUrl,
        currentOrigin,
        ...parsedDomains,
    ].filter(Boolean);
};

/** @constant {string[]} Trusted image origins from environment */
const TRUSTED_IMAGE_ORIGINS = parseTrustedDomains();

// ═══════════════════════════════════════════════════════════════════════════════
// MODULE D: PRODUCTION-GRADE OBSERVABILITY
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Log security event to production monitoring tools.
 *
 * HIERARCHY:
 * 1. Sentry (if available)
 * 2. NewRelic (if available)
 * 3. Bugsnag (if available)
 * 4. console.warn (DEV only)
 */
const logSecurityEvent = (message, data = {}) => {
    const eventData = {
        component: 'AnnouncementBanner',
        timestamp: new Date().toISOString(),
        ...data,
    };

    // Priority 1: Sentry
    if (typeof window !== 'undefined' && window.Sentry) {
        window.Sentry.captureMessage(message, {
            level: 'warning',
            tags: { security: true },
            extra: eventData,
        });
        return;
    }

    // Priority 2: NewRelic
    if (typeof window !== 'undefined' && window.newrelic) {
        window.newrelic.noticeError(new Error(message), eventData);
        return;
    }

    // Priority 3: Bugsnag
    if (typeof window !== 'undefined' && window.Bugsnag) {
        window.Bugsnag.notify(new Error(message), event => {
            event.addMetadata('security', eventData);
        });
        return;
    }

    // Fallback: Console (DEV only to keep production console clean)
    if (import.meta.env.DEV) {
        console.warn(`[SecurityEvent] ${message}`, eventData);
    }
};

// ═══════════════════════════════════════════════════════════════════════════════
// UTILITY FUNCTIONS
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Check if content contains HTML tags.
 */
const containsHtml = (content) => {
    if (!content || typeof content !== 'string') return false;
    return HTML_TAG_REGEX.test(content);
};

/**
 * Check if image source is from trusted CDN.
 */
const isTrustedImageSource = (src) => {
    if (!src) return false;
    return TRUSTED_IMAGE_ORIGINS.some(origin => src.startsWith(origin));
};

/**
 * Build versioned storage key.
 */
const buildStorageKey = (tenantId, announcementId) => {
    return `dismissed:v${STORAGE_VERSION}:tenant:${tenantId}:announcement:${announcementId}`;
};

/**
 * Create secure DOMPurify instance with marketing + CLS protection.
 * @param {Object} DOMPurifyModule - The imported DOMPurify module
 * @returns {Object} Configured purifier instance
 */
const createSecurePurifier = (DOMPurifyModule) => {
    const DOMPurify = DOMPurifyModule.default || DOMPurifyModule;
    const purifier = DOMPurify(window);

    // MARKETING ENABLED: Allow rich formatting
    purifier.setConfig({
        ALLOWED_TAGS: [
            'p', 'br', 'strong', 'b', 'em', 'i', 'u', 'a', 'span',
            // Marketing additions:
            'img', 'ul', 'ol', 'li', 'h1', 'h2', 'h3', 'h4',
        ],
        ALLOWED_ATTR: ['href', 'target', 'rel', 'src', 'alt', 'class'],
        ALLOW_DATA_ATTR: false,
    });

    // SECURITY + CLS PROTECTION hook
    purifier.addHook('afterSanitizeAttributes', (node) => {
        // Links: Force security attributes
        if (node.tagName === 'A') {
            node.setAttribute('rel', 'noopener noreferrer');
            node.setAttribute('target', '_blank');
        }

        // Images: CDN policy + CLS protection
        if (node.tagName === 'IMG') {
            const src = node.getAttribute('src') || '';

            // SECURITY: Remove untrusted images
            if (!isTrustedImageSource(src)) {
                logSecurityEvent('Untrusted Image Blocked', {
                    blockedSrc: src,
                    trustedOrigins: TRUSTED_IMAGE_ORIGINS,
                });
                node.remove();
                return;
            }

            // ═══════════════════════════════════════════════════════════════
            // MODULE C: CLS PROTECTION - Inline Styles
            // Prevents layout shift from user-generated images
            // ═══════════════════════════════════════════════════════════════
            node.style.maxHeight = `${MAX_IMAGE_HEIGHT}px`;
            node.style.width = 'auto';
            node.style.objectFit = 'contain';
            node.style.display = 'block';
            node.style.margin = '0 auto'; // Center images

            // Performance: Lazy loading
            node.setAttribute('loading', 'lazy');
        }
    });

    return purifier;
};

export default function AnnouncementBanner() {
    const { announcements, tenant } = usePage().props;
    const [isDismissed, setIsDismissed] = useState(false);
    const [purifier, setPurifier] = useState(null);
    const [isLoading, setIsLoading] = useState(false);
    const isMounted = useRef(true);

    // Business decision: Only show highest priority announcement
    const announcement = announcements?.[0] || null;
    const tenantId = tenant?.id;
    const content = announcement?.content_html || '';

    // HEURISTIC: Determine if content needs sanitization
    const needsSanitization = useMemo(() => containsHtml(content), [content]);

    // FAST PATH: Plain text content (no HTML)
    const plainTextContent = useMemo(() => {
        if (!content || needsSanitization) return null;

        // DoS check
        if (content.length > MAX_CONTENT_LENGTH) return null;

        return content;
    }, [content, needsSanitization]);

    // SLOW PATH: Load DOMPurify only when HTML detected
    useEffect(() => {
        isMounted.current = true;

        // Skip if no announcement, already loaded, or plain text
        if (!announcement || purifier || !needsSanitization) {
            return;
        }

        setIsLoading(true);

        const loadPurifier = async () => {
            try {
                const DOMPurifyModule = await import('dompurify');

                if (isMounted.current) {
                    const instance = createSecurePurifier(DOMPurifyModule);
                    setPurifier(instance);
                }
            } catch (error) {
                logSecurityEvent('DOMPurify Load Failed', { error: error.message });
            } finally {
                if (isMounted.current) {
                    setIsLoading(false);
                }
            }
        };

        loadPurifier();

        return () => {
            isMounted.current = false;
        };
    }, [announcement, needsSanitization, purifier]);

    // Check localStorage for dismissal
    useEffect(() => {
        if (typeof window === 'undefined') return;
        if (!announcement?.id || !tenantId) return;

        const storageKey = buildStorageKey(tenantId, announcement.id);

        try {
            const wasDismissed = localStorage.getItem(storageKey) === 'true';
            if (isMounted.current) {
                setIsDismissed(wasDismissed);
            }
        } catch {
            // localStorage blocked
        }
    }, [announcement?.id, tenantId]);

    // SLOW PATH: Sanitized HTML content
    const sanitizedContent = useMemo(() => {
        if (!needsSanitization || !purifier || !content) return null;

        // DoS check
        if (content.length > MAX_CONTENT_LENGTH) {
            logSecurityEvent('Content Exceeds Max Length', {
                contentLength: content.length,
                maxLength: MAX_CONTENT_LENGTH,
            });
            return null;
        }

        return purifier.sanitize(content);
    }, [content, purifier, needsSanitization]);

    // Handle dismiss
    const handleDismiss = useCallback(() => {
        const currentId = announcement?.id;
        const currentTenantId = tenantId;

        if (typeof window !== 'undefined' && currentTenantId && currentId) {
            const storageKey = buildStorageKey(currentTenantId, currentId);
            try {
                localStorage.setItem(storageKey, 'true');
            } catch {
                // Storage blocked
            }
        }

        setIsDismissed(true);
    }, [announcement?.id, tenantId]);

    // Render conditions
    if (!announcement || isDismissed) return null;

    // Loading state for HTML content
    if (needsSanitization && isLoading) {
        return (
            <div className="bg-gray-100 py-2.5 px-4 animate-pulse">
                <div className="max-w-7xl mx-auto h-5 bg-gray-200 rounded" />
            </div>
        );
    }

    // Neither plain text nor sanitized HTML ready
    if (!plainTextContent && !sanitizedContent) return null;

    const getTypeStyles = (type) => {
        const styles = {
            info: { container: 'bg-blue-600 text-white', icon: <InfoIcon /> },
            warning: { container: 'bg-amber-500 text-amber-900', icon: <WarningIcon /> },
            promo: { container: 'bg-gradient-to-r from-emerald-500 to-teal-700 text-white', icon: <GiftIcon /> },
            urgent: { container: 'bg-red-600 text-white', icon: <AlertIcon /> },
        };
        return styles[type] || { container: 'bg-gray-700 text-white', icon: null };
    };

    const styles = getTypeStyles(announcement.type);

    return (
        <div
            className={`${styles.container} py-2.5 px-4 relative`}
            role="alert"
            aria-live="polite"
        >
            <div className="max-w-7xl mx-auto flex items-center justify-center gap-3 pr-8">
                {styles.icon}

                {/* FAST PATH: Plain text (no dangerouslySetInnerHTML) */}
                {plainTextContent && (
                    <p className="text-sm font-medium leading-snug">
                        {plainTextContent}
                    </p>
                )}

                {/* SLOW PATH: Sanitized HTML */}
                {sanitizedContent && (
                    <div
                        className="text-sm font-medium leading-snug announcement-content"
                        dangerouslySetInnerHTML={{ __html: sanitizedContent }}
                    />
                )}

                <button
                    onClick={handleDismiss}
                    className="absolute right-3 top-1/2 -translate-y-1/2 p-1.5 rounded-full hover:bg-black/10 transition-colors focus:outline-none focus:ring-2 focus:ring-white/50"
                    aria-label="Cerrar anuncio"
                    type="button"
                >
                    <CloseIcon />
                </button>
            </div>
        </div>
    );
}

// ═══════════════════════════════════════════════════════════════════════════════
// ICON COMPONENTS
// ═══════════════════════════════════════════════════════════════════════════════

function InfoIcon() {
    return (
        <svg className="w-5 h-5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
            <path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clipRule="evenodd" />
        </svg>
    );
}

function WarningIcon() {
    return (
        <svg className="w-5 h-5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
            <path fillRule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clipRule="evenodd" />
        </svg>
    );
}

function GiftIcon() {
    return (
        <svg className="w-5 h-5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
            <path fillRule="evenodd" d="M5 5a3 3 0 015-2.236A3 3 0 0114.83 6H16a2 2 0 110 4h-5V9a1 1 0 10-2 0v1H4a2 2 0 110-4h1.17C5.06 5.687 5 5.35 5 5zm4 1V5a1 1 0 10-1 1h1zm3 0a1 1 0 10-1-1v1h1z" clipRule="evenodd" />
            <path d="M9 11H3v5a2 2 0 002 2h4v-7zM11 18h4a2 2 0 002-2v-5h-6v7z" />
        </svg>
    );
}

function AlertIcon() {
    return (
        <svg className="w-5 h-5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
            <path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clipRule="evenodd" />
        </svg>
    );
}

function CloseIcon() {
    return (
        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
        </svg>
    );
}
