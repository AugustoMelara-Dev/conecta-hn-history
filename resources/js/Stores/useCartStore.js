/**
 * useCartStore - Zustand Store for Pro Tier Cart with Order Management
 * 
 * ENHANCED FEATURES:
 * - LocalStorage persistence via persist middleware
 * - Customer data capture (name, phone, address)
 * - Async order submission to backend API
 * - Loading states and error handling
 * - Smart addItem that merges identical products+modifiers
 * 
 * TRANSACTIONAL HANDSHAKE PATTERN:
 * Frontend submits to API → API persists order → API returns WhatsApp URL
 * The browser NEVER opens WhatsApp without a persisted order.
 * 
 * @NOTE: This store is ONLY used for Pro/Enterprise tiers.
 * Semilla tier bypasses the cart entirely with DirectWhatsAppButton.
 */

import { create } from 'zustand';
import { persist, createJSONStorage } from 'zustand/middleware';
import axios from 'axios';

/**
 * Generate a unique key for cart item deduplication
 * Based on product ID + sorted modifier selection
 */
const generateItemKey = (productId, selectedModifiers = []) => {
    const modifierIds = selectedModifiers
        .map(m => `${m.groupId}:${m.optionId}`)
        .sort()
        .join('|');
    return `${productId}-${modifierIds}`;
};

/**
 * Calculate total price for a cart item (base + modifiers)
 */
const calculateItemTotal = (basePrice, quantity, selectedModifiers = []) => {
    const modifiersTotal = selectedModifiers.reduce((sum, m) => sum + (m.price || 0), 0);
    return (basePrice + modifiersTotal) * quantity;
};

const useCartStore = create(
    persist(
        (set, get) => ({
            // ═══════════════════════════════════════════════════════════════════════
            // STATE: Cart Items
            // ═══════════════════════════════════════════════════════════════════════
            items: [],
            tenantId: null,
            tenantPhone: null,
            tenantName: null,
            tenantSlug: null,

            // ═══════════════════════════════════════════════════════════════════════
            // STATE: Customer Data (Persisted to localStorage)
            // ═══════════════════════════════════════════════════════════════════════
            customerName: '',
            customerPhone: '',
            customerEmail: '',
            customerAddress: '',
            customerNotes: '',
            deliveryType: 'pickup',  // 'pickup' | 'delivery'
            paymentMethod: 'pending', // 'pending' | 'cash' | 'card' | 'transfer'

            // ═══════════════════════════════════════════════════════════════════════
            // STATE: Coupon / Discount
            // ═══════════════════════════════════════════════════════════════════════
            couponCode: null,
            discountAmount: 0,
            couponType: null,     // 'fixed' | 'percent'
            couponValue: null,     // The raw value (e.g., 10 for 10%)
            couponError: null,
            isApplyingCoupon: false,

            // ═══════════════════════════════════════════════════════════════════════
            // STATE: Order Submission
            // ═══════════════════════════════════════════════════════════════════════
            isSubmitting: false,
            submitError: null,
            lastOrderNumber: null,
            lastOrderId: null,

            // ═══════════════════════════════════════════════════════════════════════
            // STATE: Cart Drawer UI Control
            // ═══════════════════════════════════════════════════════════════════════
            isCartOpen: false,

            /**
             * Toggle cart drawer visibility
             */
            toggleCart: () => set((state) => ({ isCartOpen: !state.isCartOpen })),

            /**
             * Open cart drawer
             */
            openCart: () => set({ isCartOpen: true }),

            /**
             * Close cart drawer
             */
            closeCart: () => set({ isCartOpen: false }),

            // ═══════════════════════════════════════════════════════════════════════
            // ACTIONS: Tenant Initialization
            // ═══════════════════════════════════════════════════════════════════════

            /**
             * Initialize cart for a specific tenant
             * Clears cart if switching to a different tenant
             */
            initForTenant: (tenantSlug, tenantPhone, tenantName, tenantId = null) => {
                const currentSlug = get().tenantSlug;
                if (currentSlug && currentSlug !== tenantSlug) {
                    // Different tenant - clear cart items but KEEP customer data
                    set({
                        items: [],
                        tenantSlug,
                        tenantPhone,
                        tenantName,
                        tenantId,
                    });
                } else {
                    // Same tenant or first init - just update tenant info
                    set({ tenantSlug, tenantPhone, tenantName, tenantId });
                }
            },

            // ═══════════════════════════════════════════════════════════════════════
            // ACTIONS: Customer Data
            // ═══════════════════════════════════════════════════════════════════════

            setCustomerName: (name) => set({ customerName: name }),
            setCustomerPhone: (phone) => set({ customerPhone: phone }),
            setCustomerEmail: (email) => set({ customerEmail: email }),
            setCustomerAddress: (address) => set({ customerAddress: address }),
            setCustomerNotes: (notes) => set({ customerNotes: notes }),
            setDeliveryType: (type) => set({ deliveryType: type }),
            setPaymentMethod: (method) => set({ paymentMethod: method }),

            /**
             * Update multiple customer fields at once
             */
            updateCustomerData: (data) => set({
                customerName: data.customerName ?? get().customerName,
                customerPhone: data.customerPhone ?? get().customerPhone,
                customerEmail: data.customerEmail ?? get().customerEmail,
                customerAddress: data.customerAddress ?? get().customerAddress,
                customerNotes: data.customerNotes ?? get().customerNotes,
                deliveryType: data.deliveryType ?? get().deliveryType,
                paymentMethod: data.paymentMethod ?? get().paymentMethod,
            }),

            // ═══════════════════════════════════════════════════════════════════════
            // ACTIONS: Cart Items
            // ═══════════════════════════════════════════════════════════════════════

            /**
             * Add item to cart with smart merging
             * If same product + same modifiers exists, increment quantity
             */
            addItem: (product, quantity = 1, selectedModifiers = [], notes = '') => {
                const items = get().items;
                const itemKey = generateItemKey(product.id, selectedModifiers);

                const existingIndex = items.findIndex(
                    item => generateItemKey(item.productId, item.selectedModifiers) === itemKey
                );

                if (existingIndex >= 0) {
                    // Merge: increment quantity
                    const updatedItems = [...items];
                    updatedItems[existingIndex] = {
                        ...updatedItems[existingIndex],
                        quantity: updatedItems[existingIndex].quantity + quantity,
                        notes: notes || updatedItems[existingIndex].notes,
                    };
                    set({ items: updatedItems });
                } else {
                    // New item
                    const newItem = {
                        id: `${product.id}-${Date.now()}`,
                        productId: product.id,
                        name: product.name,
                        price: product.price,
                        image: product.image,
                        quantity,
                        selectedModifiers,
                        notes,
                    };
                    set({ items: [...items, newItem] });
                }
            },

            /**
             * Remove item from cart by cart item ID
             */
            removeItem: (cartItemId) => {
                set({
                    items: get().items.filter(item => item.id !== cartItemId),
                });
            },

            /**
             * Update quantity of a cart item
             */
            updateQuantity: (cartItemId, newQuantity) => {
                if (newQuantity <= 0) {
                    get().removeItem(cartItemId);
                    return;
                }

                set({
                    items: get().items.map(item =>
                        item.id === cartItemId
                            ? { ...item, quantity: newQuantity }
                            : item
                    ),
                });
            },

            /**
             * Clear cart items only (preserve customer data)
             */
            clearCart: () => {
                set({
                    items: [],
                    couponCode: null,
                    discountAmount: 0,
                    couponType: null,
                    couponValue: null,
                    couponError: null,
                    lastOrderNumber: null,
                    lastOrderId: null,
                    submitError: null,
                });
            },

            /**
             * Reset entire store including customer data
             */
            resetStore: () => {
                set({
                    items: [],
                    customerName: '',
                    customerPhone: '',
                    customerEmail: '',
                    customerAddress: '',
                    customerNotes: '',
                    deliveryType: 'pickup',
                    paymentMethod: 'pending',
                    couponCode: null,
                    discountAmount: 0,
                    couponType: null,
                    couponValue: null,
                    couponError: null,
                    isSubmitting: false,
                    submitError: null,
                    lastOrderNumber: null,
                    lastOrderId: null,
                });
            },

            // ═══════════════════════════════════════════════════════════════════════
            // ACTIONS: Coupon Management
            // ═══════════════════════════════════════════════════════════════════════

            /**
             * Apply a coupon code via API validation
             */
            applyCoupon: async (code) => {
                const state = get();

                if (!code || code.trim() === '') {
                    set({ couponError: 'Ingresa un código de cupón' });
                    return { success: false, error: 'Ingresa un código de cupón' };
                }

                if (!state.tenantId) {
                    set({ couponError: 'Error de configuración' });
                    return { success: false, error: 'Error de configuración' };
                }

                const subtotal = get().getSubtotal();

                set({ isApplyingCoupon: true, couponError: null });

                try {
                    const response = await axios.post('/api/coupons/verify', {
                        tenant_id: state.tenantId,
                        code: code.trim().toUpperCase(),
                        cart_subtotal: subtotal,
                    });

                    if (response.data.valid) {
                        set({
                            couponCode: response.data.coupon_code,
                            discountAmount: response.data.discount_amount,
                            couponType: response.data.coupon_type,
                            couponValue: response.data.coupon_value,
                            couponError: null,
                            isApplyingCoupon: false,
                        });

                        return {
                            success: true,
                            discountAmount: response.data.discount_amount,
                        };
                    } else {
                        throw new Error(response.data.error || 'Cupón inválido');
                    }
                } catch (error) {
                    const errorMessage = error.response?.data?.error
                        || error.message
                        || 'Error al validar cupón';

                    set({
                        couponCode: null,
                        discountAmount: 0,
                        couponType: null,
                        couponValue: null,
                        couponError: errorMessage,
                        isApplyingCoupon: false,
                    });

                    return { success: false, error: errorMessage };
                }
            },

            /**
             * Remove applied coupon
             */
            removeCoupon: () => {
                set({
                    couponCode: null,
                    discountAmount: 0,
                    couponType: null,
                    couponValue: null,
                    couponError: null,
                });
            },

            /**
             * Revalidate coupon after cart changes
             * Called internally when items change
             */
            revalidateCoupon: async () => {
                const { couponCode, tenantId } = get();

                if (!couponCode || !tenantId) return;

                const subtotal = get().getSubtotal();

                try {
                    const response = await axios.post('/api/coupons/verify', {
                        tenant_id: tenantId,
                        code: couponCode,
                        cart_subtotal: subtotal,
                    });

                    if (response.data.valid) {
                        set({
                            discountAmount: response.data.discount_amount,
                            couponError: null,
                        });
                    } else {
                        // Coupon no longer valid - remove it
                        set({
                            couponCode: null,
                            discountAmount: 0,
                            couponType: null,
                            couponValue: null,
                            couponError: response.data.error,
                        });
                    }
                } catch (error) {
                    // Silent failure - just remove coupon
                    set({
                        couponCode: null,
                        discountAmount: 0,
                        couponType: null,
                        couponValue: null,
                        couponError: null,
                    });
                }
            },

            // ═══════════════════════════════════════════════════════════════════════
            // SELECTORS: Computed Values
            // ═══════════════════════════════════════════════════════════════════════

            getTotalItems: () => {
                return get().items.reduce((sum, item) => sum + item.quantity, 0);
            },

            /**
             * Get cart subtotal (before discount)
             */
            getSubtotal: () => {
                return get().items.reduce(
                    (sum, item) => sum + calculateItemTotal(item.price, item.quantity, item.selectedModifiers),
                    0
                );
            },

            /**
             * Get grand total (subtotal - discount)
             */
            getGrandTotal: () => {
                const subtotal = get().getSubtotal();
                const discount = get().discountAmount || 0;
                return Math.max(0, subtotal - discount);
            },

            /**
             * Validate customer data for checkout
             */
            validateCheckout: () => {
                const { items, customerName, customerPhone, deliveryType, customerAddress } = get();
                const errors = {};

                if (items.length === 0) {
                    errors.items = 'El carrito está vacío';
                }

                if (!customerName || customerName.trim().length < 2) {
                    errors.customerName = 'Ingresa tu nombre';
                }

                if (!customerPhone || customerPhone.trim().length < 8) {
                    errors.customerPhone = 'Ingresa un teléfono válido';
                }

                if (deliveryType === 'delivery' && (!customerAddress || customerAddress.trim().length < 5)) {
                    errors.customerAddress = 'Ingresa tu dirección de entrega';
                }

                return {
                    isValid: Object.keys(errors).length === 0,
                    errors,
                };
            },

            // ═══════════════════════════════════════════════════════════════════════
            // ACTIONS: Order Submission (Transactional Handshake)
            // ═══════════════════════════════════════════════════════════════════════

            /**
             * Submit order to backend API
             * Returns the WhatsApp URL on success
             * 
             * FLOW:
             * 1. Validate customer data
             * 2. Build API payload
             * 3. POST to /api/orders
             * 4. On success: clear cart, return WhatsApp URL
             * 5. On error: set error state
             */
            submitOrder: async () => {
                const state = get();

                // Prevent double submission
                if (state.isSubmitting) {
                    return { success: false, error: 'Ya se está procesando el pedido' };
                }

                // Validate
                const validation = get().validateCheckout();
                if (!validation.isValid) {
                    set({ submitError: Object.values(validation.errors)[0] });
                    return {
                        success: false,
                        error: Object.values(validation.errors)[0],
                        validationErrors: validation.errors,
                    };
                }

                // Start submission
                set({ isSubmitting: true, submitError: null });

                try {
                    // Build API payload matching OrderController validation
                    const payload = {
                        tenant_id: state.tenantId,
                        customer_name: state.customerName.trim(),
                        customer_phone: state.customerPhone.trim(),
                        customer_email: state.customerEmail?.trim() || null,
                        customer_address: state.deliveryType === 'delivery'
                            ? state.customerAddress.trim()
                            : null,
                        customer_notes: state.customerNotes?.trim() || null,
                        delivery_type: state.deliveryType,
                        payment_method: state.paymentMethod,
                        items: state.items.map(item => ({
                            product_id: item.productId,
                            name: item.name,
                            price: item.price,
                            quantity: item.quantity,
                            options: item.selectedModifiers?.map(mod => ({
                                group: mod.groupName,
                                option: mod.name,
                                price: mod.price || 0,
                            })) || [],
                            notes: item.notes || null,
                        })),
                        // Coupon code (if applied)
                        coupon_code: state.couponCode || null,
                    };

                    // Make API call
                    const response = await axios.post('/api/orders', payload);

                    if (response.data.success) {
                        const { order_number, order_id, whatsapp_url } = response.data.data;

                        // Clear cart items but preserve customer data for future purchases
                        set({
                            items: [],
                            isSubmitting: false,
                            submitError: null,
                            lastOrderNumber: order_number,
                            lastOrderId: order_id,
                        });

                        return {
                            success: true,
                            orderNumber: order_number,
                            orderId: order_id,
                            whatsappUrl: whatsapp_url,
                        };
                    } else {
                        throw new Error(response.data.message || 'Error al crear el pedido');
                    }
                } catch (error) {
                    const errorMessage = error.response?.data?.message
                        || error.message
                        || 'Error de conexión. Intenta de nuevo.';

                    set({
                        isSubmitting: false,
                        submitError: errorMessage,
                    });

                    return {
                        success: false,
                        error: errorMessage,
                    };
                }
            },

            /**
             * Clear submission error
             */
            clearError: () => set({ submitError: null }),
        }),
        {
            name: 'conecta-cart', // localStorage key
            storage: createJSONStorage(() => localStorage),
            // Persist cart items, tenant info, AND customer data for returning users
            partialize: (state) => ({
                items: state.items,
                tenantPhone: state.tenantPhone,
                tenantName: state.tenantName,
                tenantSlug: state.tenantSlug,
                tenantId: state.tenantId,
                // Customer data - persisted for returning user experience
                customerName: state.customerName,
                customerPhone: state.customerPhone,
                customerEmail: state.customerEmail,
                customerAddress: state.customerAddress,
                deliveryType: state.deliveryType,
            }),
        }
    )
);

export default useCartStore;
