import { Fragment } from 'react';
import { Dialog, Transition } from '@headlessui/react';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import { X, Plus, Minus, Trash2, ShoppingCart, MessageCircle } from 'lucide-react';
import useCartStore from '@/Stores/useCartStore';
import { cn } from '@/lib/utils';

/**
 * ═══════════════════════════════════════════════════════════════════════════
 * FLOATING CART - Conecta HN
 * ═══════════════════════════════════════════════════════════════════════════
 * 
 * Botón flotante con badge de cantidad + Drawer lateral del carrito.
 * Integrado con WhatsApp para envío de pedido.
 */
export default function FloatingCart({ primaryColor = '#000000' }) {
    const {
        cart,
        isCartOpen,
        openCart,
        closeCart,
        incrementItem,
        decrementItem,
        removeItem,
        clearCart,
        getCartTotal,
        getItemsCount,
        getWhatsAppUrl,
        tenantName
    } = useCartStore();

    const itemsCount = getItemsCount();
    const cartTotal = getCartTotal();
    const whatsappUrl = getWhatsAppUrl();

    // Calcular precio de item individual con modificadores
    const calculateItemPrice = (item) => {
        const basePrice = parseFloat(item.price) || 0;
        const modifiersTotal = (item.selectedOptions || []).reduce(
            (sum, opt) => sum + (parseFloat(opt.price_modifier) || 0),
            0
        );
        return (basePrice + modifiersTotal) * item.quantity;
    };

    // Si no hay items, mostrar solo el botón (sin badge)
    if (itemsCount === 0) {
        return null;
    }

    return (
        <>
            {/* Botón Flotante del Carrito */}
            <div className="fixed bottom-6 right-6 z-50 animate-in fade-in slide-in-from-bottom-10 duration-500">
                <button
                    onClick={openCart}
                    className="group relative flex items-center gap-3 bg-slate-900 hover:bg-slate-800 text-white px-5 py-4 rounded-full shadow-2xl hover:shadow-slate-900/30 transition-all hover:scale-105"
                >
                    <ShoppingCart className="w-6 h-6" />
                    <div className="flex flex-col items-start">
                        <span className="text-xs opacity-70">Tu pedido</span>
                        <span className="font-bold">L. {cartTotal.toFixed(2)}</span>
                    </div>

                    {/* Badge de cantidad */}
                    <Badge
                        className="absolute -top-2 -right-2 bg-rose-500 hover:bg-rose-500 text-white border-2 border-white min-w-[24px] h-6 flex items-center justify-center"
                    >
                        {itemsCount}
                    </Badge>
                </button>
            </div>

            {/* Drawer del Carrito */}
            <Transition show={isCartOpen} as={Fragment}>
                <Dialog onClose={closeCart} className="relative z-[100]">
                    {/* Overlay */}
                    <Transition.Child
                        as={Fragment}
                        enter="ease-out duration-300"
                        enterFrom="opacity-0"
                        enterTo="opacity-100"
                        leave="ease-in duration-200"
                        leaveFrom="opacity-100"
                        leaveTo="opacity-0"
                    >
                        <div className="fixed inset-0 bg-black/50 backdrop-blur-sm" />
                    </Transition.Child>

                    {/* Panel */}
                    <div className="fixed inset-0 overflow-hidden">
                        <div className="absolute inset-0 overflow-hidden">
                            <div className="pointer-events-none fixed inset-y-0 right-0 flex max-w-full pl-10">
                                <Transition.Child
                                    as={Fragment}
                                    enter="transform transition ease-in-out duration-300"
                                    enterFrom="translate-x-full"
                                    enterTo="translate-x-0"
                                    leave="transform transition ease-in-out duration-300"
                                    leaveFrom="translate-x-0"
                                    leaveTo="translate-x-full"
                                >
                                    <Dialog.Panel className="pointer-events-auto w-screen max-w-md">
                                        <div className="flex h-full flex-col bg-background shadow-xl">
                                            {/* Header */}
                                            <div className="flex items-center justify-between px-6 py-4 border-b">
                                                <Dialog.Title className="text-lg font-bold flex items-center gap-2">
                                                    <ShoppingCart className="w-5 h-5" />
                                                    Tu Pedido
                                                    {tenantName && (
                                                        <span className="text-sm font-normal text-muted-foreground">
                                                            • {tenantName}
                                                        </span>
                                                    )}
                                                </Dialog.Title>
                                                <button
                                                    onClick={closeCart}
                                                    className="p-2 rounded-full hover:bg-muted transition-colors"
                                                >
                                                    <X className="w-5 h-5" />
                                                </button>
                                            </div>

                                            {/* Items List */}
                                            <div className="flex-1 overflow-y-auto px-6 py-4">
                                                <div className="space-y-4">
                                                    {cart.map((item) => (
                                                        <div
                                                            key={item.cartItemId}
                                                            className="flex gap-4 p-3 bg-muted/30 rounded-xl"
                                                        >
                                                            {/* Imagen */}
                                                            <div className="w-20 h-20 bg-muted rounded-lg overflow-hidden flex-shrink-0">
                                                                {item.image ? (
                                                                    <img
                                                                        src={`/storage/${item.image}`}
                                                                        alt={item.name}
                                                                        className="w-full h-full object-cover"
                                                                    />
                                                                ) : (
                                                                    <div className="w-full h-full flex items-center justify-center">
                                                                        <ShoppingCart className="w-6 h-6 text-muted-foreground/30" />
                                                                    </div>
                                                                )}
                                                            </div>

                                                            {/* Info */}
                                                            <div className="flex-1 min-w-0">
                                                                <h4 className="font-semibold truncate">{item.name}</h4>

                                                                {/* Opciones seleccionadas */}
                                                                {item.selectedOptions?.length > 0 && (
                                                                    <div className="mt-1 space-y-0.5">
                                                                        {item.selectedOptions.map((opt, idx) => (
                                                                            <p key={idx} className="text-xs text-muted-foreground">
                                                                                • {opt.name}
                                                                                {opt.price_modifier > 0 && (
                                                                                    <span className="text-emerald-600 ml-1">
                                                                                        +L.{opt.price_modifier}
                                                                                    </span>
                                                                                )}
                                                                            </p>
                                                                        ))}
                                                                    </div>
                                                                )}

                                                                {/* Notas */}
                                                                {item.notes && (
                                                                    <p className="text-xs text-muted-foreground italic mt-1">
                                                                        📝 {item.notes}
                                                                    </p>
                                                                )}

                                                                {/* Precio y Cantidad */}
                                                                <div className="flex items-center justify-between mt-2">
                                                                    <span className="font-bold">
                                                                        L. {calculateItemPrice(item).toFixed(2)}
                                                                    </span>

                                                                    <div className="flex items-center gap-2">
                                                                        <button
                                                                            onClick={() => decrementItem(item.cartItemId)}
                                                                            className="w-7 h-7 rounded-full bg-muted hover:bg-muted-foreground/20 flex items-center justify-center transition-colors"
                                                                        >
                                                                            {item.quantity === 1 ? (
                                                                                <Trash2 className="w-3.5 h-3.5 text-rose-500" />
                                                                            ) : (
                                                                                <Minus className="w-3.5 h-3.5" />
                                                                            )}
                                                                        </button>
                                                                        <span className="w-6 text-center font-semibold">
                                                                            {item.quantity}
                                                                        </span>
                                                                        <button
                                                                            onClick={() => incrementItem(item.cartItemId)}
                                                                            className="w-7 h-7 rounded-full bg-muted hover:bg-muted-foreground/20 flex items-center justify-center transition-colors"
                                                                        >
                                                                            <Plus className="w-3.5 h-3.5" />
                                                                        </button>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    ))}
                                                </div>

                                                {/* Limpiar carrito */}
                                                <button
                                                    onClick={clearCart}
                                                    className="mt-4 text-sm text-rose-500 hover:text-rose-600 hover:underline transition-colors"
                                                >
                                                    Vaciar carrito
                                                </button>
                                            </div>

                                            {/* Footer - Total y WhatsApp */}
                                            <div className="border-t px-6 py-4 space-y-4 bg-muted/20">
                                                {/* Resumen */}
                                                <div className="space-y-2">
                                                    <div className="flex justify-between text-sm">
                                                        <span className="text-muted-foreground">Subtotal ({itemsCount} items)</span>
                                                        <span>L. {cartTotal.toFixed(2)}</span>
                                                    </div>
                                                    <div className="flex justify-between text-lg font-bold">
                                                        <span>Total</span>
                                                        <span>L. {cartTotal.toFixed(2)}</span>
                                                    </div>
                                                </div>

                                                {/* Botón de WhatsApp */}
                                                <a
                                                    href={whatsappUrl}
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    className="flex items-center justify-center gap-3 w-full py-4 rounded-full text-white font-bold text-lg transition-all hover:scale-[1.02] hover:shadow-lg"
                                                    style={{ backgroundColor: '#25D366' }}
                                                    onClick={closeCart}
                                                >
                                                    <MessageCircle className="w-6 h-6 fill-white" />
                                                    Enviar Pedido por WhatsApp
                                                </a>

                                                <p className="text-xs text-center text-muted-foreground">
                                                    Tu pedido será enviado directamente al negocio
                                                </p>
                                            </div>
                                        </div>
                                    </Dialog.Panel>
                                </Transition.Child>
                            </div>
                        </div>
                    </div>
                </Dialog>
            </Transition>
        </>
    );
}
