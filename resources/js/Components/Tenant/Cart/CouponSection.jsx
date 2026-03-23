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
    couponError,
}) {
    return (
        <div className="space-y-2">
            {couponCode ? (
                <div className="flex items-center justify-between rounded-lg border border-white/10 bg-white/5 px-3 py-2">
                    <div className="flex items-center gap-2">
                        <Tag className="h-4 w-4 text-white" />
                        <span className="text-sm font-medium text-white">{couponCode}</span>
                    </div>
                    <button
                        onClick={() => {
                            removeCoupon();
                            setShowCouponInput(true);
                        }}
                        className="text-slate-400 transition-colors hover:text-white"
                        title="Eliminar cupon"
                    >
                        <X className="h-4 w-4" />
                    </button>
                </div>
            ) : showCouponInput ? (
                <div className="space-y-2">
                    <div className="flex gap-2">
                        <input
                            type="text"
                            value={couponInput}
                            onChange={(event) => setCouponInput(event.target.value.toUpperCase())}
                            placeholder="Ingresa tu codigo"
                            autoFocus
                            className="flex-1 rounded-lg border border-white/10 bg-slate-800 px-3 py-2 text-sm text-white placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-white/20"
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
                            className="flex items-center gap-1 rounded-lg bg-white px-4 py-2 text-sm font-medium text-slate-950 transition-colors hover:bg-slate-200 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            {isApplyingCoupon ? <Loader2 className="h-4 w-4 animate-spin" /> : 'Aplicar'}
                        </button>
                    </div>

                    <button
                        onClick={() => {
                            setShowCouponInput(false);
                            setCouponInput('');
                        }}
                        className="text-xs text-slate-500 transition-colors hover:text-white"
                    >
                        Cancelar
                    </button>
                </div>
            ) : (
                <button
                    onClick={() => setShowCouponInput(true)}
                    className="flex items-center gap-1.5 text-sm text-slate-300 transition-colors hover:text-white hover:underline"
                >
                    <Tag className="h-3.5 w-3.5" />
                    Tienes un codigo de descuento?
                </button>
            )}

            {couponError && showCouponInput ? (
                <p className="text-xs text-rose-300">{couponError}</p>
            ) : null}
        </div>
    );
}
