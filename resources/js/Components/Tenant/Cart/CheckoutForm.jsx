import React from 'react';
import { AlertCircle, User, Package, Truck, Check, MapPin, Loader2, MessageCircle } from 'lucide-react';
import { cn } from '@/lib/utils';
import { calculateItemTotal } from '@/Stores/useCartStore';

export default function CheckoutForm({
    // Form State
    customerName, setCustomerName,
    customerPhone, setCustomerPhone,
    deliveryType, setDeliveryType,
    customerAddress, setCustomerAddress,
    customerNotes, setCustomerNotes,

    // Order Data
    items,
    grandTotal,

    // Submission
    handleSubmitOrder,
    isSubmitting,
    submitError,
    errors
}) {
    return (
        <>
            <div className="flex-1 overflow-y-auto p-4 space-y-6">
                {/* Error Banner */}
                {submitError && (
                    <div className="bg-rose-500/10 border border-rose-500/30 rounded-xl p-4 flex items-start gap-3">
                        <AlertCircle className="w-5 h-5 text-rose-400 flex-shrink-0 mt-0.5" />
                        <p className="text-sm text-rose-300">{submitError}</p>
                    </div>
                )}

                {/* Name Input */}
                <div>
                    <label className="block text-sm font-medium text-slate-300 mb-2">
                        <User className="w-4 h-4 inline mr-2" />
                        Tu nombre *
                    </label>
                    <input
                        type="text"
                        value={customerName}
                        onChange={(e) => setCustomerName(e.target.value)}
                        placeholder="Ej: María García"
                        className={cn(
                            "w-full px-4 py-3 bg-slate-800 border rounded-xl text-white placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-all",
                            errors.customerName ? "border-rose-500" : "border-white/10"
                        )}
                    />
                    {errors.customerName && (
                        <p className="mt-1 text-sm text-rose-400">{errors.customerName}</p>
                    )}
                </div>

                {/* Phone Input */}
                <div>
                    <label className="block text-sm font-medium text-slate-300 mb-2">
                        📱 Tu teléfono *
                    </label>
                    <input
                        type="tel"
                        value={customerPhone}
                        onChange={(e) => setCustomerPhone(e.target.value)}
                        placeholder="Ej: 9999-1234"
                        className={cn(
                            "w-full px-4 py-3 bg-slate-800 border rounded-xl text-white placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-all",
                            errors.customerPhone ? "border-rose-500" : "border-white/10"
                        )}
                    />
                    {errors.customerPhone && (
                        <p className="mt-1 text-sm text-rose-400">{errors.customerPhone}</p>
                    )}
                </div>

                {/* Delivery Type Selector */}
                <div>
                    <label className="block text-sm font-medium text-slate-300 mb-3">
                        ¿Cómo recibirás tu pedido?
                    </label>
                    <div className="grid grid-cols-2 gap-3">
                        <button
                            type="button"
                            onClick={() => setDeliveryType('pickup')}
                            className={cn(
                                "p-4 rounded-xl border-2 flex flex-col items-center gap-2 transition-all",
                                deliveryType === 'pickup'
                                    ? "border-indigo-500 bg-indigo-500/10"
                                    : "border-white/10 bg-slate-800/50 hover:border-white/20"
                            )}
                        >
                            <Package className={cn(
                                "w-6 h-6",
                                deliveryType === 'pickup' ? "text-indigo-400" : "text-slate-400"
                            )} />
                            <span className={cn(
                                "text-sm font-medium",
                                deliveryType === 'pickup' ? "text-white" : "text-slate-300"
                            )}>
                                Recoger en tienda
                            </span>
                            {deliveryType === 'pickup' && (
                                <Check className="w-4 h-4 text-indigo-400" />
                            )}
                        </button>

                        <button
                            type="button"
                            onClick={() => setDeliveryType('delivery')}
                            className={cn(
                                "p-4 rounded-xl border-2 flex flex-col items-center gap-2 transition-all",
                                deliveryType === 'delivery'
                                    ? "border-indigo-500 bg-indigo-500/10"
                                    : "border-white/10 bg-slate-800/50 hover:border-white/20"
                            )}
                        >
                            <Truck className={cn(
                                "w-6 h-6",
                                deliveryType === 'delivery' ? "text-indigo-400" : "text-slate-400"
                            )} />
                            <span className={cn(
                                "text-sm font-medium",
                                deliveryType === 'delivery' ? "text-white" : "text-slate-300"
                            )}>
                                A domicilio
                            </span>
                            {deliveryType === 'delivery' && (
                                <Check className="w-4 h-4 text-indigo-400" />
                            )}
                        </button>
                    </div>
                </div>

                {/* Address Input (conditional) */}
                {deliveryType === 'delivery' && (
                    <div>
                        <label className="block text-sm font-medium text-slate-300 mb-2">
                            <MapPin className="w-4 h-4 inline mr-2" />
                            Dirección de entrega *
                        </label>
                        <textarea
                            value={customerAddress}
                            onChange={(e) => setCustomerAddress(e.target.value)}
                            placeholder="Colonia, calle, número de casa, referencias..."
                            rows={3}
                            className={cn(
                                "w-full px-4 py-3 bg-slate-800 border rounded-xl text-white placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-all resize-none",
                                errors.customerAddress ? "border-rose-500" : "border-white/10"
                            )}
                        />
                        {errors.customerAddress && (
                            <p className="mt-1 text-sm text-rose-400">{errors.customerAddress}</p>
                        )}
                    </div>
                )}

                {/* Notes (optional) */}
                <div>
                    <label className="block text-sm font-medium text-slate-300 mb-2">
                        📝 Notas adicionales (opcional)
                    </label>
                    <textarea
                        value={customerNotes}
                        onChange={(e) => setCustomerNotes(e.target.value)}
                        placeholder="Instrucciones especiales, alérgenos, etc."
                        rows={2}
                        className="w-full px-4 py-3 bg-slate-800 border border-white/10 rounded-xl text-white placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-all resize-none"
                    />
                </div>

                {/* Order Summary */}
                <div className="bg-slate-800/50 rounded-xl p-4 border border-white/5">
                    <h4 className="text-sm font-medium text-slate-400 mb-3">Resumen del pedido</h4>
                    <div className="space-y-2 text-sm">
                        {items.map(item => (
                            <div key={item.id} className="flex justify-between text-slate-300">
                                <span>{item.quantity}x {item.name}</span>
                                <span>L. {calculateItemTotal(item.price, item.quantity, item.selectedModifiers).toFixed(2)}</span>
                            </div>
                        ))}
                        <div className="pt-2 border-t border-white/10 flex justify-between font-bold text-white">
                            <span>Total</span>
                            <span>L. {grandTotal.toFixed(2)}</span>
                        </div>
                    </div>
                </div>
            </div>

            {/* Footer: Submit Button */}
            <div className="p-4 border-t border-white/10 bg-slate-900/80 backdrop-blur-xl">
                <button
                    onClick={handleSubmitOrder}
                    disabled={isSubmitting}
                    className={cn(
                        "w-full py-4 rounded-xl font-bold flex items-center justify-center gap-2",
                        "bg-emerald-600 hover:bg-emerald-500 text-white",
                        "shadow-lg shadow-emerald-900/30 hover:shadow-emerald-500/30",
                        "transition-all active:scale-98",
                        "disabled:opacity-50 disabled:cursor-not-allowed"
                    )}
                >
                    {isSubmitting ? (
                        <>
                            <Loader2 className="w-5 h-5 animate-spin" />
                            Procesando...
                        </>
                    ) : (
                        <>
                            <MessageCircle className="w-5 h-5" />
                            Confirmar Pedido en WhatsApp
                        </>
                    )}
                </button>

                <p className="text-center text-xs text-slate-500 mt-3">
                    Tu pedido será registrado y se abrirá WhatsApp para confirmar
                </p>
            </div>
        </>
    );
}
