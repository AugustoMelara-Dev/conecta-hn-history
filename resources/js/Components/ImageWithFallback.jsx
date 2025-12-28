/**
 * ImageWithFallback.jsx - Self-Healing Image Component
 *
 * ARCHITECTURE:
 * - Wraps native <img> with automatic fallback on load failure
 * - Maintains internal state to track load status
 * - Prevents layout shifts with seamless placeholder swap
 * - Polymorphic: accepts all standard img attributes
 *
 * USAGE:
 * <ImageWithFallback
 *     src={product.image}
 *     fallbackSrc="/images/placeholder.png"
 *     alt="Product"
 *     className="..."
 * />
 *
 * @module Components/ImageWithFallback
 */

import { useState, useCallback } from 'react';
import { ImageOff } from 'lucide-react';

/**
 * Default placeholder - can be overridden via fallbackSrc prop
 */
const DEFAULT_PLACEHOLDER = '/images/placeholder.png';

/**
 * ImageWithFallback - Resilient image component with automatic fallback
 *
 * @param {Object} props - Component props
 * @param {string} props.src - Primary image source URL
 * @param {string} [props.fallbackSrc] - Fallback image URL (defaults to placeholder)
 * @param {string} [props.fallbackIcon] - If true, shows icon instead of placeholder image
 * @param {string} [props.fallbackColor] - Background color for icon fallback
 * @param {string} props.alt - Alt text (required for accessibility)
 * @param {string} [props.className] - CSS classes
 * @param {string} [props.loading] - Loading strategy ('lazy' | 'eager')
 * @param {Object} [props.style] - Inline styles
 * @param {...any} rest - Any other valid img attributes
 *
 * @returns {JSX.Element}
 */
export default function ImageWithFallback({
    src,
    fallbackSrc = DEFAULT_PLACEHOLDER,
    fallbackIcon = false,
    fallbackColor = '#f3f4f6',
    alt = '',
    className = '',
    loading = 'lazy',
    style = {},
    ...rest
}) {
    // Track if primary image failed to load
    const [hasError, setHasError] = useState(false);
    // Track if image has successfully loaded
    const [isLoaded, setIsLoaded] = useState(false);

    /**
     * Handle image load error - swap to fallback
     * Sets onerror to null to prevent infinite loop if fallback also fails
     */
    const handleError = useCallback((e) => {
        e.target.onerror = null; // Prevent infinite loop
        setHasError(true);

        // If not using icon fallback, swap the src
        if (!fallbackIcon && fallbackSrc) {
            e.target.src = fallbackSrc;
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
    if (hasError && fallbackIcon) {
        return (
            <div
                className={`flex items-center justify-center ${className}`}
                style={{ backgroundColor: fallbackColor, ...style }}
                role="img"
                aria-label={alt || 'Imagen no disponible'}
                {...rest}
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
        if (fallbackIcon) {
            return (
                <div
                    className={`flex items-center justify-center ${className}`}
                    style={{ backgroundColor: fallbackColor, ...style }}
                    role="img"
                    aria-label={alt || 'Sin imagen'}
                    {...rest}
                >
                    <ImageOff
                        className="h-12 w-12 text-gray-400 opacity-50"
                        aria-hidden="true"
                    />
                </div>
            );
        }

        return (
            <img
                src={fallbackSrc}
                alt={alt}
                className={className}
                loading={loading}
                style={style}
                {...rest}
            />
        );
    }

    // ═══════════════════════════════════════════════════════════════════════
    // RENDER: Standard image with error handling
    // ═══════════════════════════════════════════════════════════════════════
    return (
        <img
            src={hasError && fallbackSrc ? fallbackSrc : src}
            alt={alt}
            className={className}
            loading={loading}
            style={style}
            onError={handleError}
            onLoad={handleLoad}
            {...rest}
        />
    );
}

/**
 * PreloadedImageWithFallback - Variant with skeleton loading state
 * For use cases where we want to show a loading indicator
 */
export function ImageWithSkeleton({
    src,
    fallbackSrc = DEFAULT_PLACEHOLDER,
    alt = '',
    className = '',
    skeletonClassName = '',
    ...rest
}) {
    const [isLoaded, setIsLoaded] = useState(false);
    const [hasError, setHasError] = useState(false);

    const handleLoad = () => setIsLoaded(true);
    const handleError = (e) => {
        e.target.onerror = null;
        setHasError(true);
        if (fallbackSrc) {
            e.target.src = fallbackSrc;
        }
    };

    return (
        <div className={`relative ${className}`}>
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
                className={`${className} ${isLoaded ? 'opacity-100' : 'opacity-0'} transition-opacity duration-300`}
                onLoad={handleLoad}
                onError={handleError}
                {...rest}
            />
        </div>
    );
}
