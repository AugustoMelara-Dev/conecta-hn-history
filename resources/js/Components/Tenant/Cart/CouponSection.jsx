import React from 'react';
import { Tag, X, Loader2 } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { cn } from '@/lib/utils';

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
        <div className="grid gap-2">
            {couponCode ? (
                <div className="flex items-center justify-between rounded-lg border bg-muted px-3 py-2">
                    <div className="flex items-center gap-2">
                        <Tag className="h-4 w-4 text-muted-foreground" data-icon aria-hidden="true" />
                        <span className="text-sm font-medium text-foreground">{couponCode}</span>
                    </div>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        onClick={() => {
                            removeCoupon();
                            setShowCouponInput(true);
                        }}
                        aria-label="Eliminar cupón"
                        title="Eliminar cupón"
                    >
                        <X className="h-4 w-4" data-icon aria-hidden="true" />
                    </Button>
                </div>
            ) : showCouponInput ? (
                <div className="grid gap-2">
                    <div className="flex gap-2">
                        <Input
                            type="text"
                            value={couponInput}
                            onChange={(event) => setCouponInput(event.target.value.toUpperCase())}
                            placeholder="Ingresa tu código"
                            autoFocus
                            aria-label="Código de descuento"
                            className="flex-1"
                        />
                        <Button
                            type="button"
                            onClick={async () => {
                                const result = await applyCoupon(couponInput);
                                if (result.success) {
                                    setCouponInput('');
                                    setShowCouponInput(false);
                                }
                            }}
                            disabled={isApplyingCoupon || !couponInput}
                        >
                            {isApplyingCoupon ? (
                                <Loader2 className="h-4 w-4 animate-spin" data-icon aria-hidden="true" />
                            ) : null}
                            Aplicar
                        </Button>
                    </div>

                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={() => {
                            setShowCouponInput(false);
                            setCouponInput('');
                        }}
                        className="w-fit"
                    >
                        Cancelar
                    </Button>
                </div>
            ) : (
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    onClick={() => setShowCouponInput(true)}
                    className="w-fit px-0"
                >
                    <Tag className="h-3.5 w-3.5" data-icon aria-hidden="true" />
                    ¿Tienes un código de descuento?
                </Button>
            )}

            {couponError && showCouponInput ? (
                <p className={cn('text-xs text-[color:var(--ds-danger)]')} role="alert">
                    {couponError}
                </p>
            ) : null}
        </div>
    );
}
