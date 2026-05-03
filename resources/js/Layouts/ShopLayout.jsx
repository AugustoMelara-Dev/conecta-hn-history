/**
 * ShopLayout.jsx - Immersive Tenant Store Layout
 * 
 * ARCHITECTURE: "Walled Garden" Pattern
 * When a user visits a tenant store, the interface transforms into a
 * branded, immersive experience where the platform's branding becomes
 * secondary. The goal is conversion, not exploration.
 * 
 * KEY FEATURES:
 * - Tenant branding prominently displayed
 * - Navigation restricted to store-only actions
 * - No escape routes to the directory
 * - Cart integration in bottom nav
 * - "Powered by Vito Business OS" relegated to footer
 */

import { useState } from 'react';
import { Link } from '@inertiajs/react';
import {
    Store, Grid3X3, ShoppingBag, MessageCircle,
    ChevronLeft, Phone, MapPin
} from 'lucide-react';
import { cn } from '@/lib/utils';
import useCartStore from '@/Stores/useCartStore';
import FloatingCart from '@/Components/Tenant/FloatingCart';
import { buildWhatsAppUrl } from '@/Utils/phoneUtils';

export default function ShopLayout({
    children,
    tenant,
    tenantPlan = 'semilla',
    showCart = true,
}) {
    const [isCartOpen, setIsCartOpen] = useState(false);
    const cartItems = useCartStore(state => state.items);
    const cartItemCount = cartItems.reduce((sum, item) => sum + item.quantity, 0);

    // Safe accessors
    const tenantName = tenant?.name || 'Tienda';
    const tenantLogo = tenant?.logo_path || tenant?.logo;
    const tenantSlug = tenant?.slug || '';
    const whatsappNumber = tenant?.whatsapp || tenant?.whatsapp_number || '';
    const primaryColor = tenant?.brand_color || tenant?.primary_color || '#6366f1';
    const isPro = tenantPlan !== 'semilla';

    // Build WhatsApp URL
    const whatsappUrl = whatsappNumber
        ? buildWhatsAppUrl(whatsappNumber, `Hola ${tenantName}!`)
        : null;

    return (
        <div
            className="flex flex-col min-h-[100dvh] bg-slate-950"
            style={{ '--primary-color': primaryColor }}
        >
            {/* ═══════════════════════════════════════════════════════════════
                HEADER: Tenant Branding (White-Label Mode)
                Platform branding is minimized - tenant is the star
            ═══════════════════════════════════════════════════════════════ */}
            <header className="sticky top-0 z-50 bg-slate-950/80 backdrop-blur-xl border-b border-white/5">
                <div className="flex items-center justify-between px-4 h-14">
                    {/* Back to Directory (subtle) */}
                    <Link
                        href={route('directory.index')}
                        className="flex items-center gap-1 text-slate-400 hover:text-white transition-colors text-sm"
                    >
                        <ChevronLeft className="w-4 h-4" />
                        <span className="hidden sm:inline">Directorio</span>
                    </Link>

                    {/* Tenant Branding - PRIORITY */}
                    <div className="flex items-center gap-2">
                        {tenantLogo ? (
                            <img
                                src={tenantLogo}
                                alt={tenantName}
                                className="h-8 w-auto object-contain"
                            />
                        ) : (
                            <span className="font-bold text-white text-lg truncate max-w-[200px]">
                                {tenantName}
                            </span>
                        )}
                    </div>

                    {/* Cart Button (Desktop) */}
                    {isPro && showCart && (
                        <button
                            type="button"
                            onClick={() => setIsCartOpen(true)}
                            aria-label={`Abrir carrito${cartItemCount > 0 ? `, ${cartItemCount} productos` : ''}`}
                            className="relative rounded-md p-2 text-slate-400 transition-colors hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/70 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-950"
                        >
                            <ShoppingBag className="w-5 h-5" aria-hidden="true" />
                            {cartItemCount > 0 && (
                                <span className="absolute -top-1 -right-1 bg-red-500 text-white text-xs font-bold w-5 h-5 flex items-center justify-center rounded-full">
                                    {cartItemCount > 9 ? '9+' : cartItemCount}
                                </span>
                            )}
                        </button>
                    )}
                </div>
            </header>

            {/* ═══════════════════════════════════════════════════════════════
                MAIN CONTENT: Full-width immersive experience
            ═══════════════════════════════════════════════════════════════ */}
            <main className="flex-1 overflow-y-auto pb-20 md:pb-8">
                {children}
            </main>

            {/* ═══════════════════════════════════════════════════════════════
                FOOTER: Powered by Vito Business OS (White-Label Attribution)
            ═══════════════════════════════════════════════════════════════ */}
            <footer className="hidden md:block py-4 text-center border-t border-white/5">
                <p className="text-xs text-slate-500">
                    Powered by{' '}
                    <Link href="/" className="text-slate-400 hover:text-white transition-colors">
                        Vito Business OS
                    </Link>
                </p>
            </footer>

            {/* ═══════════════════════════════════════════════════════════════
                MOBILE BOTTOM NAV: Conversion-Focused (4 Actions Only)
                - Home: Return to store main
                - Catalog: Scroll to products
                - Cart: Open cart modal
                - WhatsApp: Direct contact
                
                NO directory links, NO create button - pure commerce
            ═══════════════════════════════════════════════════════════════ */}
            <nav className="md:hidden fixed bottom-0 left-0 right-0 z-50 bg-slate-950/95 backdrop-blur-xl border-t border-white/10">
                <div className="flex items-center justify-around h-16">
                    {/* Home */}
                    <Link
                        href={tenantSlug ? `/t/${tenantSlug}` : '#'}
                        className="flex flex-col items-center gap-1 text-slate-400 hover:text-white transition-colors px-4 py-2"
                    >
                        <Store className="w-5 h-5" />
                        <span className="text-[10px] font-medium">Inicio</span>
                    </Link>

                    {/* Catalog (Scroll anchor) */}
                    <a
                        href="#catalogo"
                        className="flex flex-col items-center gap-1 text-slate-400 hover:text-white transition-colors px-4 py-2"
                    >
                        <Grid3X3 className="w-5 h-5" />
                        <span className="text-[10px] font-medium">Catálogo</span>
                    </a>

                    {/* Cart (Pro only) or Contact (Semilla) */}
                    {isPro ? (
                        <button
                            type="button"
                            onClick={() => setIsCartOpen(true)}
                            aria-label={`Abrir carrito${cartItemCount > 0 ? `, ${cartItemCount} productos` : ''}`}
                            className="relative flex flex-col items-center gap-1 rounded-lg px-4 py-2 text-slate-400 transition-colors hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/70"
                        >
                            <ShoppingBag className="w-5 h-5" aria-hidden="true" />
                            {cartItemCount > 0 && (
                                <span className="absolute top-0 right-2 bg-red-500 text-white text-[10px] font-bold w-4 h-4 flex items-center justify-center rounded-full">
                                    {cartItemCount > 9 ? '9+' : cartItemCount}
                                </span>
                            )}
                            <span className="text-[10px] font-medium">Carrito</span>
                        </button>
                    ) : (
                        <a
                            href={`tel:${whatsappNumber}`}
                            className="flex flex-col items-center gap-1 text-slate-400 hover:text-white transition-colors px-4 py-2"
                        >
                            <Phone className="w-5 h-5" />
                            <span className="text-[10px] font-medium">Llamar</span>
                        </a>
                    )}

                    {/* WhatsApp - Primary CTA */}
                    {whatsappUrl ? (
                        <a
                            href={whatsappUrl}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="flex flex-col items-center gap-1 px-4 py-2"
                            style={{ color: '#25D366' }}
                        >
                            <MessageCircle className="w-5 h-5" />
                            <span className="text-[10px] font-medium">WhatsApp</span>
                        </a>
                    ) : (
                        <a
                            href="#contacto"
                            className="flex flex-col items-center gap-1 text-slate-400 hover:text-white transition-colors px-4 py-2"
                        >
                            <MapPin className="w-5 h-5" />
                            <span className="text-[10px] font-medium">Ubicación</span>
                        </a>
                    )}
                </div>
            </nav>

            {/* ═══════════════════════════════════════════════════════════════
                FLOATING CART MODAL (Pro tier only)
            ═══════════════════════════════════════════════════════════════ */}
            {isPro && showCart && (
                <FloatingCart
                    isOpen={isCartOpen}
                    onClose={() => setIsCartOpen(false)}
                    tenant={tenant}
                />
            )}
        </div>
    );
}
