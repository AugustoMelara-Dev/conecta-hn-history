import React from 'react';
import { Tag, X, Loader2 } from 'lucide-react';

export default function CouponSection({
    couponCode,
    showCouponInput,
    setShowCouponInput,
    couponInput,
    setCouponInput,
    isApplyingCoupon,
    applyCoupon,
    removeCoupon,
    couponError
}) {
    return (
        <div className="space-y-2">
            {couponCode ? (
                // ✅ Applied coupon display
                <div className="flex items-center justify-between bg-emerald-500/10 border border-emerald-500/30 rounded-lg px-3 py-2">
                    <div className="flex items-center gap-2">
                        <Tag className="w-4 h-4 text-emerald-400" />
                        <span className="text-sm font-medium text-emerald-300">{couponCode}</span>
                    </div>
                    <button
                        onClick={() => {
                            removeCoupon();
                            // Show input again for retry
                            setShowCouponInput(true);
                        }}
                        className="text-slate-400 hover:text-rose-400 transition-colors"
                        title="Eliminar cupón"
                    >
                        <X className="w-4 h-4" />
                    </button>
                </div>
            ) : showCouponInput ? (
                // 📝 Coupon input form (revealed)
                <div className="space-y-2">
                    <div className="flex gap-2">
                        <input
                            type="text"
                            value={couponInput}
                            onChange={(e) => setCouponInput(e.target.value.toUpperCase())}
                            placeholder="Ingresa tu código"
                            autoFocus
                            className="flex-1 px-3 py-2 bg-slate-800 border border-white/10 rounded-lg text-white text-sm placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        />
                        <button
                            onClick={async () => {
                                const result = await applyCoupon(couponInput);
                                if (result.success) {
                                    setCouponInput('');
                                    setShowCouponInput(false);
                                }
                            }}
                            disabled={isApplyingCoupon || !couponInput}
                            className="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed text-white text-sm font-medium rounded-lg transition-colors flex items-center gap-1"
                        >
                            {isApplyingCoupon ? (
                                <Loader2 className="w-4 h-4 animate-spin" />
                            ) : (
                                'Aplicar'
                            )}
                        </button>
                    </div>

                    {/* Cancel button to hide input */}
                    <button
                        onClick={() => {
                            setShowCouponInput(false);
                            setCouponInput('');
                            // Clear any previous error when closing
                        }}
                        className="text-xs text-slate-500 hover:text-slate-400 transition-colors"
                    >
                        Cancelar
                    </button>
                </div>
            ) : (
                // 🎯 Trigger link (hidden state - Progressive Disclosure)
                <button
                    onClick={() => setShowCouponInput(true)}
                    className="flex items-center gap-1.5 text-sm text-indigo-400 hover:text-indigo-300 hover:underline transition-colors"
                >
                    <Tag className="w-3.5 h-3.5" />
                    ¿Tienes un código de descuento?
                </button>
            )}

            {/* Coupon error */}
            {couponError && showCouponInput && (
                <p className="text-xs text-rose-400">{couponError}</p>
            )}
        </div>
    );
}
