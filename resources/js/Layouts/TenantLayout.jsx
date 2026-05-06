/**
 * TenantLayout.jsx - Persistent Sidebar/Header Layout (Hardened)
 *
 * PURPOSE: Wraps all tenant-scoped pages. Provides consistent navigation.
 *
 * NAVIGATION MODULES (UPDATED):
 * - Dashboard, Orders, Products (Core)
 * - Marketing, Reports (Analytics)
 * - Billing, Settings (Account)
 * - Upgrade, Landing Page (PRO Features)
 *
 * ACTIVE STATE LOGIC:
 * - Uses strict route matching via route().current() pattern
 * - Highlights parent when in child routes (e.g., /products/* all highlight Products)
 *
 * PROPS (from Controller):
 * - auth: { user: { id, name, email } }
 * - flash: { message: string | null, type: 'success' | 'error' | null }
 * - tenant: { id, name, slug, is_pro }
 */

import { useState, useEffect } from 'react';
import { Link, usePage, router } from '@inertiajs/react';

// ═══════════════════════════════════════════════════════════════════════════
// REAL-TIME NOTIFICATIONS (WebSocket)
// ═══════════════════════════════════════════════════════════════════════════
import { useWebSocketNotifications } from '@/Hooks/useWebSocketNotifications';
import { ToastContainer } from '@/Components/Toast/ToastContainer';
import { NotificationBell } from '@/Components/NotificationBell';

// ═══════════════════════════════════════════════════════════════════════════
// NAVIGATION CONFIGURATION (HARDENED)
// All modules with strict route patterns
// ═══════════════════════════════════════════════════════════════════════════
const NAV_SECTIONS = [
    {
        title: 'Principal',
        items: [
            { label: 'Dashboard', href: '/app/dashboard', icon: 'home', pattern: 'tenant.dashboard' },
            { label: 'Pedidos', href: '/app/orders', icon: 'orders', pattern: 'tenant.orders.*' },
            { label: 'Productos', href: '/app/products', icon: 'products', pattern: 'tenant.products.*' },
        ],
    },
    {
        title: 'Marketing',
        items: [
            { label: 'Promociones', href: '/app/marketing', icon: 'marketing', pattern: 'tenant.marketing.*' },
            { label: 'Reportes', href: '/app/reports', icon: 'reports', pattern: 'tenant.reports.*' },
        ],
    },
    {
        title: 'Cuenta',
        items: [
            { label: 'Facturación', href: '/app/billing', icon: 'billing', pattern: 'tenant.billing.*' },
            { label: 'Configuración', href: '/app/settings', icon: 'settings', pattern: 'tenant.settings.*' },
        ],
    },
];

// PRO-only features (shown conditionally)
const PRO_NAV_ITEMS = [
    { label: 'Landing Page', href: '/app/landing-page', icon: 'landing', pattern: 'tenant.landing-page.*' },
];

// ═══════════════════════════════════════════════════════════════════════════
// ICON COMPONENT (Extended SVG Library)
// ═══════════════════════════════════════════════════════════════════════════
function NavIcon({ name, className = 'w-5 h-5' }) {
    const icons = {
        home: (
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
                d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
        ),
        orders: (
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
                d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
        ),
        products: (
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
                d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
        ),
        reports: (
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
                d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
        ),
        settings: (
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
                d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
        ),
        billing: (
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
                d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
        ),
        marketing: (
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
                d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
        ),
        landing: (
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
                d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zm0 8a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zm12 0a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z" />
        ),
        upgrade: (
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
                d="M13 10V3L4 14h7v7l9-11h-7z" />
        ),
        logout: (
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
                d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
        ),
    };

    return (
        <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
            {icons[name] || icons.home}
        </svg>
    );
}

// ═══════════════════════════════════════════════════════════════════════════
// TOAST NOTIFICATION (Flash Messages)
// ═══════════════════════════════════════════════════════════════════════════
function Toast({ message, type = 'success' }) {
    const [visible, setVisible] = useState(true);

    useEffect(() => {
        const timer = setTimeout(() => setVisible(false), 4000);
        return () => clearTimeout(timer);
    }, []);

    if (!visible || !message) return null;

    const colors = {
        success: 'bg-emerald-600',
        error: 'bg-red-600',
        warning: 'bg-amber-600',
        info: 'bg-blue-600',
    };

    return (
        <div
            className={`fixed bottom-4 right-4 z-50 px-6 py-3 ${colors[type] || colors.success} text-white rounded-xl shadow-2xl 
                transform transition-all duration-300 ${visible ? 'translate-y-0 opacity-100' : 'translate-y-4 opacity-0'}`}
        >
            {message}
        </div>
    );
}

// ═══════════════════════════════════════════════════════════════════════════
// UPGRADE BANNER (For Non-PRO Users)
// ═══════════════════════════════════════════════════════════════════════════
function UpgradeBanner({ isPro }) {
    if (isPro) return null;

    return (
        <Link
            href="/app/upgrade"
            className="mx-2 mb-2 p-3 bg-gradient-to-r from-blue-600 to-indigo-600 rounded-xl text-white text-center hover:from-blue-700 hover:to-indigo-700 transition-all"
        >
            <div className="flex items-center justify-center gap-2">
                <NavIcon name="upgrade" className="w-4 h-4" />
                <span className="text-sm font-semibold">Upgrade a PRO</span>
            </div>
            <p className="text-xs text-blue-100 mt-1">Productos ilimitados</p>
        </Link>
    );
}

// ═══════════════════════════════════════════════════════════════════════════
// NAVIGATION ITEM (with strict route matching)
// ═══════════════════════════════════════════════════════════════════════════
function NavItem({ item, url }) {
    // Strict route matching: check if current URL starts with href
    const isActive = url?.startsWith(item.href) ||
        (item.href === '/app/dashboard' && url === '/app/dashboard');

    return (
        <Link
            href={item.href}
            className={`group flex items-center gap-3 px-3 py-2.5 text-sm font-medium rounded-lg transition-all
                ${isActive
                    ? 'bg-blue-50 text-blue-700 shadow-sm'
                    : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900'
                }`}
        >
            <NavIcon
                name={item.icon}
                className={`w-5 h-5 transition-colors ${isActive ? 'text-blue-600' : 'text-gray-400 group-hover:text-gray-600'}`}
            />
            <span>{item.label}</span>
            {item.badge && (
                <span className="ml-auto px-2 py-0.5 text-xs font-bold bg-amber-100 text-amber-700 rounded-full">
                    {item.badge}
                </span>
            )}
        </Link>
    );
}

// ═══════════════════════════════════════════════════════════════════════════
// MAIN LAYOUT COMPONENT
// ═══════════════════════════════════════════════════════════════════════════
export default function TenantLayout({ children }) {
    const { auth, flash, url, tenant } = usePage().props;
    const [sidebarOpen, setSidebarOpen] = useState(false);
    const isPro = tenant?.is_pro || false;

    // ═══════════════════════════════════════════════════════════════════════
    // WEBSOCKET NOTIFICATIONS (Real-time export alerts)
    // ═══════════════════════════════════════════════════════════════════════
    const { toasts, removeToast } = useWebSocketNotifications(auth?.user);

    return (
        <div className="antialiased bg-gray-50 flex h-screen overflow-hidden">
            {/* ═══════════════════════════════════════════════════════════════ */}
            {/* DESKTOP SIDEBAR */}
            {/* ═══════════════════════════════════════════════════════════════ */}
            <aside className="hidden md:flex md:w-64 md:flex-col bg-white border-r border-gray-200">
                {/* Logo */}
                <div className="flex items-center justify-center h-16 border-b border-gray-200">
                    <Link href="/">
                        <span className="text-xl font-bold text-gray-900">
                            Conecta<span className="text-blue-600">HN</span>
                        </span>
                    </Link>
                    {isPro && (
                        <span className="ml-2 px-2 py-0.5 text-xs font-bold bg-gradient-to-r from-blue-600 to-indigo-600 text-white rounded-full">
                            PRO
                        </span>
                    )}
                </div>

                {/* Upgrade Banner (non-PRO only) */}
                <UpgradeBanner isPro={isPro} />

                {/* Navigation Sections */}
                <nav className="flex-1 overflow-y-auto py-4 px-2 space-y-6">
                    {NAV_SECTIONS.map((section) => (
                        <div key={section.title}>
                            <h3 className="px-3 mb-2 text-xs font-semibold text-gray-400 uppercase tracking-wider">
                                {section.title}
                            </h3>
                            <div className="space-y-1">
                                {section.items.map((item) => (
                                    <NavItem key={item.href} item={item} url={url} />
                                ))}
                            </div>
                        </div>
                    ))}

                    {/* PRO Features Section */}
                    {isPro && (
                        <div>
                            <h3 className="px-3 mb-2 text-xs font-semibold text-blue-500 uppercase tracking-wider">
                                ✨ Premium
                            </h3>
                            <div className="space-y-1">
                                {PRO_NAV_ITEMS.map((item) => (
                                    <NavItem key={item.href} item={item} url={url} />
                                ))}
                            </div>
                        </div>
                    )}
                </nav>

                {/* User Info Footer */}
                <div className="p-4 border-t border-gray-200">
                    <div className="flex items-center gap-3">
                        <div className="w-10 h-10 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-full flex items-center justify-center shadow-md">
                            <span className="text-sm font-bold text-white">
                                {auth?.user?.name?.[0]?.toUpperCase() || 'U'}
                            </span>
                        </div>
                        <div className="flex-1 min-w-0">
                            <p className="text-sm font-semibold text-gray-800 truncate">
                                {auth?.user?.name || 'Usuario'}
                            </p>
                            <p className="text-xs text-gray-500 truncate">
                                {tenant?.name || 'Mi Negocio'}
                            </p>
                        </div>
                        <Link
                            href="/logout"
                            method="post"
                            as="button"
                            className="p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                            title="Cerrar sesión"
                        >
                            <NavIcon name="logout" className="w-5 h-5" />
                        </Link>
                    </div>
                </div>
            </aside>

            {/* ═══════════════════════════════════════════════════════════════ */}
            {/* MOBILE SIDEBAR (Overlay) */}
            {/* ═══════════════════════════════════════════════════════════════ */}
            {sidebarOpen && (
                <div
                    className="md:hidden fixed inset-0 z-40 bg-gray-600/75"
                    onClick={() => setSidebarOpen(false)}
                >
                    <aside
                        className="w-72 h-full bg-white shadow-xl overflow-y-auto"
                        onClick={(e) => e.stopPropagation()}
                    >
                        <div className="flex items-center justify-between h-16 border-b border-gray-200 px-4">
                            <span className="text-xl font-bold text-gray-900">
                                Conecta<span className="text-blue-600">HN</span>
                            </span>
                            <button onClick={() => setSidebarOpen(false)} className="p-2 text-gray-500 hover:text-gray-700">
                                <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        {/* Upgrade Banner (mobile) */}
                        <div className="p-2">
                            <UpgradeBanner isPro={isPro} />
                        </div>

                        {/* Mobile Navigation */}
                        <nav className="py-4 px-2 space-y-6">
                            {NAV_SECTIONS.map((section) => (
                                <div key={section.title}>
                                    <h3 className="px-3 mb-2 text-xs font-semibold text-gray-400 uppercase">
                                        {section.title}
                                    </h3>
                                    <div className="space-y-1">
                                        {section.items.map((item) => (
                                            <NavItem key={item.href} item={item} url={url} />
                                        ))}
                                    </div>
                                </div>
                            ))}
                            {isPro && (
                                <div>
                                    <h3 className="px-3 mb-2 text-xs font-semibold text-blue-500 uppercase">
                                        ✨ Premium
                                    </h3>
                                    <div className="space-y-1">
                                        {PRO_NAV_ITEMS.map((item) => (
                                            <NavItem key={item.href} item={item} url={url} />
                                        ))}
                                    </div>
                                </div>
                            )}
                        </nav>
                    </aside>
                </div>
            )}

            {/* ═══════════════════════════════════════════════════════════════ */}
            {/* MAIN CONTENT AREA */}
            {/* ═══════════════════════════════════════════════════════════════ */}
            <div className="flex-1 flex flex-col overflow-hidden">
                {/* ═══════════════════════════════════════════════════════════════ */}
                {/* DESKTOP HEADER (Hardened UX) */}
                {/* ═══════════════════════════════════════════════════════════════ */}
                <header className="hidden md:flex bg-white h-16 border-b border-gray-200 items-center justify-between px-8 shadow-sm z-30">
                    <div className="flex-1">
                        {/* Placeholder for Breadcrumbs or Page Title */}
                    </div>
                    <div className="flex items-center gap-4">
                        {/* Notification Center (Real-time & Persistent) */}
                        <NotificationBell />

                        {/* User Profile Context */}
                        <div className="flex items-center gap-3 pl-4 border-l border-gray-200">
                            <div className="text-right hidden lg:block">
                                <div className="text-sm font-medium text-gray-900">{auth?.user?.name}</div>
                                <div className="text-xs text-gray-500">{tenant?.name}</div>
                            </div>
                            <img
                                src={auth?.user?.profile_photo_url || `https://ui-avatars.com/api/?name=${auth?.user?.name}&background=random`}
                                alt={auth?.user?.name}
                                className="h-8 w-8 rounded-full object-cover border border-gray-200"
                            />
                        </div>
                    </div>
                </header>

                {/* Mobile Top Bar */}
                <header className="bg-white shadow-sm md:hidden p-4 flex justify-between items-center border-b">
                    <div className="flex items-center gap-2">
                        <span className="text-xl font-bold text-gray-900">
                            Conecta<span className="text-blue-600">HN</span>
                        </span>
                        {isPro && (
                            <span className="px-1.5 py-0.5 text-xs font-bold bg-blue-600 text-white rounded">PRO</span>
                        )}
                    </div>
                    <button
                        onClick={() => setSidebarOpen(true)}
                        className="p-2 text-gray-500 hover:text-gray-700"
                    >
                        <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                </header>

                {/* Page Content */}
                <main className="flex-1 overflow-y-auto p-4 md:p-8">
                    {children}
                </main>
            </div>

            {/* Flash Toast Notifications (HTTP response messages) */}
            {flash?.message && <Toast message={flash.message} type={flash.type} />}
            {flash?.success && <Toast message={flash.success} type="success" />}
            {flash?.error && <Toast message={flash.error} type="error" />}

            {/* WebSocket Toast Notifications (Real-time events) */}
            <ToastContainer toasts={toasts} onDismiss={removeToast} />
        </div>
    );
}
