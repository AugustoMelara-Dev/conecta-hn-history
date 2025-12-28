/**
 * useTenantContext.js - Tenant Context Abstraction Hook
 *
 * ARCHITECTURE:
 * - Abstracts tenant initialization from UI components
 * - Auto-initializes cart store for current tenant
 * - Returns standardized tenant data with derived values
 *
 * USAGE:
 * const { tenant, primaryColor } = useTenantContext();
 *
 * @module Hooks/useTenantContext
 */

import { useEffect, useMemo } from 'react';
import { usePage } from '@inertiajs/react';
import useCartStore from '@/Stores/useCartStore';

/**
 * Custom hook for tenant context management
 *
 * @returns {{
 *   tenant: Object,
 *   primaryColor: string,
 *   isInitialized: boolean
 * }}
 */
export function useTenantContext() {
    const { props } = usePage();

    // Extract tenant from various possible locations in props
    const tenant = useMemo(() => {
        return props.tenant || props.auth?.tenant || {};
    }, [props.tenant, props.auth?.tenant]);

    // Derive primary color with fallback
    const primaryColor = useMemo(() => {
        return tenant.primary_color || '#000000';
    }, [tenant.primary_color]);

    // Get cart store actions
    const { initForTenant, tenantSlug: currentSlug } = useCartStore();

    // Auto-initialize cart for tenant (side-effect)
    useEffect(() => {
        if (tenant.slug && tenant.slug !== currentSlug) {
            initForTenant(
                tenant.slug,
                tenant.whatsapp_number || '',
                tenant.name || '',
                tenant.id || null
            );
        }
    }, [
        tenant.slug,
        tenant.whatsapp_number,
        tenant.name,
        tenant.id,
        currentSlug,
        initForTenant,
    ]);

    // Check if tenant is properly initialized
    const isInitialized = useMemo(() => {
        return Boolean(tenant.slug);
    }, [tenant.slug]);

    return {
        tenant,
        primaryColor,
        isInitialized,
    };
}

export default useTenantContext;
