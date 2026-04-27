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

// ═══════════════════════════════════════════════════════════════════════════
// CROSS-TAB SYNC CONSTANTS
// ═══════════════════════════════════════════════════════════════════════════
const STORAGE_KEY = 'conecta-cart';

/**
 * Generate a unique key for cart item deduplication
 * Based on product ID + sorted modifier selection
 * SAFETY: Guards against undefined groupId/optionId
 */
const generateItemKey = (productId, selectedModifiers = []) => {
    if (!selectedModifiers?.length) return `${productId}-`;

    const modifierIds = selectedModifiers
        .filter(m => m?.groupId != null && m?.optionId != null)
        .map(m => `${m.groupId}:${m.optionId}`)
        .sort()
        .join('|');
    return `${productId}-${modifierIds}`;
};

/**
 * Calculate total price for a cart item (base + modifiers)
 * SAFETY: Guards against undefined/null prices
 */
export const calculateItemTotal = (basePrice, quantity, selectedModifiers = []) => {
    const modifiersTotal = (selectedModifiers || []).reduce((sum, m) => sum + (Number(m?.price) || 0), 0);
    return ((Number(basePrice) || 0) + modifiersTotal) * (Number(quantity) || 1);
};

const checkoutDiagnostic = (status, probableCause, recommendedAction, traceId = null) => ({
    surface: 'checkout',
    status,
    probable_cause: probableCause,
    recommended_action: recommendedAction,
    trace_id: traceId,
});

const diagnosticFromError = (error, fallback) => error?.response?.data?.diagnostic || checkoutDiagnostic(
    fallback.status,
    error?.response?.data?.message || error?.message || fallback.probableCause,
    fallback.recommendedAction,
    error?.response?.headers?.['x-request-id'] || null
);

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
            checkoutSessionId: null,
            checkoutNonce: null,

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
            revalidateController: null, // For canceling pending requests

            // ═══════════════════════════════════════════════════════════════════════
            // STATE: Order Submission
            // ═══════════════════════════════════════════════════════════════════════
            isSubmitting: false,
            submitError: null,
            submitDiagnostic: null,
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
             * 
             * PRIVACY PROTOCOL:
             * When switching tenants, ALL customer PII is sanitized to prevent
             * cross-tenant data leakage on shared devices (cyber cafes, etc.)
             * 
             * This is a privacy-by-design decision that prioritizes security
             * over convenience (magic autocomplete).
             */
            initForTenant: (tenantSlug, tenantPhone, tenantName, tenantId = null) => {
                const currentSlug = get().tenantSlug;

                if (currentSlug && currentSlug !== tenantSlug) {
                    // ═══════════════════════════════════════════════════════════════
                    // CONTEXT SWITCH DETECTED: Strict PII Sanitization
                    // ═══════════════════════════════════════════════════════════════
                    // Scenario: User A shops at "Pizzería" then leaves computer,
                    // User B visits "Ferretería" - they must NOT see User A's data.
                    // ═══════════════════════════════════════════════════════════════
                    console.info('[Cart] Tenant switch detected - sanitizing customer data for privacy');

                    set({
                        // Clear cart items (product context is different)
                        items: [],

                        // Update tenant context
                        tenantSlug,
                        tenantPhone,
                        tenantName,
                        tenantId,
                        checkoutSessionId: null,
                        checkoutNonce: null,

                        // ═══════════════════════════════════════════════════════════
                        // PII SANITIZATION: Reset ALL customer data to defaults
                        // ═══════════════════════════════════════════════════════════
                        customerName: '',
                        customerPhone: '',
                        customerEmail: '',
                        customerAddress: '',
                        customerNotes: '',
                        deliveryType: 'pickup',
                        paymentMethod: 'pending',

                        // Clear any applied coupons (tenant-specific)
                        couponCode: null,
                        discountAmount: 0,
                        couponType: null,
                        couponValue: null,
                        couponError: null,
                    });
                } else {
                    // Same tenant or first init - just update tenant info
                    set({ tenantSlug, tenantPhone, tenantName, tenantId });
                }
            },

            ensureCheckoutSession: async () => {
                const state = get();

                if (state.checkoutSessionId && state.checkoutNonce) {
                    return {
                        sessionId: state.checkoutSessionId,
                        nonce: state.checkoutNonce,
                    };
                }

                if (!state.tenantId) {
                    const diagnostic = checkoutDiagnostic(
                        'Checkout sin negocio identificado',
                        'El carrito no tiene tenant_id activo para crear la sesion de compra.',
                        'Vuelve al catalogo del negocio y abre el carrito desde ahi.'
                    );
                    set({ submitDiagnostic: diagnostic });
                    throw new Error('No se pudo iniciar checkout: tenant no identificado.');
                }

                let response;
                try {
                    response = await axios.post('/api/v1/checkout/session', {
                        tenant: state.tenantId,
                    });
                } catch (error) {
                    const diagnostic = diagnosticFromError(error, {
                        status: 'No pudimos iniciar checkout',
                        probableCause: 'La sesion segura de checkout no pudo crearse.',
                        recommendedAction: 'Reintenta en unos segundos. Si persiste, comparte esta referencia con soporte.',
                    });
                    set({ submitDiagnostic: diagnostic });
                    throw error;
                }

                const sessionId = response.data.session_id;
                const nonce = response.data.checkout_nonce;

                set({
                    checkoutSessionId: sessionId,
                    checkoutNonce: nonce,
                });

                return { sessionId, nonce };
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

                // Revalidate coupon if items changed (minimum subtotal may have changed)
                get().revalidateCoupon();
            },

            /**
             * Remove item from cart by cart item ID
             */
            removeItem: (cartItemId) => {
                set({
                    items: get().items.filter(item => item.id !== cartItemId),
                });
                // Revalidate coupon after removal (minimum subtotal may have changed)
                get().revalidateCoupon();
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
                // Revalidate coupon after quantity change
                get().revalidateCoupon();
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
             * 
             * OPTIMIZATION: Uses AbortController to cancel pending requests
             * This prevents race conditions when user clicks +/- rapidly
             */
            revalidateCoupon: async () => {
                const { couponCode, tenantId, revalidateController } = get();

                // Cancel previous request if it exists
                if (revalidateController) {
                    revalidateController.abort();
                }

                if (!couponCode || !tenantId) return;

                // Create new controller for this request
                const controller = new AbortController();
                set({ revalidateController: controller });

                const subtotal = get().getSubtotal();

                try {
                    const response = await axios.post('/api/coupons/verify', {
                        tenant_id: tenantId,
                        code: couponCode,
                        cart_subtotal: subtotal,
                    }, {
                        signal: controller.signal // Bind signal to axios
                    });

                    if (response.data.valid) {
                        set({
                            discountAmount: response.data.discount_amount,
                            couponError: null,
                            revalidateController: null // Cleanup
                        });
                    } else {
                        // Coupon no longer valid - remove it
                        set({
                            couponCode: null,
                            discountAmount: 0,
                            couponType: null,
                            couponValue: null,
                            couponError: response.data.error,
                            revalidateController: null
                        });
                    }
                } catch (error) {
                    // Ignore abort errors
                    if (axios.isCancel(error)) {
                        return;
                    }

                    // Silent failure - just remove coupon
                    set({
                        couponCode: null,
                        discountAmount: 0,
                        couponType: null,
                        couponValue: null,
                        couponError: null,
                        revalidateController: null
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

            // ═══════════════════════════════════════════════════════════════════════
            // WHATSAPP COMPILER: The Serializer
            // Transforms cart state into human-readable WhatsApp message
            // USAGE: For Semilla tier direct-to-WhatsApp flow (no API persistence)
            // ═══════════════════════════════════════════════════════════════════════

            /**
             * Compile cart state into formatted WhatsApp message text
             * @param {string} customerNotes - Optional notes from customer
             * @returns {string} - Formatted message ready for encodeURIComponent
             */
            getWhatsappMessage: (customerNotes = '') => {
                const state = get();
                const { items, tenantName, discountAmount, couponCode } = state;

                if (items.length === 0) return '';

                const lines = [];

                // Header
                lines.push(`Hola *${tenantName || 'Tienda'}*, quiero hacer un pedido:`);
                lines.push('');

                // Items with modifiers
                items.forEach(item => {
                    const modifiersTotal = (item.selectedModifiers || []).reduce((s, m) => s + (m.price || 0), 0);
                    const itemTotal = (item.price + modifiersTotal) * item.quantity;

                    // Item line: "2x *Hamburguesa* (L. 150.00)"
                    lines.push(`${item.quantity}x *${item.name}* (L. ${itemTotal.toFixed(2)})`);

                    // Modifiers (indented)
                    if (item.selectedModifiers?.length > 0) {
                        item.selectedModifiers.forEach(mod => {
                            if (mod.price > 0) {
                                lines.push(`   + ${mod.name} (L. ${mod.price.toFixed(2)})`);
                            } else {
                                lines.push(`   + ${mod.name}`);
                            }
                        });
                    }

                    // Item notes
                    if (item.notes) {
                        lines.push(`   📝 ${item.notes}`);
                    }

                    lines.push(''); // Spacer between items
                });

                // Divider
                lines.push('--------------------------------');

                // Discount (if applied)
                if (discountAmount > 0 && couponCode) {
                    const subtotal = state.getSubtotal();
                    lines.push(`Subtotal: L. ${subtotal.toFixed(2)}`);
                    lines.push(`Descuento (${couponCode}): -L. ${discountAmount.toFixed(2)}`);
                }

                // Total
                const grandTotal = state.getGrandTotal();
                lines.push(`*Total: L. ${grandTotal.toFixed(2)}*`);
                lines.push('--------------------------------');

                // Customer notes
                if (customerNotes) {
                    lines.push('');
                    lines.push(`Notas: ${customerNotes}`);
                }

                return lines.join('\n');
            },

            /**
             * Generate complete WhatsApp API link with sanitized phone
             * @param {string} customerNotes - Optional notes from customer
             * @returns {string} - Full wa.me URL ready for window.open()
             */
            getWhatsappLink: (customerNotes = '') => {
                const state = get();
                const { tenantPhone } = state;

                if (!tenantPhone) {
                    console.warn('[Cart] getWhatsappLink called without tenantPhone');
                    return '';
                }

                // SECURITY: Sanitize phone - remove ALL non-numeric characters
                // Handles formats like: +504 9999-1234, (504) 99991234, etc.
                const sanitizedPhone = tenantPhone.replace(/\D/g, '');

                if (!sanitizedPhone) {
                    console.warn('[Cart] Phone sanitization resulted in empty string');
                    return '';
                }

                const message = state.getWhatsappMessage(customerNotes);
                const encodedMessage = encodeURIComponent(message);

                return `https://wa.me/${sanitizedPhone}?text=${encodedMessage}`;
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
                    const firstError = Object.values(validation.errors)[0];
                    set({
                        submitError: firstError,
                        submitDiagnostic: checkoutDiagnostic(
                            'Datos de entrega incompletos',
                            firstError,
                            'Completa los campos marcados antes de enviar el pedido.'
                        ),
                    });
                    return {
                        success: false,
                        error: firstError,
                        validationErrors: validation.errors,
                    };
                }

                // Start submission
                set({ isSubmitting: true, submitError: null, submitDiagnostic: null });

                try {
                    const checkoutSession = await get().ensureCheckoutSession();

                    // Build API payload matching OrderController validation
                    const payload = {
                        tenant_id: state.tenantId,
                        session_id: checkoutSession.sessionId,
                        checkout_nonce: checkoutSession.nonce,
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
                    const response = await axios.post('/api/orders', payload, {
                        headers: {
                            'X-Checkout-Session': checkoutSession.sessionId,
                            'X-Checkout-Nonce': checkoutSession.nonce,
                            'Idempotency-Key': checkoutSession.sessionId,
                        },
                    });

                    if (response.data.success) {
                        const { order_number, order_id, whatsapp_url } = response.data.data;

                        // Clear cart items but preserve customer data for future purchases
                        set({
                            items: [],
                            isSubmitting: false,
                            submitError: null,
                            submitDiagnostic: null,
                            lastOrderNumber: order_number,
                            lastOrderId: order_id,
                            checkoutSessionId: null,
                            checkoutNonce: null,
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
                    const diagnostic = diagnosticFromError(error, {
                        status: 'Pedido no enviado',
                        probableCause: errorMessage,
                        recommendedAction: 'Revisa los datos, confirma que sigues en el negocio correcto e intenta de nuevo.',
                    });

                    set({
                        isSubmitting: false,
                        submitError: errorMessage,
                        submitDiagnostic: diagnostic,
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
            clearError: () => set({ submitError: null, submitDiagnostic: null }),
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

// ═══════════════════════════════════════════════════════════════════════════
// CROSS-TAB SYNC: Listen for storage changes from other tabs
// When user adds to cart in Tab A, Tab B sees the update immediately
// ═══════════════════════════════════════════════════════════════════════════
if (typeof window !== 'undefined') {
    const handleStorageChange = (event) => {
        // Only react to our cart storage key
        if (event.key !== STORAGE_KEY) return;

        // Parse the new value and update state
        try {
            const newState = JSON.parse(event.newValue);
            if (newState?.state) {
                // Merge the persisted state into current store
                useCartStore.setState({
                    items: newState.state.items || [],
                    tenantPhone: newState.state.tenantPhone,
                    tenantName: newState.state.tenantName,
                    tenantSlug: newState.state.tenantSlug,
                    tenantId: newState.state.tenantId,
                    customerName: newState.state.customerName || '',
                    customerPhone: newState.state.customerPhone || '',
                    customerEmail: newState.state.customerEmail || '',
                    customerAddress: newState.state.customerAddress || '',
                    deliveryType: newState.state.deliveryType || 'pickup',
                });
            }
        } catch (e) {
            // Silently fail on parse errors - localStorage might be corrupted
            console.warn('[CartStore] Cross-tab sync parse error:', e);
        }
    };

    window.addEventListener('storage', handleStorageChange);
}

export default useCartStore;
