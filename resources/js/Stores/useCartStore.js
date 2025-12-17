import { create } from 'zustand';
import { persist } from 'zustand/middleware';

/**
 * ═══════════════════════════════════════════════════════════════════════════
 * CONECTA HN - CART STORE (El Cerebro del Carrito)
 * ═══════════════════════════════════════════════════════════════════════════
 * 
 * Este store maneja la lógica compleja de un carrito con modificadores.
 * 
 * PROBLEMA CRÍTICO RESUELTO:
 * No podemos usar solo product_id para identificar items porque:
 * - Cliente pide "Hamburguesa + Extra Queso" → cartItemId: "123_[{queso}]"
 * - Cliente pide "Hamburguesa + Sin Cebolla" → cartItemId: "123_[{sincebolla}]"
 * Son el MISMO producto pero líneas DIFERENTES en el carrito.
 * 
 * SOLUCIÓN: cartItemId = product_id + hash(selectedOptions)
 */

/**
 * Genera un ID único para el item del carrito basado en producto + opciones
 */
const generateCartItemId = (productId, selectedOptions = []) => {
    const optionsKey = JSON.stringify(
        [...selectedOptions].sort((a, b) => a.id - b.id)
    );
    return `${productId}_${optionsKey}`;
};

/**
 * Calcula el precio total de un item (base + modificadores) * cantidad
 */
const calculateItemPrice = (item) => {
    const basePrice = parseFloat(item.price) || 0;
    const modifiersTotal = (item.selectedOptions || []).reduce(
        (sum, opt) => sum + (parseFloat(opt.price_modifier) || 0),
        0
    );
    return (basePrice + modifiersTotal) * item.quantity;
};

/**
 * Store principal del carrito con persistencia en localStorage
 */
const useCartStore = create(
    persist(
        (set, get) => ({
            // ═══ ESTADO ═══
            cart: [],
            isCartOpen: false,
            tenantId: null,
            tenantSlug: null,
            tenantName: null,
            tenantWhatsapp: null,

            // ═══ ACCIONES: CARRITO ═══

            /**
             * Inicializa el carrito para un tenant específico
             * Si el tenant cambia, limpia el carrito anterior
             */
            initializeCart: (tenant) => {
                const currentTenantId = get().tenantId;

                if (currentTenantId !== tenant.id) {
                    // Nuevo tenant, limpiar carrito
                    set({
                        cart: [],
                        tenantId: tenant.id,
                        tenantSlug: tenant.slug,
                        tenantName: tenant.name,
                        tenantWhatsapp: tenant.whatsapp,
                    });
                } else {
                    // Mismo tenant, solo actualizar info
                    set({
                        tenantSlug: tenant.slug,
                        tenantName: tenant.name,
                        tenantWhatsapp: tenant.whatsapp,
                    });
                }
            },

            /**
             * Agrega un item al carrito
             * Si ya existe un item con misma combinación producto+opciones, incrementa cantidad
             */
            addItem: (product, selectedOptions = [], quantity = 1, notes = '') => {
                const cartItemId = generateCartItemId(product.id, selectedOptions);
                const cart = get().cart;
                const existingIndex = cart.findIndex(item => item.cartItemId === cartItemId);

                if (existingIndex >= 0) {
                    // Ya existe, incrementar cantidad
                    const updatedCart = [...cart];
                    updatedCart[existingIndex] = {
                        ...updatedCart[existingIndex],
                        quantity: updatedCart[existingIndex].quantity + quantity,
                        notes: notes || updatedCart[existingIndex].notes,
                    };
                    set({ cart: updatedCart });
                } else {
                    // Nuevo item
                    const newItem = {
                        cartItemId,
                        productId: product.id,
                        name: product.name,
                        price: parseFloat(product.price),
                        image: product.image_path,
                        selectedOptions: selectedOptions.map(opt => ({
                            id: opt.id,
                            name: opt.name,
                            groupName: opt.groupName || opt.group_name,
                            price_modifier: parseFloat(opt.price_modifier) || 0,
                        })),
                        quantity,
                        notes,
                    };
                    set({ cart: [...cart, newItem] });
                }
            },

            /**
             * Actualiza la cantidad de un item
             */
            updateQuantity: (cartItemId, quantity) => {
                if (quantity <= 0) {
                    get().removeItem(cartItemId);
                    return;
                }

                const cart = get().cart.map(item =>
                    item.cartItemId === cartItemId
                        ? { ...item, quantity }
                        : item
                );
                set({ cart });
            },

            /**
             * Incrementa la cantidad de un item
             */
            incrementItem: (cartItemId) => {
                const cart = get().cart.map(item =>
                    item.cartItemId === cartItemId
                        ? { ...item, quantity: item.quantity + 1 }
                        : item
                );
                set({ cart });
            },

            /**
             * Decrementa la cantidad de un item (mínimo 1)
             */
            decrementItem: (cartItemId) => {
                const item = get().cart.find(i => i.cartItemId === cartItemId);
                if (!item) return;

                if (item.quantity <= 1) {
                    get().removeItem(cartItemId);
                } else {
                    const cart = get().cart.map(i =>
                        i.cartItemId === cartItemId
                            ? { ...i, quantity: i.quantity - 1 }
                            : i
                    );
                    set({ cart });
                }
            },

            /**
             * Elimina un item del carrito
             */
            removeItem: (cartItemId) => {
                const cart = get().cart.filter(item => item.cartItemId !== cartItemId);
                set({ cart });
            },

            /**
             * Limpia todo el carrito
             */
            clearCart: () => {
                set({ cart: [] });
            },

            // ═══ ACCIONES: UI ═══

            openCart: () => set({ isCartOpen: true }),
            closeCart: () => set({ isCartOpen: false }),
            toggleCart: () => set(state => ({ isCartOpen: !state.isCartOpen })),

            // ═══ GETTERS (Calculados) ═══

            /**
             * Obtiene el total del carrito
             */
            getCartTotal: () => {
                return get().cart.reduce((total, item) => total + calculateItemPrice(item), 0);
            },

            /**
             * Obtiene la cantidad total de items
             */
            getItemsCount: () => {
                return get().cart.reduce((count, item) => count + item.quantity, 0);
            },

            /**
             * Genera el mensaje de WhatsApp prellenado
             */
            generateWhatsAppMessage: () => {
                const { cart, tenantName } = get();
                if (cart.length === 0) return '';

                let message = `🛒 *Nuevo Pedido - ${tenantName}*\n\n`;

                cart.forEach((item, index) => {
                    const itemTotal = calculateItemPrice(item);
                    message += `*${index + 1}. ${item.name}* x${item.quantity}\n`;

                    if (item.selectedOptions?.length > 0) {
                        const optionsText = item.selectedOptions
                            .map(opt => `   • ${opt.name}`)
                            .join('\n');
                        message += `${optionsText}\n`;
                    }

                    if (item.notes) {
                        message += `   📝 ${item.notes}\n`;
                    }

                    message += `   💰 L. ${itemTotal.toFixed(2)}\n\n`;
                });

                const total = get().getCartTotal();
                message += `━━━━━━━━━━━━━━━\n`;
                message += `*TOTAL: L. ${total.toFixed(2)}*\n\n`;
                message += `📍 Mi dirección es: [COMPLETAR]\n`;
                message += `💳 Método de pago: [EFECTIVO/TRANSFERENCIA]`;

                return encodeURIComponent(message);
            },

            /**
             * Obtiene la URL de WhatsApp con mensaje prellenado
             */
            getWhatsAppUrl: () => {
                const { tenantWhatsapp } = get();
                if (!tenantWhatsapp) return '#';

                const phone = tenantWhatsapp.replace(/[^0-9]/g, '');
                const message = get().generateWhatsAppMessage();
                return `https://wa.me/${phone}?text=${message}`;
            },
        }),
        {
            name: 'conecta-cart',
            partialize: (state) => ({
                cart: state.cart,
                tenantId: state.tenantId,
                tenantSlug: state.tenantSlug,
                tenantName: state.tenantName,
                tenantWhatsapp: state.tenantWhatsapp,
            }),
        }
    )
);

export default useCartStore;

// Helper hooks para acceder a getters reactivamente
export const useCartTotal = () => useCartStore(state => state.getCartTotal());
export const useItemsCount = () => useCartStore(state => state.getItemsCount());
