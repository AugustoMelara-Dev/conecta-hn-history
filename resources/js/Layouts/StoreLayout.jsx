/**
 * StoreLayout.jsx - Corporate Minimalist Store Shell
 *
 * ARCHITECTURE:
 * - Sticky Navbar with tenant branding
 * - Headless UI Slide-Over Cart Drawer
 * - Dynamic brand color injection via CSS variables
 * - Context-aware via Inertia props
 *
 * BRAND INJECTION:
 * The tenant's primary_color is applied as a CSS variable (--primary)
 * and used throughout the layout for buttons and accents.
 */

import { Fragment } from 'react';
import { Link, usePage } from '@inertiajs/react';
import { Dialog, Transition } from '@headlessui/react';
import {
    ShoppingCart,
    X,
    Plus,
    Minus,
    Trash2,
    ArrowRight,
} from 'lucide-react';
import useCartStore from '@/Stores/useCartStore';
import CategoryNav from '@/Components/Store/CategoryNav';
import { formatCurrency } from '@/Utils/currency';

/**
 * StoreLayout - The Persistent Store Frame
 *
 * @param {React.ReactNode} children - Page content
 * @param {string} className - Additional CSS classes for main content
 */
export default function StoreLayout({ children, className = '' }) {
    // ═══════════════════════════════════════════════════════════════════════
    // CONTEXT: Consume tenant data from Inertia props
    // ═══════════════════════════════════════════════════════════════════════
    const { props } = usePage();
    const tenant = props.tenant ?? {};

    // Default brand color (Corporate Minimalist = Black)
    const primaryColor = tenant.primary_color || '#000000';

    // Menu sections for category navigation
    const menuSections = tenant.menu_sections || [];

    // ═══════════════════════════════════════════════════════════════════════
    // CART STATE: Bind to Zustand store
    // ═══════════════════════════════════════════════════════════════════════
    const {
        items,
        isCartOpen,
        toggleCart,
        closeCart,
        removeItem,
        updateQuantity,
        getTotalItems,
        getSubtotal,
    } = useCartStore();

    const totalItems = getTotalItems();
    const subtotal = getSubtotal();

    return (
        <div
            className="min-h-screen bg-white"
            style={{ '--primary': primaryColor }}
        >
            {/* ═══════════════════════════════════════════════════════════════
                STICKY NAVBAR
            ═══════════════════════════════════════════════════════════════ */}
            <header className="sticky top-0 z-50 bg-white border-b border-gray-100 shadow-sm">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="flex items-center justify-between h-16">
                        {/* Branding: Tenant Logo */}
                        <Link
                            href={tenant.slug ? `/t/${tenant.slug}` : '/'}
                            className="flex items-center space-x-3"
                        >
                            {tenant.logo ? (
                                <img
                                    src={tenant.logo}
                                    alt={tenant.name || 'Store'}
                                    className="h-10 w-auto object-contain"
                                />
                            ) : (
                                <span
                                    className="text-xl font-bold"
                                    style={{ color: primaryColor }}
                                >
                                    {tenant.name || 'Tienda'}
                                </span>
                            )}
                        </Link>

                        {/* Actions: Cart Trigger */}
                        <button
                            type="button"
                            onClick={toggleCart}
                            className="relative p-2 text-gray-600 hover:text-gray-900 transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 rounded-full"
                            style={{ '--tw-ring-color': primaryColor }}
                            aria-label={`Carrito de compras. ${totalItems} artículos`}
                        >
                            <ShoppingCart className="h-6 w-6" />

                            {/* Badge: Item Count */}
                            {totalItems > 0 && (
                                <span
                                    className="absolute -top-1 -right-1 h-5 w-5 flex items-center justify-center text-xs font-bold text-white rounded-full"
                                    style={{ backgroundColor: primaryColor }}
                                >
                                    {totalItems > 99 ? '99+' : totalItems}
                                </span>
                            )}
                        </button>
                    </div>
                </div>
            </header>

            {/* ═══════════════════════════════════════════════════════════════
                CATEGORY NAVIGATION (Sticky Secondary Header)
            ═══════════════════════════════════════════════════════════════ */}
            <CategoryNav
                categories={menuSections}
                primaryColor={primaryColor}
            />

            {/* ═══════════════════════════════════════════════════════════════
                MAIN CONTENT
            ═══════════════════════════════════════════════════════════════ */}
            <main className={className}>
                {children}
            </main>

            {/* ═══════════════════════════════════════════════════════════════
                CART DRAWER (Headless UI Slide-Over)
            ═══════════════════════════════════════════════════════════════ */}
            <Transition.Root show={isCartOpen} as={Fragment}>
                <Dialog as="div" className="relative z-50" onClose={closeCart}>
                    {/* Backdrop */}
                    <Transition.Child
                        as={Fragment}
                        enter="ease-in-out duration-300"
                        enterFrom="opacity-0"
                        enterTo="opacity-100"
                        leave="ease-in-out duration-300"
                        leaveFrom="opacity-100"
                        leaveTo="opacity-0"
                    >
                        <div className="fixed inset-0 bg-black/40 transition-opacity" />
                    </Transition.Child>

                    <div className="fixed inset-0 overflow-hidden">
                        <div className="absolute inset-0 overflow-hidden">
                            <div className="pointer-events-none fixed inset-y-0 right-0 flex max-w-full pl-10">
                                {/* Slide-Over Panel */}
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
                                        <div className="flex h-full flex-col bg-white shadow-xl">
                                            {/* Header */}
                                            <div className="flex items-center justify-between px-4 py-4 border-b border-gray-200">
                                                <Dialog.Title className="text-lg font-semibold text-gray-900">
                                                    Tu Carrito
                                                    {totalItems > 0 && (
                                                        <span className="ml-2 text-sm font-normal text-gray-500">
                                                            ({totalItems} artículo{totalItems !== 1 ? 's' : ''})
                                                        </span>
                                                    )}
                                                </Dialog.Title>
                                                <button
                                                    type="button"
                                                    onClick={closeCart}
                                                    className="p-2 text-gray-400 hover:text-gray-600 transition-colors rounded-full"
                                                    aria-label="Cerrar carrito"
                                                >
                                                    <X className="h-5 w-5" />
                                                </button>
                                            </div>

                                            {/* Cart Items */}
                                            <div className="flex-1 overflow-y-auto px-4 py-4">
                                                {items.length === 0 ? (
                                                    <div className="flex flex-col items-center justify-center h-full text-center">
                                                        <ShoppingCart className="h-16 w-16 text-gray-300 mb-4" />
                                                        <p className="text-gray-500 text-lg">
                                                            Tu carrito está vacío
                                                        </p>
                                                        <p className="text-gray-400 text-sm mt-1">
                                                            Agrega productos para comenzar
                                                        </p>
                                                    </div>
                                                ) : (
                                                    <ul className="divide-y divide-gray-200">
                                                        {items.map((item) => (
                                                            <li
                                                                key={item.id}
                                                                className="py-4 flex items-start space-x-4"
                                                            >
                                                                {/* Product Image */}
                                                                {item.image && (
                                                                    <img
                                                                        src={item.image}
                                                                        alt={item.name}
                                                                        className="h-16 w-16 rounded-lg object-cover border border-gray-100"
                                                                    />
                                                                )}

                                                                {/* Product Info */}
                                                                <div className="flex-1 min-w-0">
                                                                    <h4 className="text-sm font-medium text-gray-900 truncate">
                                                                        {item.name}
                                                                    </h4>

                                                                    {/* Modifiers/Options */}
                                                                    {item.selectedModifiers?.length > 0 && (
                                                                        <p className="text-xs text-gray-500 mt-0.5">
                                                                            {item.selectedModifiers
                                                                                .map((m) => m.name)
                                                                                .join(', ')}
                                                                        </p>
                                                                    )}

                                                                    {/* Price */}
                                                                    <p
                                                                        className="text-sm font-medium mt-1"
                                                                        style={{ color: primaryColor }}
                                                                    >
                                                                        {formatCurrency(item.price * item.quantity)}
                                                                    </p>

                                                                    {/* Quantity Controls */}
                                                                    <div className="flex items-center space-x-2 mt-2">
                                                                        <button
                                                                            type="button"
                                                                            onClick={() =>
                                                                                updateQuantity(item.id, item.quantity - 1)
                                                                            }
                                                                            className="p-1 rounded-full border border-gray-300 text-gray-500 hover:bg-gray-100 transition-colors"
                                                                            aria-label="Reducir cantidad"
                                                                        >
                                                                            <Minus className="h-3 w-3" />
                                                                        </button>
                                                                        <span className="text-sm font-medium w-6 text-center">
                                                                            {item.quantity}
                                                                        </span>
                                                                        <button
                                                                            type="button"
                                                                            onClick={() =>
                                                                                updateQuantity(item.id, item.quantity + 1)
                                                                            }
                                                                            className="p-1 rounded-full border border-gray-300 text-gray-500 hover:bg-gray-100 transition-colors"
                                                                            aria-label="Aumentar cantidad"
                                                                        >
                                                                            <Plus className="h-3 w-3" />
                                                                        </button>
                                                                    </div>
                                                                </div>

                                                                {/* Remove Button */}
                                                                <button
                                                                    type="button"
                                                                    onClick={() => removeItem(item.id)}
                                                                    className="p-1 text-gray-400 hover:text-red-500 transition-colors"
                                                                    aria-label={`Eliminar ${item.name} del carrito`}
                                                                >
                                                                    <Trash2 className="h-4 w-4" />
                                                                </button>
                                                            </li>
                                                        ))}
                                                    </ul>
                                                )}
                                            </div>

                                            {/* Footer: Subtotal & Checkout */}
                                            {items.length > 0 && (
                                                <div className="border-t border-gray-200 px-4 py-4 space-y-4">
                                                    {/* Subtotal */}
                                                    <div className="flex items-center justify-between text-base font-medium text-gray-900">
                                                        <span>Subtotal</span>
                                                        <span>{formatCurrency(subtotal)}</span>
                                                    </div>

                                                    <p className="text-sm text-gray-500">
                                                        Envío calculado al finalizar la compra.
                                                    </p>

                                                    {/* Checkout Button */}
                                                    <Link
                                                        href={tenant.slug ? `/t/${tenant.slug}/checkout` : '/checkout'}
                                                        onClick={closeCart}
                                                        className="w-full flex items-center justify-center gap-2 px-6 py-3 text-white font-medium rounded-lg transition-all hover:opacity-90"
                                                        style={{ backgroundColor: primaryColor }}
                                                    >
                                                        Finalizar Compra
                                                        <ArrowRight className="h-4 w-4" />
                                                    </Link>

                                                    {/* Continue Shopping */}
                                                    <button
                                                        type="button"
                                                        onClick={closeCart}
                                                        className="w-full text-center text-sm text-gray-500 hover:text-gray-700 transition-colors"
                                                    >
                                                        Continuar comprando
                                                    </button>
                                                </div>
                                            )}
                                        </div>
                                    </Dialog.Panel>
                                </Transition.Child>
                            </div>
                        </div>
                    </div>
                </Dialog>
            </Transition.Root>
        </div>
    );
}
