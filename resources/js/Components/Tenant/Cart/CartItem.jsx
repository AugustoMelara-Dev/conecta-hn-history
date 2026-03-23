import React from 'react';
import { ShoppingBag, Minus, Plus, Trash2 } from 'lucide-react';
import { calculateItemTotal } from '@/Stores/useCartStore';

export default function CartItem({ item, onRemove, onUpdateQuantity }) {
    const itemTotal = calculateItemTotal(item.price, item.quantity, item.selectedModifiers);

    return (
        <div className="flex gap-3 rounded-xl border border-white/5 bg-slate-800/50 p-3">
            <div className="h-16 w-16 flex-shrink-0 overflow-hidden rounded-lg bg-slate-700">
                {item.image ? (
                    <img
                        src={item.image}
                        alt={item.name}
                        className="h-full w-full object-cover"
                    />
                ) : (
                    <div className="flex h-full w-full items-center justify-center">
                        <ShoppingBag className="h-6 w-6 text-slate-500" />
                    </div>
                )}
            </div>

            <div className="min-w-0 flex-1">
                <h4 className="truncate font-semibold text-white">{item.name}</h4>

                {item.selectedModifiers?.length > 0 ? (
                    <div className="mt-1 space-y-0.5">
                        {item.selectedModifiers.map((modifier, index) => (
                            <p key={index} className="text-xs text-slate-400">
                                + {modifier.name}
                                {modifier.price > 0 ? (
                                    <span className="ml-1 text-slate-300">
                                        (+L.{modifier.price.toFixed(2)})
                                    </span>
                                ) : null}
                            </p>
                        ))}
                    </div>
                ) : null}

                {item.notes ? (
                    <p className="mt-1 truncate text-xs text-slate-500">
                        Notas: {item.notes}
                    </p>
                ) : null}

                <div className="mt-2 flex items-center justify-between">
                    <span className="text-sm font-bold text-white">
                        L. {itemTotal.toFixed(2)}
                    </span>

                    <div className="flex items-center gap-1 rounded-lg bg-slate-700/50">
                        <button
                            onClick={() => onUpdateQuantity(item.quantity - 1)}
                            className="flex h-7 w-7 items-center justify-center text-slate-400 transition hover:text-white"
                            aria-label="Reducir cantidad"
                        >
                            <Minus className="h-3 w-3" />
                        </button>
                        <span className="w-5 text-center text-sm font-medium text-white">
                            {item.quantity}
                        </span>
                        <button
                            onClick={() => onUpdateQuantity(item.quantity + 1)}
                            className="flex h-7 w-7 items-center justify-center text-slate-400 transition hover:text-white"
                            aria-label="Aumentar cantidad"
                        >
                            <Plus className="h-3 w-3" />
                        </button>
                    </div>
                </div>
            </div>

            <button
                onClick={onRemove}
                className="flex h-8 w-8 flex-shrink-0 items-center justify-center text-slate-400 transition hover:text-white"
                aria-label="Eliminar producto"
            >
                <Trash2 className="h-4 w-4" />
            </button>
        </div>
    );
}
