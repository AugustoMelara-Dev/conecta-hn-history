import React from 'react';
import { AlertCircle, User, Package, Truck, Check, MapPin, Loader2, MessageCircle } from 'lucide-react';
import { cn } from '@/lib/utils';
import { calculateItemTotal } from '@/Stores/useCartStore';

export default function CheckoutForm({
    customerName,
    setCustomerName,
    customerPhone,
    setCustomerPhone,
    deliveryType,
    setDeliveryType,
    customerAddress,
    setCustomerAddress,
    customerNotes,
    setCustomerNotes,
    items,
    grandTotal,
    handleSubmitOrder,
    isSubmitting,
    submitError,
    errors,
}) {
    return (
        <>
            <div className="flex-1 space-y-6 overflow-y-auto p-4">
                {submitError ? (
                    <div className="flex items-start gap-3 rounded-xl border border-rose-300/20 bg-rose-500/10 p-4">
                        <AlertCircle className="mt-0.5 h-5 w-5 flex-shrink-0 text-rose-300" />
                        <p className="text-sm text-rose-200">{submitError}</p>
                    </div>
                ) : null}

                <div>
                    <label className="mb-2 block text-sm font-medium text-slate-300">
                        <User className="mr-2 inline h-4 w-4" />
                        Tu nombre *
                    </label>
                    <input
                        type="text"
                        value={customerName}
                        onChange={(event) => setCustomerName(event.target.value)}
                        placeholder="Ej: Maria Garcia"
                        className={cn(
                            'w-full rounded-xl border bg-slate-800 px-4 py-3 text-white placeholder:text-slate-500 transition-all focus:outline-none focus:ring-2 focus:ring-white/20',
                            errors.customerName ? 'border-rose-400' : 'border-white/10'
                        )}
                    />
                    {errors.customerName ? <p className="mt-1 text-sm text-rose-300">{errors.customerName}</p> : null}
                </div>

                <div>
                    <label className="mb-2 block text-sm font-medium text-slate-300">Tu telefono *</label>
                    <input
                        type="tel"
                        value={customerPhone}
                        onChange={(event) => setCustomerPhone(event.target.value)}
                        placeholder="Ej: 9999-1234"
                        className={cn(
                            'w-full rounded-xl border bg-slate-800 px-4 py-3 text-white placeholder:text-slate-500 transition-all focus:outline-none focus:ring-2 focus:ring-white/20',
                            errors.customerPhone ? 'border-rose-400' : 'border-white/10'
                        )}
                    />
                    {errors.customerPhone ? <p className="mt-1 text-sm text-rose-300">{errors.customerPhone}</p> : null}
                </div>

                <div>
                    <label className="mb-3 block text-sm font-medium text-slate-300">Como recibiras tu pedido?</label>
                    <div className="grid grid-cols-2 gap-3">
                        <button
                            type="button"
                            onClick={() => setDeliveryType('pickup')}
                            className={cn(
                                'flex flex-col items-center gap-2 rounded-xl border-2 p-4 transition-all',
                                deliveryType === 'pickup'
                                    ? 'border-white bg-white/10'
                                    : 'border-white/10 bg-slate-800/50 hover:border-white/20'
                            )}
                        >
                            <Package className={cn('h-6 w-6', deliveryType === 'pickup' ? 'text-white' : 'text-slate-400')} />
                            <span className={cn('text-sm font-medium', deliveryType === 'pickup' ? 'text-white' : 'text-slate-300')}>
                                Recoger en tienda
                            </span>
                            {deliveryType === 'pickup' ? <Check className="h-4 w-4 text-white" /> : null}
                        </button>

                        <button
                            type="button"
                            onClick={() => setDeliveryType('delivery')}
                            className={cn(
                                'flex flex-col items-center gap-2 rounded-xl border-2 p-4 transition-all',
                                deliveryType === 'delivery'
                                    ? 'border-white bg-white/10'
                                    : 'border-white/10 bg-slate-800/50 hover:border-white/20'
                            )}
                        >
                            <Truck className={cn('h-6 w-6', deliveryType === 'delivery' ? 'text-white' : 'text-slate-400')} />
                            <span className={cn('text-sm font-medium', deliveryType === 'delivery' ? 'text-white' : 'text-slate-300')}>
                                A domicilio
                            </span>
                            {deliveryType === 'delivery' ? <Check className="h-4 w-4 text-white" /> : null}
                        </button>
                    </div>
                </div>

                {deliveryType === 'delivery' ? (
                    <div>
                        <label className="mb-2 block text-sm font-medium text-slate-300">
                            <MapPin className="mr-2 inline h-4 w-4" />
                            Direccion de entrega *
                        </label>
                        <textarea
                            value={customerAddress}
                            onChange={(event) => setCustomerAddress(event.target.value)}
                            placeholder="Colonia, calle, numero de casa, referencias..."
                            rows={3}
                            className={cn(
                                'w-full resize-none rounded-xl border bg-slate-800 px-4 py-3 text-white placeholder:text-slate-500 transition-all focus:outline-none focus:ring-2 focus:ring-white/20',
                                errors.customerAddress ? 'border-rose-400' : 'border-white/10'
                            )}
                        />
                        {errors.customerAddress ? <p className="mt-1 text-sm text-rose-300">{errors.customerAddress}</p> : null}
                    </div>
                ) : null}

                <div>
                    <label className="mb-2 block text-sm font-medium text-slate-300">Notas adicionales (opcional)</label>
                    <textarea
                        value={customerNotes}
                        onChange={(event) => setCustomerNotes(event.target.value)}
                        placeholder="Instrucciones especiales, alergenos, etc."
                        rows={2}
                        className="w-full resize-none rounded-xl border border-white/10 bg-slate-800 px-4 py-3 text-white placeholder:text-slate-500 transition-all focus:outline-none focus:ring-2 focus:ring-white/20"
                    />
                </div>

                <div className="rounded-xl border border-white/5 bg-slate-800/50 p-4">
                    <h4 className="mb-3 text-sm font-medium text-slate-400">Resumen del pedido</h4>
                    <div className="space-y-2 text-sm">
                        {items.map((item) => (
                            <div key={item.id} className="flex justify-between text-slate-300">
                                <span>{item.quantity}x {item.name}</span>
                                <span>L. {calculateItemTotal(item.price, item.quantity, item.selectedModifiers).toFixed(2)}</span>
                            </div>
                        ))}
                        <div className="flex justify-between border-t border-white/10 pt-2 font-bold text-white">
                            <span>Total</span>
                            <span>L. {grandTotal.toFixed(2)}</span>
                        </div>
                    </div>
                </div>
            </div>

            <div className="border-t border-white/10 bg-slate-900/80 p-4 backdrop-blur-xl">
                <button
                    onClick={handleSubmitOrder}
                    disabled={isSubmitting}
                    className={cn(
                        'flex w-full items-center justify-center gap-2 rounded-xl py-4 font-bold transition-all active:scale-95',
                        'bg-white text-slate-950 hover:bg-slate-200',
                        'disabled:cursor-not-allowed disabled:opacity-50'
                    )}
                >
                    {isSubmitting ? (
                        <>
                            <Loader2 className="h-5 w-5 animate-spin" />
                            Procesando...
                        </>
                    ) : (
                        <>
                            <MessageCircle className="h-5 w-5" />
                            Confirmar pedido en WhatsApp
                        </>
                    )}
                </button>

                <p className="mt-3 text-center text-xs text-slate-500">
                    Tu pedido se registrara y luego se abrira WhatsApp para confirmar.
                </p>
            </div>
        </>
    );
}
