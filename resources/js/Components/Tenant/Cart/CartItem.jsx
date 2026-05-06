import React from 'react';
import { ShoppingBag, Minus, Plus, Trash2 } from 'lucide-react';
import { calculateItemTotal } from '@/Stores/useCartStore';

export default function CartItem({ item, onRemove, onUpdateQuantity }) {
    const itemTotal = calculateItemTotal(item.price, item.quantity, item.selectedModifiers);

    return (
        <div className="flex gap-3 p-3 bg-slate-800/50 rounded-xl border border-white/5">
            {/* Image */}
            <div className="w-16 h-16 bg-slate-700 rounded-lg overflow-hidden flex-shrink-0">
                {item.image ? (
                    <img
                        src={item.image}
                        alt={item.name}
                        className="w-full h-full object-cover"
                    />
                ) : (
                    <div className="w-full h-full flex items-center justify-center">
                        <ShoppingBag className="w-6 h-6 text-slate-500" />
                    </div>
                )}
            </div>

            {/* Details */}
            <div className="flex-1 min-w-0">
                <h4 className="font-semibold text-white truncate">{item.name}</h4>

                {/* Modifiers */}
                {item.selectedModifiers?.length > 0 && (
                    <div className="mt-1 space-y-0.5">
                        {item.selectedModifiers.map((mod, idx) => (
                            <p key={idx} className="text-xs text-slate-400">
                                + {mod.name}
                                {mod.price > 0 && (
                                    <span className="text-emerald-400 ml-1">
                                        (+L.{mod.price.toFixed(2)})
                                    </span>
                                )}
                            </p>
                        ))}
                    </div>
                )}

                {/* Notes */}
                {item.notes && (
                    <p className="text-xs text-slate-500 mt-1 truncate">
                        📝 {item.notes}
                    </p>
                )}

                {/* Price + Quantity */}
                <div className="flex items-center justify-between mt-2">
                    <span
                        className="font-bold text-sm"
                        style={{ color: 'var(--primary-color, #818cf8)' }}
                    >
                        L. {itemTotal.toFixed(2)}
                    </span>

                    {/* Quantity Controls */}
                    <div className="flex items-center gap-1 bg-slate-700/50 rounded-lg">
                        <button
                            onClick={() => onUpdateQuantity(item.quantity - 1)}
                            className="w-7 h-7 flex items-center justify-center text-slate-400 hover:text-white"
                            aria-label="Reducir cantidad"
                        >
                            <Minus className="w-3 h-3" />
                        </button>
                        <span className="w-5 text-center text-sm font-medium text-white">
                            {item.quantity}
                        </span>
                        <button
                            onClick={() => onUpdateQuantity(item.quantity + 1)}
                            className="w-7 h-7 flex items-center justify-center text-slate-400 hover:text-white"
                            aria-label="Aumentar cantidad"
                        >
                            <Plus className="w-3 h-3" />
                        </button>
                    </div>
                </div>
            </div>

            {/* Remove Button */}
            <button
                onClick={onRemove}
                className="w-8 h-8 flex-shrink-0 flex items-center justify-center text-rose-400 hover:text-rose-300 transition-colors"
                aria-label="Eliminar producto"
            >
                <Trash2 className="w-4 h-4" />
            </button>
        </div>
    );
}
