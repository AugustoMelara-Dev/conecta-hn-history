import { useState, useCallback } from 'react';
import { router } from '@inertiajs/react';

const currencyFormatter = new Intl.NumberFormat('es-HN', {
    style: 'currency',
    currency: 'HNL',
    minimumFractionDigits: 0,
});

export default function CouponCard({ coupon, canManageCoupons = false, onToggle }) {
    const [isActive, setIsActive] = useState(coupon.is_active);
    const [copied, setCopied] = useState(false);

    const handleCopy = useCallback(async () => {
        try {
            await navigator.clipboard.writeText(coupon.code);
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        } catch (err) {
            console.error('Failed to copy:', err);
        }
    }, [coupon.code]);

    const handleToggle = useCallback(() => {
        if (!canManageCoupons) {
            return;
        }

        const previousState = isActive;
        setIsActive(!isActive);

        router.put(route('tenant.coupons.toggle', coupon.id), {}, {
            preserveScroll: true,
            preserveState: true,
            onError: () => {
                setIsActive(previousState);
            },
            onSuccess: () => {
                onToggle?.(coupon.id, !previousState);
            },
        });
    }, [canManageCoupons, coupon.id, isActive, onToggle]);

    const discountDisplay = coupon.type === 'percentage'
        ? `${coupon.value}% OFF`
        : `${currencyFormatter.format(coupon.value)} OFF`;

    const isExpired = coupon.expires_at && new Date(coupon.expires_at) < new Date();
    const isToggleDisabled = isExpired || !canManageCoupons;

    return (
        <div className={`overflow-hidden rounded-xl border bg-white shadow-sm transition-all ${isActive && !isExpired ? 'border-emerald-200 hover:shadow-md' : 'border-gray-200 opacity-75'}`}>
            <div className={`px-4 py-2 text-xs font-bold uppercase tracking-wide ${isExpired ? 'bg-red-100 text-red-700' : isActive ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-500'}`}>
                {isExpired ? 'Expirado' : isActive ? 'Activo' : 'Inactivo'}
            </div>

            <div className="p-5">
                <div className="mb-4 flex items-center justify-between">
                    <code className="text-2xl font-bold tracking-wide text-gray-900">{coupon.code}</code>

                    <button
                        onClick={handleCopy}
                        className={`rounded-lg p-2 transition-all ${copied ? 'bg-emerald-100 text-emerald-600' : 'bg-gray-100 text-gray-500 hover:bg-gray-200'}`}
                        title={copied ? 'Copiado' : 'Copiar codigo'}
                    >
                        {copied ? (
                            <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                            </svg>
                        ) : (
                            <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                            </svg>
                        )}
                    </button>
                </div>

                <div className="mb-4">
                    <span className="inline-block rounded-lg bg-indigo-100 px-3 py-1.5 text-lg font-bold text-indigo-700">
                        {discountDisplay}
                    </span>
                </div>

                <div className="mb-4 flex items-center gap-4 text-sm text-gray-500">
                    <span className="flex items-center gap-1">
                        <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                        </svg>
                        Usado {coupon.usage_count} veces
                    </span>
                    {coupon.usage_limit && <span className="text-gray-400">/ max. {coupon.usage_limit}</span>}
                </div>

                <div className="flex items-center justify-between border-t border-gray-100 pt-4">
                    <span className="text-sm font-medium text-gray-700">
                        {canManageCoupons ? (isActive ? 'Desactivar' : 'Activar') : 'Solo lectura'}
                    </span>
                    <button
                        onClick={handleToggle}
                        disabled={isToggleDisabled}
                        title={canManageCoupons ? undefined : 'Gestion no disponible en esta vista'}
                        className={`relative h-7 w-12 rounded-full transition-colors ${isToggleDisabled ? 'cursor-not-allowed bg-gray-200' : isActive ? 'bg-emerald-500' : 'bg-gray-300'}`}
                    >
                        <span className={`absolute top-1 h-5 w-5 rounded-full bg-white shadow transition-all ${isActive ? 'right-1' : 'left-1'}`} />
                    </button>
                </div>
            </div>
        </div>
    );
}
