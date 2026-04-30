import { useEffect, useMemo, useState } from 'react';
import { Link, usePage } from '@inertiajs/react';
import {
    Bell,
    Bolt,
    CreditCard,
    Home,
    LayoutPanelLeft,
    LogOut,
    Megaphone,
    Package,
    ReceiptText,
    Settings,
} from 'lucide-react';
import { toast as sonnerToast } from 'sonner';
import { useWebSocketNotifications } from '@/Hooks/useWebSocketNotifications';
import { NotificationBell } from '@/Components/NotificationBell';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/Components/ui/sheet';

const iconMap = {
    billing: CreditCard,
    dashboard: Home,
    landing: LayoutPanelLeft,
    logout: LogOut,
    marketing: Megaphone,
    orders: ReceiptText,
    products: Package,
    reports: Bell,
    settings: Settings,
    upgrade: Bolt,
};

function NavIcon({ name, className = 'h-5 w-5' }) {
    const Icon = iconMap[name] ?? Home;

    return <Icon className={className} aria-hidden="true" />;
}

function AppBrand({ isPro = false }) {
    return (
        <Link
            href="/"
            className="inline-flex items-center gap-2 text-lg font-semibold text-slate-950 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-950 focus-visible:ring-offset-2"
        >
            <span>
                Vito <span className="text-blue-600">Business OS</span>
            </span>
            {isPro ? (
                <span className="rounded-full bg-slate-950 px-2 py-0.5 text-[11px] font-bold uppercase tracking-[0.14em] text-white">
                    PRO
                </span>
            ) : null}
        </Link>
    );
}

function UserAvatar({ auth }) {
    const initial = auth?.user?.name?.[0]?.toUpperCase() ?? 'U';

    return (
        <div className="flex h-9 w-9 items-center justify-center rounded-full bg-gradient-to-br from-blue-500 to-indigo-600 text-sm font-semibold text-white">
            {initial}
        </div>
    );
}

function MobileNavDialog({ open, onClose, sections, isPro }) {
    return (
        <Sheet
            open={open}
            onOpenChange={(nextOpen) => {
                if (!nextOpen) {
                    onClose();
                }
            }}
        >
            <SheetContent side="left" className="w-72 p-4 md:hidden">
                <SheetHeader className="text-left">
                    <AppBrand isPro={isPro} />
                    <SheetTitle className="sr-only">Navegación del negocio</SheetTitle>
                    <SheetDescription className="sr-only">
                        Menú principal de administración del negocio.
                    </SheetDescription>
                </SheetHeader>

                <nav className="mt-8 flex flex-col gap-6" aria-label="Navegación principal">
                    {sections.map((section) => (
                        <div key={section.title}>
                            <h2 className="px-3 text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">
                                {section.title}
                            </h2>
                            <div className="mt-2 flex flex-col gap-1">
                                {section.items.map((item) => (
                                    <NavItem key={item.label} item={item} onNavigate={onClose} />
                                ))}
                            </div>
                        </div>
                    ))}
                </nav>
            </SheetContent>
        </Sheet>
    );
}

function UpgradeBanner({ href }) {
    return (
        <Link
            href={href}
            className="mx-2 mt-2 rounded-2xl bg-gradient-to-r from-blue-600 to-indigo-600 p-4 text-white shadow-sm transition-transform hover:-translate-y-0.5 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-700 focus-visible:ring-offset-2"
        >
            <div className="flex items-center gap-2">
                <NavIcon name="upgrade" className="h-4 w-4" />
                <span className="text-sm font-semibold">Planes y Billing</span>
            </div>
            <p className="mt-1 text-xs text-blue-100">Gestiona upgrade, pagos y estado de suscripcion</p>
        </Link>
    );
}

function toPathname(value = '') {
    try {
        return new URL(value, globalThis?.location?.origin ?? 'http://localhost').pathname;
    } catch {
        return value;
    }
}

function matchesRoute(currentUrl, href, aliases = []) {
    const currentPath = toPathname(currentUrl);
    const candidates = [href, ...aliases]
        .filter(Boolean)
        .map((candidate) => toPathname(candidate));

    return candidates.some((candidate) => {
        if (candidate === '/' || candidate === '') {
            return currentPath === candidate;
        }

        return currentPath === candidate || currentPath.startsWith(`${candidate}/`);
    });
}

function NavItem({ item, onNavigate }) {
    const { url } = usePage();
    const isActive = item.matcher(url);

    return (
        <Link
            href={item.href}
            onClick={onNavigate}
            className={`group flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-950 focus-visible:ring-offset-2 ${
                isActive
                    ? 'bg-slate-950 text-white'
                    : 'text-slate-600 hover:bg-slate-100 hover:text-slate-950'
            }`}
        >
            <NavIcon
                name={item.icon}
                className={`h-5 w-5 ${isActive ? 'text-white' : 'text-slate-400 group-hover:text-slate-600'}`}
            />
            <span className="min-w-0 truncate">{item.label}</span>
        </Link>
    );
}

export default function TenantLayout({ children }) {
    const { auth, flash, tenant } = usePage().props;
    const [sidebarOpen, setSidebarOpen] = useState(false);
    const isPro = Boolean(tenant?.is_paid_plan ?? tenant?.is_pro);

    const dashboardHref = tenant?.slug
        ? route('filament.app.pages.dashboard', { tenant: tenant.slug })
        : '/app';
    const ordersHref = tenant?.slug
        ? route('filament.app.resources.orders.index', { tenant: tenant.slug })
        : '/workspace/orders';
    const productsHref = tenant?.slug
        ? route('filament.app.resources.products.index', { tenant: tenant.slug })
        : '/workspace/products';
    const marketingHref = tenant?.slug
        ? route('filament.app.pages.manage-marketing', { tenant: tenant.slug })
        : '/workspace/marketing';
    const reportsHref = tenant?.slug
        ? route('filament.app.pages.growth-center', { tenant: tenant.slug })
        : '/workspace/reports';
    const manageBillingHref = tenant?.slug
        ? route('filament.app.pages.manage-billing', { tenant: tenant.slug })
        : route('tenant.upgrade');
    const settingsHref = tenant?.slug
        ? route('filament.app.pages.settings', { tenant: tenant.slug })
        : '/workspace/settings';
    const publicProfileHref = tenant?.slug
        ? route('filament.app.tenant.profile', { tenant: tenant.slug })
        : route('tenant.landing-page.index');

    const sections = useMemo(() => {
        const baseSections = [
            {
                title: 'Principal',
                items: [
                    {
                        label: 'Dashboard',
                        href: dashboardHref,
                        icon: 'dashboard',
                        matcher: (currentUrl = '') => matchesRoute(currentUrl, dashboardHref, ['/workspace/dashboard']),
                    },
                    {
                        label: 'Pedidos',
                        href: ordersHref,
                        icon: 'orders',
                        matcher: (currentUrl = '') => matchesRoute(currentUrl, ordersHref, ['/workspace/orders']),
                    },
                    {
                        label: 'Productos',
                        href: productsHref,
                        icon: 'products',
                        matcher: (currentUrl = '') => matchesRoute(currentUrl, productsHref, ['/workspace/products']),
                    },
                ],
            },
            {
                title: 'Marketing',
                items: [
                    {
                        label: 'Promociones',
                        href: marketingHref,
                        icon: 'marketing',
                        matcher: (currentUrl = '') => matchesRoute(currentUrl, marketingHref, ['/workspace/marketing']),
                    },
                    {
                        label: 'Reportes',
                        href: reportsHref,
                        icon: 'reports',
                        matcher: (currentUrl = '') => matchesRoute(currentUrl, reportsHref, ['/workspace/reports']),
                    },
                ],
            },
            {
                title: 'Cuenta',
                items: [
                    {
                        label: 'Planes y Billing',
                        href: manageBillingHref,
                        icon: 'billing',
                        matcher: (currentUrl = '') => matchesRoute(currentUrl, manageBillingHref, ['/workspace/billing', '/workspace/upgrade']),
                    },
                    {
                        label: 'Configuracion',
                        href: settingsHref,
                        icon: 'settings',
                        matcher: (currentUrl = '') => matchesRoute(currentUrl, settingsHref, ['/workspace/settings']),
                    },
                ],
            },
        ];

        if (isPro) {
            baseSections.push({
                title: 'Premium',
                items: [
                    {
                        label: 'Perfil Publico',
                        href: publicProfileHref,
                        icon: 'landing',
                        matcher: (currentUrl = '') => matchesRoute(currentUrl, publicProfileHref, ['/workspace/landing-page']),
                    },
                ],
            });
        }

        return baseSections;
    }, [dashboardHref, isPro, manageBillingHref, marketingHref, ordersHref, productsHref, publicProfileHref, reportsHref, settingsHref]);

    useWebSocketNotifications(auth?.user);

    useEffect(() => {
        const notifications = [
            [flash?.message, flash?.type ?? 'success'],
            [flash?.success, 'success'],
            [flash?.error, 'error'],
        ].filter(([message]) => Boolean(message));

        notifications.forEach(([message, type]) => {
            const notify = sonnerToast[type] ?? sonnerToast;
            notify(message);
        });
    }, [flash?.error, flash?.message, flash?.success, flash?.type]);

    return (
        <div className="flex h-screen overflow-hidden bg-slate-50 antialiased">
            <a
                href="#tenant-main-content"
                className="sr-only rounded-xl bg-white px-4 py-2 text-sm font-medium text-slate-950 shadow focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-[60]"
            >
                Saltar al contenido principal
            </a>

            <aside className="hidden w-72 flex-col border-r border-slate-200 bg-white md:flex">
                <div className="flex items-center justify-between border-b border-slate-200 px-6 py-5">
                    <AppBrand isPro={isPro} />
                </div>

                {!isPro ? <UpgradeBanner href={manageBillingHref} /> : null}

                <nav className="flex-1 overflow-y-auto px-3 py-6" aria-label="Navegacion principal">
                    {sections.map((section) => (
                        <div key={section.title} className="mb-6">
                            <h2 className="px-3 text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">
                                {section.title}
                            </h2>
                            <div className="mt-2 space-y-1">
                                {section.items.map((item) => (
                                    <NavItem key={item.label} item={item} />
                                ))}
                            </div>
                        </div>
                    ))}
                </nav>

                <div className="border-t border-slate-200 p-4">
                    <div className="flex items-center gap-3 rounded-2xl bg-slate-50 p-3">
                        <UserAvatar auth={auth} />
                        <div className="min-w-0 flex-1">
                            <p className="truncate text-sm font-semibold text-slate-900">{auth?.user?.name ?? 'Usuario'}</p>
                            <p className="truncate text-xs text-slate-500">{tenant?.name ?? 'Mi negocio'}</p>
                        </div>
                        <Link
                            href="/logout"
                            method="post"
                            as="button"
                            className="inline-flex h-10 w-10 items-center justify-center rounded-xl text-slate-400 transition-colors hover:bg-red-50 hover:text-red-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:ring-offset-2"
                            aria-label="Cerrar sesion"
                        >
                            <NavIcon name="logout" className="h-5 w-5" />
                        </Link>
                    </div>
                </div>
            </aside>

            <MobileNavDialog
                open={sidebarOpen}
                onClose={() => setSidebarOpen(false)}
                sections={sections}
                isPro={isPro}
            />

            <div className="flex min-w-0 flex-1 flex-col overflow-hidden">
                <header className="flex h-16 items-center justify-between border-b border-slate-200 bg-white px-4 shadow-sm md:px-8">
                    <div className="flex items-center gap-3">
                        <button
                            type="button"
                            onClick={() => setSidebarOpen(true)}
                            className="inline-flex h-10 w-10 items-center justify-center rounded-xl text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-950 focus-visible:ring-offset-2 md:hidden"
                            aria-label="Abrir menú lateral"
                        >
                            <LayoutPanelLeft className="h-5 w-5" aria-hidden="true" />
                        </button>
                        <div className="md:hidden">
                            <AppBrand isPro={isPro} />
                        </div>
                    </div>

                    <div className="flex items-center gap-4">
                        <NotificationBell />

                        <div className="hidden items-center gap-3 border-l border-slate-200 pl-4 md:flex">
                            <div className="text-right">
                                <p className="text-sm font-medium text-slate-900">{auth?.user?.name ?? 'Usuario'}</p>
                                <p className="text-xs text-slate-500">{tenant?.name ?? 'Mi negocio'}</p>
                            </div>
                            <UserAvatar auth={auth} />
                        </div>
                    </div>
                </header>

                <main id="tenant-main-content" className="flex-1 overflow-y-auto p-4 md:p-8">
                    {children}
                </main>
            </div>

        </div>
    );
}
