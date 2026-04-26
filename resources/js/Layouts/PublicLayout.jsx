import { useEffect, useMemo, useState } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    Compass,
    Home,
    LayoutDashboard,
    Menu,
    MessageSquareMore,
    Search,
    Sparkles,
    User2,
    X,
} from 'lucide-react';
import SupportDiagnosticCard from '@/Components/SupportDiagnosticCard';
import { PUBLIC_BODY_FONT, PUBLIC_DISPLAY_FONT, PUBLIC_LABEL_FONT } from '@/lib/publicTypography';

function Toast({ message, type = 'success', onClose }) {
    useEffect(() => {
        const timer = window.setTimeout(onClose, 4500);

        return () => window.clearTimeout(timer);
    }, [onClose]);

    const tones = {
        success: 'border-slate-300 bg-white text-slate-950',
        error: 'border-slate-950 bg-slate-950 text-white',
        info: 'border-slate-300 bg-white text-slate-950',
    };

    return (
        <div
            className={`fixed bottom-6 right-6 z-50 max-w-sm rounded-3xl border px-5 py-4 shadow-2xl ${tones[type]}`}
            aria-live="polite"
            role="status"
        >
            <div className="flex items-start gap-3">
                <div className="mt-1 h-2.5 w-2.5 rounded-full bg-current opacity-70" />
                <div className="flex-1 text-sm font-semibold">{message}</div>
                <button
                    type="button"
                    onClick={onClose}
                    className="text-sm opacity-60 transition hover:opacity-100 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-slate-200"
                >
                    Cerrar
                </button>
            </div>
        </div>
    );
}

function BrandMark() {
    return (
        <div className="flex h-12 w-12 items-center justify-center rounded-[18px] bg-slate-950 text-sm font-semibold text-white shadow-lg shadow-slate-950/15">
            CH
        </div>
    );
}

function Navbar() {
    const page = usePage();
    const { auth } = page.props;
    const [mobileOpen, setMobileOpen] = useState(false);
    const [search, setSearch] = useState('');
    const [scrolled, setScrolled] = useState(false);
    const isHome = route().current('home');
    const isRegistrationFlow = route().current('tenant.register');

    useEffect(() => {
        const onScroll = () => setScrolled(window.scrollY > 12);

        onScroll();
        window.addEventListener('scroll', onScroll);

        return () => window.removeEventListener('scroll', onScroll);
    }, []);

    const searchBusinesses = (event) => {
        event.preventDefault();
        router.get(route('directory.index'), search.trim() ? { search } : {});
        setMobileOpen(false);
    };

    const discoveryActive =
        route().current('public.explore')
        || route().current('directory.*')
        || route().current('city.show')
        || route().current('category.show')
        || route().current('cities.index');

    const panelHref = auth?.tenant?.slug
        ? route('filament.app.pages.dashboard', { tenant: auth.tenant.slug })
        : null;

    const homeLinks = [
        { href: `${route('home')}#features`, label: 'Features', active: true, external: true },
        { href: `${route('home')}#pricing`, label: 'Pricing', active: false, external: true },
        { href: `${route('home')}#businesses`, label: 'Businesses', active: false, external: true },
        { href: `${route('home')}#faq`, label: 'FAQ', active: false, external: true },
    ];
    const defaultLinks = [
        { href: route('home'), label: 'Inicio', active: route().current('home') },
        { href: route('public.explore'), label: 'Negocios', active: discoveryActive },
        { href: `${route('home')}#pricing`, label: 'Planes', active: false, external: true },
    ];
    const registrationLinks = [
        { href: route('home'), label: 'Beneficios', active: false, external: true },
        { href: `${route('home')}#pricing`, label: 'Precios', active: false, external: true },
        { href: 'mailto:soporte@conectahn.com', label: 'Soporte', active: false, external: true },
    ];
    const links = isHome ? homeLinks : (isRegistrationFlow ? registrationLinks : defaultLinks);
    const headerClasses = isRegistrationFlow
        ? 'border-slate-200 bg-white/92 backdrop-blur-xl'
        : isHome
            ? 'border-slate-200/60 bg-white/80 shadow-sm backdrop-blur-[20px]'
        : scrolled
            ? 'border-slate-200 bg-white/94 backdrop-blur-xl'
            : 'border-transparent bg-white/86 backdrop-blur-md';

    const navLinkClass = (active) => (
        `rounded-full px-1 text-sm transition focus-visible:outline-none focus-visible:ring-4 ${
            'focus-visible:ring-slate-200'
        } ${
            active ? 'border-b-2 border-slate-900 pb-1 text-slate-950' : 'text-slate-500 hover:text-slate-950'
        }`
    );

    return (
        <header className={`sticky top-0 z-40 border-b transition-all ${headerClasses}`}>
            <nav className="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8">
                <Link href={route('home')} className="flex items-center gap-3 rounded-2xl focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-slate-200">
                    {isHome ? null : <BrandMark />}
                    <div>
                        <p className={`${isHome ? 'text-2xl' : 'text-base'} font-extrabold tracking-tight text-slate-950`} style={{ fontFamily: PUBLIC_DISPLAY_FONT }}>
                            Vito Business OS
                        </p>
                        {!isHome ? (
                            <p className="text-[11px] font-semibold uppercase tracking-[0.34em] text-slate-500" style={{ fontFamily: PUBLIC_LABEL_FONT }}>
                                Conecta HN
                            </p>
                        ) : null}
                    </div>
                </Link>

                <div className="hidden items-center gap-8 lg:flex" style={{ fontFamily: PUBLIC_LABEL_FONT }}>
                    {links.map((item) => (
                        item.external ? (
                            <a key={item.label} href={item.href} className={navLinkClass(item.active)}>
                                {item.label}
                            </a>
                        ) : (
                            <Link key={item.label} href={item.href} className={navLinkClass(item.active)}>
                                {item.label}
                            </Link>
                        )
                    ))}
                </div>

                <form onSubmit={searchBusinesses} className={isRegistrationFlow || isHome ? 'hidden' : 'hidden w-full max-w-md lg:block'}>
                    <div className="flex items-center rounded-full border border-stone-200 bg-white px-4 py-2.5 shadow-sm">
                        <label htmlFor="public-nav-search" className="sr-only">
                            Buscar negocios, rubros o ciudades
                        </label>
                        <Search className="mr-3 h-4 w-4 text-slate-400" />
                        <input
                            id="public-nav-search"
                            name="search"
                            type="text"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Busca negocios, rubros o ciudades..."
                            className="w-full bg-transparent text-sm text-slate-900 outline-none placeholder:text-slate-400"
                        />
                        <button
                            type="submit"
                            className="rounded-full px-2 text-sm font-semibold text-slate-700 transition hover:text-slate-950 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-slate-200"
                        >
                            Buscar
                        </button>
                    </div>
                </form>

                <div className="hidden items-center gap-3 lg:flex">
                    {auth?.user ? (
                        <>
                            <Link href={route('mi-cuenta.edit')} className="rounded-full border border-stone-200 bg-white px-5 py-3 text-sm font-semibold text-slate-700 transition hover:border-stone-300 hover:text-slate-950 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-slate-200">
                                Mi cuenta
                            </Link>
                            {panelHref ? (
                                <a href={panelHref} className="inline-flex items-center gap-2 rounded-full bg-slate-950 px-5 py-3 text-sm font-semibold text-white transition hover:bg-slate-800 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-slate-200">
                                    Abrir panel
                                    <ArrowRight className="h-4 w-4" />
                                </a>
                            ) : (
                                <Link href={route('tenant.register')} className="inline-flex items-center gap-2 rounded-full bg-slate-950 px-5 py-3 text-sm font-semibold text-white transition hover:bg-slate-800 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-slate-200">
                                    Registrar negocio
                                    <ArrowRight className="h-4 w-4" />
                                </Link>
                            )}
                        </>
                    ) : isRegistrationFlow ? (
                        <Link href={route('login')} className="inline-flex items-center gap-2 rounded-lg bg-slate-950 px-5 py-3 text-sm font-semibold uppercase tracking-[0.16em] text-white transition hover:bg-slate-800 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-slate-200" style={{ fontFamily: PUBLIC_LABEL_FONT }}>
                            Iniciar sesion
                        </Link>
                    ) : (
                        <>
                            <Link href={route('login')} className="px-5 py-2 text-sm font-semibold text-slate-900 transition hover:text-slate-600 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-slate-200" style={{ fontFamily: PUBLIC_LABEL_FONT }}>
                                {isHome ? 'Log In' : 'Iniciar sesion'}
                            </Link>
                            {!isRegistrationFlow ? (
                                <Link href={route('tenant.register')} className="inline-flex items-center gap-2 rounded-lg bg-gradient-to-br from-slate-950 via-slate-900 to-slate-700 px-6 py-2.5 text-sm font-semibold text-white transition hover:scale-[0.98] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-slate-200" style={{ fontFamily: PUBLIC_LABEL_FONT }}>
                                    {isHome ? 'Get Started' : 'Registrar negocio'}
                                    {!isHome ? <ArrowRight className="h-4 w-4" /> : null}
                                </Link>
                            ) : null}
                        </>
                    )}
                </div>

                <button
                    type="button"
                    onClick={() => setMobileOpen((value) => !value)}
                    className="inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-stone-200 bg-white text-slate-700 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-slate-200 lg:hidden"
                    aria-label="Abrir menu"
                >
                    {mobileOpen ? <X className="h-5 w-5" /> : <Menu className="h-5 w-5" />}
                </button>
            </nav>

            {mobileOpen ? (
                <div className="border-t border-stone-200 bg-white lg:hidden">
                    <div className="mx-auto max-w-7xl space-y-4 px-4 py-4 sm:px-6">
                        <form onSubmit={searchBusinesses} className={isHome || isRegistrationFlow ? 'hidden' : ''}>
                            <div className="flex items-center rounded-2xl border border-stone-200 bg-white px-4 py-3">
                                <label htmlFor="public-mobile-search" className="sr-only">
                                    Buscar negocios o servicios
                                </label>
                                <Search className="mr-3 h-4 w-4 text-slate-400" />
                                <input
                                    id="public-mobile-search"
                                    name="search"
                                    type="text"
                                    value={search}
                                    onChange={(event) => setSearch(event.target.value)}
                                    placeholder="Buscar negocios o servicios..."
                                    className="w-full bg-transparent text-sm text-slate-900 outline-none placeholder:text-slate-400"
                                />
                            </div>
                        </form>

                        <div className="flex flex-col gap-2" style={{ fontFamily: PUBLIC_LABEL_FONT }}>
                            {links.map((item) => (
                                item.external ? (
                                    <a
                                        key={item.label}
                                        href={item.href}
                                        className={`rounded-2xl px-4 py-3 text-sm font-medium transition focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-slate-200 ${
                                            item.active ? 'bg-white text-slate-950' : 'text-slate-700 hover:bg-white hover:text-slate-950'
                                        }`}
                                        onClick={() => setMobileOpen(false)}
                                    >
                                        {item.label}
                                    </a>
                                ) : (
                                    <Link
                                        key={item.label}
                                        href={item.href}
                                        className={`rounded-2xl px-4 py-3 text-sm font-medium transition focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-slate-200 ${
                                            item.active ? 'bg-white text-slate-950' : 'text-slate-700 hover:bg-white hover:text-slate-950'
                                        }`}
                                        onClick={() => setMobileOpen(false)}
                                    >
                                        {item.label}
                                    </Link>
                                )
                            ))}
                        </div>

                        <div className="flex flex-col gap-3 pt-2">
                            {auth?.user ? (
                                <>
                                    <Link
                                        href={route('mi-cuenta.edit')}
                                        className="inline-flex items-center justify-center rounded-2xl border border-stone-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-slate-200"
                                        onClick={() => setMobileOpen(false)}
                                    >
                                        Mi cuenta
                                    </Link>
                                    {panelHref ? (
                                        <a
                                            href={panelHref}
                                            className="inline-flex items-center justify-center rounded-2xl bg-slate-950 px-4 py-3 text-sm font-semibold text-white focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-slate-200"
                                        >
                                            Abrir panel
                                        </a>
                                    ) : (
                                        <Link
                                            href={route('tenant.register')}
                                            className="inline-flex items-center justify-center rounded-2xl bg-slate-950 px-4 py-3 text-sm font-semibold text-white focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-slate-200"
                                            onClick={() => setMobileOpen(false)}
                                        >
                                            Registrar negocio
                                        </Link>
                                    )}
                                </>
                            ) : (
                                <>
                                    <Link
                                        href={route('login')}
                                        className="inline-flex items-center justify-center rounded-2xl border border-stone-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-slate-200"
                                        onClick={() => setMobileOpen(false)}
                                    >
                                        {isHome ? 'Log In' : 'Iniciar sesion'}
                                    </Link>
                                    {!isRegistrationFlow ? (
                                        <Link
                                            href={route('tenant.register')}
                                            className="inline-flex items-center justify-center rounded-2xl bg-slate-950 px-4 py-3 text-sm font-semibold text-white focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-slate-200"
                                            onClick={() => setMobileOpen(false)}
                                        >
                                            {isHome ? 'Get Started' : 'Registrar negocio'}
                                        </Link>
                                    ) : null}
                                </>
                            )}
                        </div>
                    </div>
                </div>
            ) : null}
        </header>
    );
}

function Footer() {
    const { auth } = usePage().props;

    return (
        <footer className="border-t border-slate-200 bg-slate-100">
            <div className="mx-auto grid max-w-7xl grid-cols-2 gap-8 px-8 py-12 md:grid-cols-4">
                <div className="col-span-2 md:col-span-1">
                    <div className="text-xl font-bold text-slate-900" style={{ fontFamily: PUBLIC_DISPLAY_FONT }}>Vito Business OS</div>
                    <p className="mt-4 text-sm leading-relaxed text-slate-500">
                        La infraestructura digital para el comercio local en Honduras y la region.
                    </p>
                </div>

                <div>
                    <span className="mb-6 block text-xs font-bold uppercase tracking-[0.18em] text-slate-900" style={{ fontFamily: PUBLIC_LABEL_FONT }}>Producto</span>
                    <ul className="space-y-3 text-sm text-slate-500">
                        <li><a href={`${route('home')}#features`} className="transition hover:underline">Features</a></li>
                        <li><a href={`${route('home')}#pricing`} className="transition hover:underline">Pricing</a></li>
                        <li><Link href={route('public.explore')} className="transition hover:underline">Businesses</Link></li>
                        <li><Link href={route('tenant.register')} className="transition hover:underline">Get Started</Link></li>
                    </ul>
                </div>

                <div>
                    <span className="mb-6 block text-xs font-bold uppercase tracking-[0.18em] text-slate-900" style={{ fontFamily: PUBLIC_LABEL_FONT }}>Compania</span>
                    <ul className="space-y-3 text-sm text-slate-500">
                        <li><a href={`${route('home')}#businesses`} className="transition hover:underline">About</a></li>
                        <li><a href={`${route('home')}#faq`} className="transition hover:underline">FAQ</a></li>
                        <li><a href="mailto:soporte@conectahn.com" className="transition hover:underline">Support</a></li>
                        {auth?.user ? (
                            <li><Link href={route('mi-cuenta.edit')} className="transition hover:underline">Mi cuenta</Link></li>
                        ) : (
                            <li><Link href={route('login')} className="transition hover:underline">Log In</Link></li>
                        )}
                    </ul>
                </div>

                <div>
                    <span className="mb-6 block text-xs font-bold uppercase tracking-[0.18em] text-slate-900" style={{ fontFamily: PUBLIC_LABEL_FONT }}>Social</span>
                    <div className="flex gap-4">
                        <a href="https://conectahn.com" className="flex h-8 w-8 items-center justify-center rounded-full bg-slate-200 text-slate-700 transition hover:bg-slate-300">
                            <Compass className="h-4 w-4" />
                        </a>
                        <a href="https://wa.me/50499999999" className="flex h-8 w-8 items-center justify-center rounded-full bg-slate-200 text-slate-700 transition hover:bg-slate-300">
                            <MessageSquareMore className="h-4 w-4" />
                        </a>
                    </div>
                </div>
            </div>

            <div className="border-t border-slate-200">
                <div className="mx-auto max-w-7xl px-8 py-8">
                    <p className="text-center text-xs text-slate-500">
                        &copy; {new Date().getFullYear()} Vito Business OS / Conecta HN. All rights reserved.
                    </p>
                </div>
            </div>
        </footer>
    );
}

function DiscoveryDock() {
    const { auth } = usePage().props;
    const panelHref = auth?.tenant?.slug
        ? route('filament.app.pages.dashboard', { tenant: auth.tenant.slug })
        : null;

    const items = [
        {
            label: 'Inicio',
            href: route('home'),
            icon: Home,
            active: route().current('home'),
        },
        {
            label: 'Negocios',
            href: route('public.explore'),
            icon: Sparkles,
            active: route().current('public.explore'),
        },
        {
            label: 'Buscar',
            href: route('directory.index'),
            icon: Search,
            active:
                route().current('directory.*')
                || route().current('city.show')
                || route().current('category.show')
                || route().current('cities.index'),
        },
        auth?.user
            ? panelHref
                ? {
                    label: 'Panel',
                    href: panelHref,
                    icon: LayoutDashboard,
                    active: false,
                    native: true,
                }
                : {
                    label: 'Cuenta',
                    href: route('mi-cuenta.edit'),
                    icon: User2,
                    active: route().current('mi-cuenta.edit'),
                }
            : {
                label: 'Ingresar',
                href: route('login'),
                icon: Compass,
                active: route().current('login'),
            },
    ];

    return (
        <nav className="fixed inset-x-0 bottom-4 z-40 px-4 md:hidden">
            <div className="mx-auto grid max-w-md grid-cols-4 gap-2 rounded-[28px] border border-stone-200 bg-white/95 px-3 py-3 shadow-2xl shadow-slate-950/10 backdrop-blur-xl">
                {items.map((item) => {
                    const content = (
                        <span className={`flex flex-col items-center justify-center gap-1 rounded-2xl px-2 py-2 text-[11px] font-semibold transition ${item.active ? 'bg-slate-100 text-slate-950' : 'text-slate-500 hover:text-slate-950'}`}>
                            <item.icon className="h-4 w-4" />
                            {item.label}
                        </span>
                    );

                    if (item.native) {
                        return (
                            <a key={item.label} href={item.href}>
                                {content}
                            </a>
                        );
                    }

                    return (
                        <Link key={item.label} href={item.href}>
                            {content}
                        </Link>
                    );
                })}
            </div>
        </nav>
    );
}

export default function PublicLayout({ children, hideFooter = false }) {
    const page = usePage();
    const { flash } = page.props;
    const [toast, setToast] = useState(null);
    const isRegistrationFlow = route().current('tenant.register');

    const showDiscoveryDock = useMemo(() => (
        route().current('public.explore')
        || route().current('directory.*')
        || route().current('city.show')
        || route().current('category.show')
        || route().current('cities.index')
    ), [page.url]);

    useEffect(() => {
        if (flash?.success) {
            setToast({ message: flash.success, type: 'success' });
        } else if (flash?.error) {
            setToast({ message: flash.error, type: 'error' });
        } else if (flash?.info) {
            setToast({ message: flash.info, type: 'info' });
        }
    }, [flash]);

    return (
        <div className={`min-h-screen bg-white text-slate-900 ${showDiscoveryDock ? 'pb-24 md:pb-0' : ''}`} style={{ fontFamily: PUBLIC_BODY_FONT }}>
            <a
                href="#main-content"
                className="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-full focus:bg-white focus:px-4 focus:py-2 focus:text-sm focus:font-semibold focus:text-slate-950 focus:shadow-lg"
            >
                Saltar al contenido
            </a>

            <Navbar />
            <main id="main-content">{children}</main>
            {!hideFooter ? <Footer /> : null}
            {showDiscoveryDock ? <DiscoveryDock /> : null}

            {toast ? (
                <Toast message={toast.message} type={toast.type} onClose={() => setToast(null)} />
            ) : null}
            {flash?.diagnostic ? (
                <div className="fixed bottom-24 left-4 right-4 z-40 mx-auto max-w-3xl md:bottom-6">
                    <SupportDiagnosticCard diagnostic={flash.diagnostic} />
                </div>
            ) : null}
        </div>
    );
}
