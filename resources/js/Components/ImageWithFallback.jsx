/**
 * ImageWithFallback.jsx - Self-Healing Image Component (ENTERPRISE)
 *
 * ARCHITECTURE:
 * - Wraps native <img> with automatic fallback on load failure
 * - Maintains internal state to track load status
 * - Prevents layout shifts with seamless placeholder swap
 * - Polymorphic: accepts all standard img attributes
 * - Supports fade-in animation on load
 *
 * FEATURES:
 * - Automatic fallback to placeholder on error
 * - Optional icon fallback mode (shows icon instead of image)
 * - Skeleton loading state variant
 * - SSR-safe with base64 inline placeholder option
 *
 * USAGE:
 * <ImageWithFallback
 *     src={product.image}
 *     alt="Product"
 *     className="..."
 * />
 *
 * @module Components/ImageWithFallback
 */

import { useState, useCallback, memo } from 'react';
import { ImageOff } from 'lucide-react';

/**
 * Base64 encoded 1x1 transparent PNG for inline placeholder
 * This ensures we always have a valid fallback even without external files
 */
const INLINE_PLACEHOLDER =
    'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

/**
 * Default placeholder path - can be overridden via prop
 */
const DEFAULT_PLACEHOLDER = '/images/placeholder.svg';

/**
 * ImageWithFallback - Resilient image component with automatic fallback
 *
 * @param {Object} props - Component props
 * @param {string} props.src - Primary image source URL
 * @param {string} [props.fallbackSrc] - Fallback image URL
 * @param {boolean} [props.fallbackIcon=false] - Show icon instead of placeholder image
 * @param {string} [props.fallbackColor='#f3f4f6'] - Background color for icon fallback
 * @param {string} props.alt - Alt text (required for accessibility)
 * @param {string} [props.className] - CSS classes
 * @param {string} [props.loading='lazy'] - Loading strategy
 * @param {boolean} [props.fadeIn=false] - Enable fade-in animation on load
 * @param {Object} [props.style] - Inline styles
 *
 * @returns {JSX.Element}
 */
function ImageWithFallback({
    src,
    fallbackSrc = DEFAULT_PLACEHOLDER,
    fallbackIcon = false,
    fallbackColor = '#f3f4f6',
    alt = '',
    className = '',
    loading = 'lazy',
    fadeIn = false,
    style = {},
    ...rest
}) {
    // Track if primary image failed to load
    const [hasError, setHasError] = useState(false);
    // Track if image has successfully loaded (used for fade-in)
    const [isLoaded, setIsLoaded] = useState(false);

    /**
     * Handle image load error - swap to fallback
     * Sets onerror to null to prevent infinite loop if fallback also fails
     */
    const handleError = useCallback((e) => {
        // Prevent infinite error loop
        e.target.onerror = null;
        setHasError(true);

        // If not using icon fallback, try the fallback src
        // If that also fails, use inline base64 placeholder
        if (!fallbackIcon) {
            if (e.target.src !== fallbackSrc && e.target.src !== INLINE_PLACEHOLDER) {
                e.target.src = fallbackSrc;
            } else if (e.target.src !== INLINE_PLACEHOLDER) {
                // Fallback also failed, use inline placeholder
                e.target.src = INLINE_PLACEHOLDER;
            }
        }
    }, [fallbackIcon, fallbackSrc]);

    /**
     * Handle successful image load
     */
    const handleLoad = useCallback(() => {
        setIsLoaded(true);
    }, []);

    // ═══════════════════════════════════════════════════════════════════════
    // RENDER: Icon Fallback Mode
    // Used when we want to show a styled icon instead of placeholder image
    // ═══════════════════════════════════════════════════════════════════════
    if ((hasError && fallbackIcon) || (!src && fallbackIcon)) {
        return (
            <div
                className={`flex items-center justify-center ${className}`}
                style={{ backgroundColor: fallbackColor, ...style }}
                role="img"
                aria-label={alt || 'Imagen no disponible'}
            >
                <ImageOff
                    className="h-12 w-12 text-gray-400 opacity-50"
                    aria-hidden="true"
                />
            </div>
        );
    }

    // ═══════════════════════════════════════════════════════════════════════
    // RENDER: No source provided - show placeholder immediately
    // ═══════════════════════════════════════════════════════════════════════
    if (!src) {
        return (
            <img
                src={fallbackSrc}
                alt={alt}
                className={className}
                loading={loading}
                style={style}
                onError={handleError}
                {...rest}
            />
        );
    }

    // ═══════════════════════════════════════════════════════════════════════
    // RENDER: Standard image with error handling and optional fade-in
    // ═══════════════════════════════════════════════════════════════════════
    const fadeClasses = fadeIn
        ? `transition-opacity duration-300 ${isLoaded ? 'opacity-100' : 'opacity-0'}`
        : '';

    return (
        <img
            src={src}
            alt={alt}
            className={`${className} ${fadeClasses}`.trim()}
            loading={loading}
            style={style}
            onError={handleError}
            onLoad={handleLoad}
            {...rest}
        />
    );
}

// Memoize to prevent unnecessary re-renders
export default memo(ImageWithFallback);

/**
 * ImageWithSkeleton - Variant with skeleton loading state
 * For use cases where we want to show a loading indicator
 */
export const ImageWithSkeleton = memo(function ImageWithSkeleton({
    src,
    fallbackSrc = DEFAULT_PLACEHOLDER,
    alt = '',
    className = '',
    wrapperClassName = '',
    skeletonClassName = '',
    ...rest
}) {
    const [isLoaded, setIsLoaded] = useState(false);
    const [hasError, setHasError] = useState(false);

    const handleLoad = useCallback(() => setIsLoaded(true), []);

    const handleError = useCallback((e) => {
        e.target.onerror = null;
        setHasError(true);
        if (fallbackSrc && e.target.src !== fallbackSrc) {
            e.target.src = fallbackSrc;
        } else if (e.target.src !== INLINE_PLACEHOLDER) {
            e.target.src = INLINE_PLACEHOLDER;
        }
    }, [fallbackSrc]);

    return (
        <div className={`relative overflow-hidden ${wrapperClassName}`}>
            {/* Skeleton placeholder while loading */}
            {!isLoaded && !hasError && (
                <div
                    className={`absolute inset-0 bg-gray-200 animate-pulse ${skeletonClassName}`}
                    aria-hidden="true"
                />
            )}

            <img
                src={src || fallbackSrc}
                alt={alt}
                className={`${className} transition-opacity duration-300 ${isLoaded ? 'opacity-100' : 'opacity-0'}`}
                onLoad={handleLoad}
                onError={handleError}
                {...rest}
            />
        </div>
    );
});
