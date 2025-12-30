/**
 * Components/Public/FavoriteButton.jsx - Smart Favorite Toggle
 *
 * FEATURES:
 * - Optimistic UI toggle
 * - Authentication-aware (guest redirect to login)
 * - Heart animation on toggle
 * - Persisted state via API
 *
 * @param {Object} props
 * @param {number} props.tenantId - ID of the tenant to favorite
 * @param {boolean} props.isFavorited - Initial favorite state
 * @param {number} props.count - Optional favorite count
 * @param {string} props.size - 'sm' | 'md' | 'lg'
 * @param {boolean} props.showCount - Show favorite count
 */

import { useState, useCallback } from 'react';
import { router, usePage } from '@inertiajs/react';

export default function FavoriteButton({
    tenantId,
    isFavorited: initialFavorited = false,
    count: initialCount = 0,
    size = 'md',
    showCount = false,
}) {
    const { auth } = usePage().props;
    const [isFavorited, setIsFavorited] = useState(initialFavorited);
    const [count, setCount] = useState(initialCount);
    const [isAnimating, setIsAnimating] = useState(false);

    // Size classes
    const sizeClasses = {
        sm: 'w-8 h-8',
        md: 'w-10 h-10',
        lg: 'w-12 h-12',
    };

    const iconSizes = {
        sm: 'w-4 h-4',
        md: 'w-5 h-5',
        lg: 'w-6 h-6',
    };

    const handleToggle = useCallback((e) => {
        e.preventDefault();
        e.stopPropagation();

        // Check authentication
        if (!auth?.user) {
            router.visit(route('login'), {
                data: { redirect: window.location.pathname },
            });
            return;
        }

        // Optimistic update
        const newState = !isFavorited;
        setIsFavorited(newState);
        setCount(prev => newState ? prev + 1 : Math.max(0, prev - 1));

        // Trigger animation
        setIsAnimating(true);
        setTimeout(() => setIsAnimating(false), 300);

        // Persist to server
        router.post(
            route('favorites.toggle'),
            { tenant_id: tenantId },
            {
                preserveScroll: true,
                preserveState: true,
                onError: () => {
                    // Rollback on error
                    setIsFavorited(!newState);
                    setCount(prev => newState ? prev - 1 : prev + 1);
                },
            }
        );
    }, [auth, isFavorited, tenantId]);

    return (
        <button
            onClick={handleToggle}
            // FIX #4: Added 'relative' for proper stacking context
            // Principle: CSS Stacking - absolute children need relative parent
            className={`
                relative
                ${sizeClasses[size]}
                flex items-center justify-center
                rounded-full
                transition-all duration-200
                ${isFavorited
                    ? 'bg-red-100 text-red-500 hover:bg-red-200'
                    : 'bg-white/90 text-gray-400 hover:text-red-400 hover:bg-white'
                }
                backdrop-blur-sm
                shadow-md hover:shadow-lg
                ${isAnimating ? 'scale-125' : 'scale-100'}
            `}
            aria-label={isFavorited ? 'Quitar de favoritos' : 'Agregar a favoritos'}
            title={isFavorited ? 'Quitar de favoritos' : 'Agregar a favoritos'}
        >
            {/* Heart Icon */}
            <svg
                className={`${iconSizes[size]} transition-transform ${isAnimating ? 'scale-110' : ''}`}
                fill={isFavorited ? 'currentColor' : 'none'}
                stroke="currentColor"
                viewBox="0 0 24 24"
            >
                <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    strokeWidth={2}
                    d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"
                />
            </svg>

            {/* Optional Count Badge */}
            {showCount && count > 0 && (
                <span className="absolute -bottom-1 -right-1 min-w-[18px] h-[18px] flex items-center justify-center px-1 bg-red-500 text-white text-xs font-bold rounded-full">
                    {count > 99 ? '99+' : count}
                </span>
            )}

            {/* Particles Animation */}
            {isAnimating && isFavorited && (
                <div className="absolute inset-0 pointer-events-none">
                    <div className="absolute top-0 left-1/2 w-1 h-1 bg-red-400 rounded-full animate-ping" />
                    <div className="absolute bottom-0 right-0 w-1 h-1 bg-pink-400 rounded-full animate-ping" style={{ animationDelay: '0.1s' }} />
                    <div className="absolute top-1/2 left-0 w-1 h-1 bg-red-300 rounded-full animate-ping" style={{ animationDelay: '0.2s' }} />
                </div>
            )}
        </button>
    );
}

// ═══════════════════════════════════════════════════════════════════════════
// INLINE VARIANT (For list items)
// ═══════════════════════════════════════════════════════════════════════════
export function FavoriteButtonInline({ tenantId, isFavorited: initialFavorited = false }) {
    const { auth } = usePage().props;
    const [isFavorited, setIsFavorited] = useState(initialFavorited);

    const handleToggle = useCallback((e) => {
        e.preventDefault();
        e.stopPropagation();

        if (!auth?.user) {
            router.visit(route('login'));
            return;
        }

        setIsFavorited(!isFavorited);

        router.post(
            route('favorites.toggle'),
            { tenant_id: tenantId },
            {
                preserveScroll: true,
                preserveState: true,
                onError: () => setIsFavorited(isFavorited),
            }
        );
    }, [auth, isFavorited, tenantId]);

    return (
        <button
            onClick={handleToggle}
            className={`flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm font-medium transition-colors ${isFavorited
                ? 'bg-red-100 text-red-600'
                : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                }`}
        >
            <svg
                className="w-4 h-4"
                fill={isFavorited ? 'currentColor' : 'none'}
                stroke="currentColor"
                viewBox="0 0 24 24"
            >
                <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    strokeWidth={2}
                    d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"
                />
            </svg>
            {isFavorited ? 'Guardado' : 'Guardar'}
        </button>
    );
}
