/**
 * Components/Marketing/CouponCard.jsx - Interactive Coupon Display
 *
 * FEATURES:
 * - Clipboard API for code copying
 * - Optimistic toggle with rollback
 * - Visual feedback for all actions
 *
 * @param {Object} props
 * @param {Object} props.coupon - Coupon data
 * @param {number} props.coupon.id - Unique ID
 * @param {string} props.coupon.code - Coupon code (e.g., "SUMMER2025")
 * @param {string} props.coupon.type - 'percentage' | 'fixed'
 * @param {number} props.coupon.value - Discount value
 * @param {boolean} props.coupon.is_active - Active status
 * @param {number} props.coupon.usage_count - Times used
 * @param {string|null} props.coupon.expires_at - ISO date or null
 * @param {function} props.onToggle - Callback when toggle changes
 */

import { useState, useCallback } from 'react';
import { router } from '@inertiajs/react';

const currencyFormatter = new Intl.NumberFormat('es-HN', {
    style: 'currency',
    currency: 'HNL',
    minimumFractionDigits: 0,
});

export default function CouponCard({ coupon, onToggle }) {
    const [isActive, setIsActive] = useState(coupon.is_active);
    const [copied, setCopied] = useState(false);

    // ─────────────────────────────────────────────────────────────────────
    // CLIPBOARD COPY
    // ─────────────────────────────────────────────────────────────────────
    const handleCopy = useCallback(async () => {
        try {
            await navigator.clipboard.writeText(coupon.code);
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        } catch (err) {
            console.error('Failed to copy:', err);
        }
    }, [coupon.code]);

    // ─────────────────────────────────────────────────────────────────────
    // OPTIMISTIC TOGGLE
    // ─────────────────────────────────────────────────────────────────────
    const handleToggle = useCallback(() => {
        const previousState = isActive;

        // Optimistic update
        setIsActive(!isActive);

        // Background sync
        router.put(
            route('tenant.coupons.toggle', coupon.id),
            {},
            {
                preserveScroll: true,
                preserveState: true,
                onError: () => {
                    // Rollback on error
                    setIsActive(previousState);
                },
                onSuccess: () => {
                    onToggle?.(coupon.id, !previousState);
                },
            }
        );
    }, [coupon.id, isActive, onToggle]);

    // Format discount display
    const discountDisplay = coupon.type === 'percentage'
        ? `${coupon.value}% OFF`
        : `${currencyFormatter.format(coupon.value)} OFF`;

    // Check if expired
    const isExpired = coupon.expires_at && new Date(coupon.expires_at) < new Date();

    return (
        <div className={`bg-white rounded-xl shadow-sm border overflow-hidden transition-all ${isActive && !isExpired
                ? 'border-emerald-200 hover:shadow-md'
                : 'border-gray-200 opacity-75'
            }`}>
            {/* Header Badge */}
            <div className={`px-4 py-2 text-xs font-bold uppercase tracking-wide ${isExpired
                    ? 'bg-red-100 text-red-700'
                    : isActive
                        ? 'bg-emerald-100 text-emerald-700'
                        : 'bg-gray-100 text-gray-500'
                }`}>
                {isExpired ? 'Expirado' : isActive ? 'Activo' : 'Inactivo'}
            </div>

            <div className="p-5">
                {/* Code Display */}
                <div className="flex items-center justify-between mb-4">
                    <code className="text-2xl font-bold font-mono tracking-wide text-gray-900">
                        {coupon.code}
                    </code>

                    {/* Copy Button */}
                    <button
                        onClick={handleCopy}
                        className={`p-2 rounded-lg transition-all ${copied
                                ? 'bg-emerald-100 text-emerald-600'
                                : 'bg-gray-100 text-gray-500 hover:bg-gray-200'
                            }`}
                        title={copied ? '¡Copiado!' : 'Copiar código'}
                    >
                        {copied ? (
                            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                            </svg>
                        ) : (
                            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
                                    d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                            </svg>
                        )}
                    </button>
                </div>

                {/* Discount Value */}
                <div className="mb-4">
                    <span className="inline-block px-3 py-1.5 bg-indigo-100 text-indigo-700 font-bold text-lg rounded-lg">
                        {discountDisplay}
                    </span>
                </div>

                {/* Stats */}
                <div className="flex items-center gap-4 text-sm text-gray-500 mb-4">
                    <span className="flex items-center gap-1">
                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
                                d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                        </svg>
                        Usado {coupon.usage_count} veces
                    </span>
                    {coupon.usage_limit && (
                        <span className="text-gray-400">
                            / máx. {coupon.usage_limit}
                        </span>
                    )}
                </div>

                {/* Toggle Switch */}
                <div className="flex items-center justify-between pt-4 border-t border-gray-100">
                    <span className="text-sm font-medium text-gray-700">
                        {isActive ? 'Desactivar' : 'Activar'}
                    </span>
                    <button
                        onClick={handleToggle}
                        disabled={isExpired}
                        className={`relative w-12 h-7 rounded-full transition-colors ${isExpired
                                ? 'bg-gray-200 cursor-not-allowed'
                                : isActive
                                    ? 'bg-emerald-500'
                                    : 'bg-gray-300'
                            }`}
                    >
                        <span className={`absolute top-1 w-5 h-5 bg-white rounded-full shadow transition-all ${isActive ? 'right-1' : 'left-1'
                            }`} />
                    </button>
                </div>
            </div>
        </div>
    );
}
