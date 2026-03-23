/**
 * ProductModal - Modifier Selection Modal for Pro Tier
 * 
 * Features:
 * - Modifier group selection (single or multiple)
 * - Quantity adjustment
 * - Notes field
 * - Real-time price calculation
 * - Accessibility compliant (dialog pattern)
 */

import { useState, useEffect, Fragment } from 'react';
import { Dialog, Transition } from '@headlessui/react';
import { X, Minus, Plus, Check, ShoppingCart } from 'lucide-react';
import { cn } from '@/lib/utils';

export default function ProductModal({
    isOpen,
    onClose,
    product,
    onConfirm,
}) {
    const [quantity, setQuantity] = useState(1);
    const [selectedModifiers, setSelectedModifiers] = useState([]);
    const [notes, setNotes] = useState('');

    // Reset state when modal opens
    useEffect(() => {
        if (isOpen) {
            setQuantity(1);
            setSelectedModifiers([]);
            setNotes('');

            // Pre-select default modifiers
            if (product?.modifier_groups) {
                const defaults = [];
                product.modifier_groups.forEach(group => {
                    const defaultOption = group.options?.find(opt => opt.is_default);
                    if (defaultOption) {
                        defaults.push({
                            groupId: group.id,
                            groupName: group.name,
                            optionId: defaultOption.id,
                            name: defaultOption.name,
                            price: defaultOption.price,
                        });
                    }
                });
                setSelectedModifiers(defaults);
            }
        }
    }, [isOpen, product]);

    const handleModifierToggle = (group, option) => {
        setSelectedModifiers(prev => {
            // For single-select groups, replace any existing selection
            if (group.type === 'single') {
                return [
                    ...prev.filter(m => m.groupId !== group.id),
                    {
                        groupId: group.id,
                        groupName: group.name,
                        optionId: option.id,
                        name: option.name,
                        price: option.price,
                    }
                ];
            }

            // For multi-select, toggle
            const exists = prev.find(m => m.groupId === group.id && m.optionId === option.id);
            if (exists) {
                return prev.filter(m => !(m.groupId === group.id && m.optionId === option.id));
            } else {
                // Check max selections
                const groupSelections = prev.filter(m => m.groupId === group.id);
                if (group.max_selections && groupSelections.length >= group.max_selections) {
                    return prev; // Can't add more
                }
                return [
                    ...prev,
                    {
                        groupId: group.id,
                        groupName: group.name,
                        optionId: option.id,
                        name: option.name,
                        price: option.price,
                    }
                ];
            }
        });
    };

    const isOptionSelected = (groupId, optionId) => {
        return selectedModifiers.some(m => m.groupId === groupId && m.optionId === optionId);
    };

    const calculateTotal = () => {
        const basePrice = product?.price || 0;
        const modifiersTotal = selectedModifiers.reduce((sum, m) => sum + (m.price || 0), 0);
        return (basePrice + modifiersTotal) * quantity;
    };

    const handleConfirm = () => {
        onConfirm(quantity, selectedModifiers, notes);
    };

    // Check if required modifiers are selected
    const isValid = () => {
        if (!product?.modifier_groups) return true;

        return product.modifier_groups.every(group => {
            if (!group.is_required) return true;
            return selectedModifiers.some(m => m.groupId === group.id);
        });
    };

    if (!product) return null;

    return (
        <Transition appear show={isOpen} as={Fragment}>
            <Dialog as="div" className="relative z-50" onClose={onClose}>
                {/* Backdrop */}
                <Transition.Child
                    as={Fragment}
                    enter="ease-out duration-300"
                    enterFrom="opacity-0"
                    enterTo="opacity-100"
                    leave="ease-in duration-200"
                    leaveFrom="opacity-100"
                    leaveTo="opacity-0"
                >
                    <div className="fixed inset-0 bg-black/60 backdrop-blur-sm" />
                </Transition.Child>

                {/* Modal Container */}
                <div className="fixed inset-0 overflow-y-auto">
                    <div className="flex min-h-full items-end sm:items-center justify-center p-0 sm:p-4">
                        <Transition.Child
                            as={Fragment}
                            enter="ease-out duration-300"
                            enterFrom="opacity-0 translate-y-full sm:translate-y-4 sm:scale-95"
                            enterTo="opacity-100 translate-y-0 sm:scale-100"
                            leave="ease-in duration-200"
                            leaveFrom="opacity-100 translate-y-0 sm:scale-100"
                            leaveTo="opacity-0 translate-y-full sm:translate-y-4 sm:scale-95"
                        >
                            <Dialog.Panel className="w-full sm:max-w-lg transform overflow-hidden rounded-t-3xl sm:rounded-3xl bg-slate-900 border border-white/10 shadow-2xl transition-all">
                                {/* Header with Image */}
                                <div className="relative h-48 bg-slate-800 overflow-hidden">
                                    <img
                                        src={product.image}
                                        alt={product.name}
                                        className="w-full h-full object-cover opacity-80"
                                    />
                                    <div className="absolute inset-0 bg-gradient-to-t from-slate-900 to-transparent" />

                                    {/* Close button */}
                                    <button
                                        onClick={onClose}
                                        className="absolute top-4 right-4 w-10 h-10 bg-black/50 backdrop-blur-md rounded-full flex items-center justify-center text-white hover:bg-white/20 transition-colors"
                                    >
                                        <X className="w-5 h-5" />
                                    </button>

                                    {/* Product info overlay */}
                                    <div className="absolute bottom-4 left-4 right-4">
                                        <Dialog.Title className="text-xl font-bold text-white">
                                            {product.name}
                                        </Dialog.Title>
                                        <p className="text-slate-300 text-sm mt-1">{product.description}</p>
                                        <p className="mt-2 text-lg font-bold text-white">{product.formatted_price}</p>
                                    </div>
                                </div>

                                {/* Modifier Groups */}
                                <div className="max-h-[40vh] overflow-y-auto p-4 space-y-6">
                                    {product.modifier_groups?.map(group => (
                                        <div key={group.id} className="space-y-3">
                                            <div className="flex items-center justify-between">
                                                <h3 className="font-semibold text-white">
                                                    {group.name}
                                                    {group.is_required && (
                                                        <span className="ml-2 text-xs text-rose-400">*Requerido</span>
                                                    )}
                                                </h3>
                                                {group.type === 'multiple' && group.max_selections && (
                                                    <span className="text-xs text-slate-400">
                                                        Máx: {group.max_selections}
                                                    </span>
                                                )}
                                            </div>

                                            <div className="space-y-2">
                                                {group.options?.map(option => (
                                                    <button
                                                        key={option.id}
                                                        type="button"
                                                        onClick={() => handleModifierToggle(group, option)}
                                                        className={cn(
                                                            "w-full flex items-center justify-between rounded-xl border p-3 transition-all",
                                                            isOptionSelected(group.id, option.id)
                                                                ? "border-white bg-white/10 text-white"
                                                                : "border-white/5 bg-slate-800/50 text-slate-300 hover:border-white/20"
                                                        )}
                                                    >
                                                        <div className="flex items-center gap-3">
                                                            <div className={cn(
                                                                "w-5 h-5 rounded-full border-2 flex items-center justify-center transition-colors",
                                                                isOptionSelected(group.id, option.id)
                                                                    ? "border-white bg-white"
                                                                    : "border-slate-500"
                                                            )}>
                                                                {isOptionSelected(group.id, option.id) && (
                                                                    <Check className="w-3 h-3 text-slate-950" />
                                                                )}
                                                            </div>
                                                            <span className="font-medium">{option.name}</span>
                                                        </div>
                                                        <span className={cn(
                                                            "text-sm",
                                                            option.price > 0 ? "text-white" : "text-slate-500"
                                                        )}>
                                                            {option.formatted_price}
                                                        </span>
                                                    </button>
                                                ))}
                                            </div>
                                        </div>
                                    ))}

                                    {/* Notes */}
                                    <div className="space-y-2">
                                        <label className="block text-sm font-medium text-slate-300">
                                            Notas especiales (opcional)
                                        </label>
                                        <textarea
                                            value={notes}
                                            onChange={(e) => setNotes(e.target.value)}
                                            placeholder="Ej: Sin cebolla, extra salsa..."
                                            className="w-full resize-none rounded-xl border border-white/10 bg-slate-800 px-4 py-3 text-white placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-white/20"
                                            rows={2}
                                        />
                                    </div>
                                </div>

                                {/* Footer: Quantity + Add to Cart */}
                                <div className="p-4 border-t border-white/10 bg-slate-900/50">
                                    <div className="flex items-center gap-4">
                                        {/* Quantity Selector */}
                                        <div className="flex items-center gap-2 bg-slate-800 rounded-xl border border-white/10">
                                            <button
                                                type="button"
                                                onClick={() => setQuantity(q => Math.max(1, q - 1))}
                                                className="w-10 h-10 flex items-center justify-center text-slate-400 hover:text-white transition-colors"
                                            >
                                                <Minus className="w-4 h-4" />
                                            </button>
                                            <span className="w-8 text-center font-bold text-white">{quantity}</span>
                                            <button
                                                type="button"
                                                onClick={() => setQuantity(q => q + 1)}
                                                className="w-10 h-10 flex items-center justify-center text-slate-400 hover:text-white transition-colors"
                                            >
                                                <Plus className="w-4 h-4" />
                                            </button>
                                        </div>

                                        {/* Add to Cart Button */}
                                        <button
                                            type="button"
                                            onClick={handleConfirm}
                                            disabled={!isValid()}
                                            className={cn(
                                                "flex-1 py-4 rounded-xl font-bold flex items-center justify-center gap-2 transition-all",
                                                isValid()
                                                    ? "bg-white text-slate-950 shadow-lg hover:bg-slate-200"
                                                    : "cursor-not-allowed bg-slate-700 text-slate-400"
                                            )}
                                        >
                                            <ShoppingCart className="w-5 h-5" />
                                            <span>Agregar L. {calculateTotal().toFixed(2)}</span>
                                        </button>
                                    </div>
                                </div>
                            </Dialog.Panel>
                        </Transition.Child>
                    </div>
                </div>
            </Dialog>
        </Transition>
    );
}
