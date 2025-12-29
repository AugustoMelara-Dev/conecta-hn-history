import { useRef, useId } from 'react';
import useCouponRedeemer from '@/Hooks/useCouponRedeemer';

/**
 * CouponRedeemer - Pure UI Component for Coupon Input (v8)
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  SRP COMPLIANT: UI only, all logic delegated to useCouponRedeemer      ║
 * ║  PURE COMPONENT: No network calls, no complex state management         ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * @param {Object} props
 * @param {string} props.applyCouponRoute - Route name for applying coupons
 * @param {string} props.removeCouponRoute - Route name for removing coupons
 * @param {Object|null} props.appliedCoupon - Currently applied coupon
 * @param {number} props.discount - Discount amount in currency
 */
export default function CouponRedeemer({
    applyCouponRoute = 'checkout.apply-coupon',
    removeCouponRoute = 'checkout.remove-coupon',
    appliedCoupon = null,
    discount = 0,
}) {
    const inputRef = useRef(null);
    const errorId = useId();

    // All logic delegated to hook (SRP)
    const {
        code,
        setCode,
        isLoading,
        error,
        isShaking,
        handleSubmit,
        handleRemove,
        canSubmit,
    } = useCouponRedeemer({
        applyCouponRoute,
        removeCouponRoute,
    });

    const formatPrice = (amount) => {
        return new Intl.NumberFormat('es-HN', {
            style: 'currency',
            currency: 'HNL',
            minimumFractionDigits: 2,
        }).format(amount);
    };

    // ═══════════════════════════════════════════════════════════════════════════
    // RENDER: APPLIED STATE
    // ═══════════════════════════════════════════════════════════════════════════
    if (appliedCoupon) {
        return (
            <div className="bg-emerald-50 border border-emerald-200 rounded-lg p-4">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <div className="w-10 h-10 bg-emerald-100 rounded-full flex items-center justify-center">
                            <svg className="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                            </svg>
                        </div>
                        <div>
                            <p className="text-sm font-semibold text-emerald-800">Cupón aplicado</p>
                            <p className="text-xs text-emerald-600 font-mono">{appliedCoupon.code || appliedCoupon}</p>
                        </div>
                    </div>

                    <div className="flex items-center gap-3">
                        <span className="text-lg font-bold text-emerald-700">- {formatPrice(discount)}</span>
                        <button
                            onClick={handleRemove}
                            disabled={isLoading}
                            className="text-xs text-gray-500 hover:text-red-600 transition-colors"
                            aria-label="Remover cupón"
                            type="button"
                        >
                            {isLoading ? (
                                <svg className="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                </svg>
                            ) : (
                                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            )}
                        </button>
                    </div>
                </div>
            </div>
        );
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // RENDER: INPUT STATE
    // ═══════════════════════════════════════════════════════════════════════════
    return (
        <div className="bg-gray-50 rounded-lg p-4">
            <form onSubmit={handleSubmit} noValidate>
                <label htmlFor="coupon-code" className="block text-sm font-medium text-gray-700 mb-2">
                    ¿Tienes un cupón de descuento?
                </label>

                <div className="flex gap-2">
                    <div className="relative flex-1">
                        <input
                            ref={inputRef}
                            type="text"
                            id="coupon-code"
                            value={code}
                            onChange={(e) => setCode(e.target.value)}
                            placeholder="Ingresa tu código"
                            disabled={isLoading}
                            autoComplete="off"
                            autoCorrect="off"
                            autoCapitalize="characters"
                            spellCheck={false}
                            aria-invalid={!!error}
                            aria-describedby={error ? errorId : undefined}
                            className={`
                                w-full px-4 py-2.5 rounded-lg border
                                text-sm font-mono uppercase tracking-wider
                                transition-all duration-150
                                focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500
                                disabled:bg-gray-100 disabled:cursor-not-allowed
                                ${error ? 'border-red-300 bg-red-50' : 'border-gray-300 bg-white'}
                                ${isShaking ? 'animate-shake' : ''}
                            `}
                        />
                        <div className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400">
                            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z" />
                            </svg>
                        </div>
                    </div>

                    <button
                        type="submit"
                        disabled={!canSubmit}
                        className={`
                            px-5 py-2.5 rounded-lg font-semibold text-sm transition-all duration-150
                            ${!canSubmit
                                ? 'bg-gray-200 text-gray-400 cursor-not-allowed'
                                : 'bg-emerald-600 text-white hover:bg-emerald-700 active:scale-95'
                            }
                        `}
                    >
                        {isLoading ? (
                            <span className="flex items-center gap-2">
                                <svg className="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                </svg>
                                Validando...
                            </span>
                        ) : (
                            'Aplicar'
                        )}
                    </button>
                </div>

                {error && (
                    <p id={errorId} className="mt-2 text-sm text-red-600 flex items-center gap-1" role="alert">
                        <svg className="w-4 h-4" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                            <path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clipRule="evenodd" />
                        </svg>
                        {error}
                    </p>
                )}
            </form>
        </div>
    );
}
