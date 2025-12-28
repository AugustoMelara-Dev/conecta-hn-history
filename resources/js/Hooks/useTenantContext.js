/**
 * useTenantContext.js - Tenant Context Abstraction Hook
 *
 * ARCHITECTURE:
 * - Abstracts tenant initialization from UI components
 * - Auto-initializes cart store for current tenant
 * - Returns standardized tenant data with derived values
 *
 * OPTIMIZATIONS:
 * - useEffect dependencies minimized to prevent unnecessary re-renders
 * - Only re-initializes when tenant slug changes
 *
 * USAGE:
 * const { tenant, primaryColor, isInitialized } = useTenantContext();
 *
 * @module Hooks/useTenantContext
 */

import { useEffect, useMemo, useRef } from 'react';
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

    // Use ref to store tenant data for initForTenant call
    // This prevents re-triggering useEffect when non-critical props change
    const tenantRef = useRef(tenant);
    tenantRef.current = tenant;

    // ═══════════════════════════════════════════════════════════════════════
    // FIX: Only depend on slug change to trigger initialization
    // Other tenant properties are read from ref when needed
    // ═══════════════════════════════════════════════════════════════════════
    useEffect(() => {
        const t = tenantRef.current;
        if (t.slug && t.slug !== currentSlug) {
            initForTenant(
                t.slug,
                t.whatsapp_number || '',
                t.name || '',
                t.id || null
            );
        }
    }, [tenant.slug, currentSlug, initForTenant]);

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
