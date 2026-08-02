import { Head, Link } from '@inertiajs/react';
import { AnimatePresence, MotionConfig, motion } from 'framer-motion';
import {
    ArrowRight,
    BarChart3,
    CalendarCheck,
    Check,
    ChevronDown,
    LayoutDashboard,
    Menu,
    MessageCircleMore,
    Moon,
    Search,
    ShoppingBag,
    Sun,
    UsersRound,
    X,
    Zap,
} from 'lucide-react';
import { useState } from 'react';

import { SkipLink } from '@/Components/ui/skip-link';
import { PUBLIC_BRAND } from '@/lib/publicBrand';

const reveal = {
    initial: { opacity: 0, y: 20 },
    whileInView: { opacity: 1, y: 0 },
    viewport: { once: true, amount: 0.15 },
    transition: { duration: 0.6, ease: 'easeOut' },
};

const productPillars = [
    { icon: ShoppingBag, label: 'Catálogo' },
    { icon: CalendarCheck, label: 'Reservas' },
    { icon: UsersRound, label: 'Clientes' },
    { icon: BarChart3, label: 'Reportes' },
    { icon: MessageCircleMore, label: 'WhatsApp' },
    { icon: LayoutDashboard, label: 'Panel privado' },
];

const productEngines = [
    {
        name: 'Dovra Business',
        role: 'Datos y operación',
        icon: LayoutDashboard,
        content: 'Catálogo, servicios, clientes, agenda, pedidos, oportunidades y políticas en una base operativa gobernada.',
        outcome: 'Una fuente de verdad para personas y agentes.',
    },
    {
        name: 'Dovra Agents',
        role: 'Empleados de IA',
        icon: MessageCircleMore,
        content: 'AI employees by job use authorized context, prepare governed actions and hand the conversation to your team when needed.',
        outcome: 'Conversaciones convertidas en trabajo medible.',
    },
    {
        name: 'Dovra Automate',
        role: 'Herramientas y flujos',
        icon: Zap,
        content: 'Confirmaciones, workflows, API, webhooks e integraciones que ejecutan reglas del negocio fuera del modelo de lenguaje.',
        outcome: 'Automatización observable, idempotente y reversible.',
    },
    {
        name: 'Dovra Discover',
        role: 'Demanda y descubrimiento',
        icon: Search,
        content: 'Búsqueda tradicional y conversacional sobre oferta publicada, comparable y vigente, con acciones preparadas para el comprador.',
        outcome: 'Necesidades reales conectadas con oferta verificable.',
    },
];

const stats = [
    { value: '4', label: 'Motores conectados' },
    { value: '2', label: 'Experiencias: negocio y comprador' },
    { value: '0%', label: 'Comisión por transacción' },
    { value: '1', label: 'Capa determinista de políticas' },
];

function buildPlans(proPlanPrice) {
    const numericProPrice = Number(proPlanPrice);
    const formattedProPrice = Number.isFinite(numericProPrice) && numericProPrice > 0
        ? `L ${numericProPrice.toLocaleString("es-HN", { maximumFractionDigits: 0 })}`
        : "No disponible";

    return [
        {
            name: 'Explore',
            description: 'Para estructurar tu negocio y probar un agente antes de operar en vivo.',
            price: 'Gratis',
            suffix: '',
            features: ['Perfil y catálogo', 'Importación y revisión', 'Agente en sandbox', 'Sin IA en vivo ilimitada'],
        },
        {
            name: 'Operación — Honduras',
            description: 'Precio vigente del primer Market Pack mientras se validan los price books globales.',
            price: formattedProPrice,
            suffix: '/mes',
            featured: true,
            features: ['Operación comercial', 'Reservas, CRM y reportes', 'Capacidad según el plan', 'IA y canales según disponibilidad'],
        },
        {
            name: 'Launch · Grow · Scale',
            description: 'Paquetes globales en validación por mercado, proveedor, volumen y soporte.',
            price: 'En validación',
            suffix: '',
            features: ['Equipos y ubicaciones', 'Canales e integraciones', 'Voz y automatización avanzada', 'Enterprise bajo contrato'],
        },
    ];
}

const faqs = [
    {
        question: '¿Puedo empezar gratis?',
        answer: 'Explore permite crear el perfil, estructurar el catálogo y simular un agente en sandbox. Los canales y agentes en vivo requieren un plan y un proveedor disponible en tu mercado.',
    },
    {
        question: '¿Dovra AI sirve para mi tipo de negocio?',
        answer: 'Puedes configurar el flujo para vender productos, trabajar con citas o captar clientes para servicios. El registro te guía según la forma en que genera ingresos tu negocio.',
    },
    {
        question: '¿Dónde administro mi negocio después del registro?',
        answer: 'Al terminar tendrás acceso a un panel privado. Desde ahí administras tu oferta, clientes y operación sin mezclarla con el directorio público.',
    },
    {
        question: '¿Qué es Dovra Discover?',
        answer: 'Es la experiencia para compradores: combina búsqueda y conversación sobre oferta publicada. Complementa la navegación tradicional y prepara contacto, reserva o pedido antes de pedir confirmación.',
    },
    {
        question: '¿La IA puede ejecutar cualquier acción?',
        answer: 'No. La IA propone; las políticas de Dovra autorizan. Las acciones sensibles requieren identidad, permisos, límites, estado vigente y una confirmación vinculada al usuario.',
    },
];

function sameOriginHref(href) {
    if (!href || typeof window === 'undefined') return href;

    try {
        const url = new URL(href, window.location.origin);
        return url.origin === window.location.origin ? `${url.pathname}${url.search}${url.hash}` : href;
    } catch {
        return href;
    }
}

function appRoute(name, params, fallback = '/') {
    try {
        return sameOriginHref(route(name, params));
    } catch {
        return fallback;
    }
}

function Brand() {
    return (
        <Link href={appRoute('home', undefined, '/')} className="font-light tracking-tighter transition-opacity hover:opacity-75">
            <span className="text-xl sm:text-2xl">{PUBLIC_BRAND.name}</span>
        </Link>
    );
}

function Navbar({ auth, canRegister, dark, onThemeChange }) {
    const [open, setOpen] = useState(false);
    const [productOpen, setProductOpen] = useState(false);
    const panelUrl = auth?.panel_url || auth?.user?.panel_url || '/registrar-negocio';
    const primaryHref = auth?.user ? panelUrl : appRoute('tenant.register', undefined, '/registrar-negocio');
    const primaryLabel = auth?.user ? 'Ir a mi panel' : 'Registrar negocio';

    const navLinks = [
        { label: 'Producto', href: '#producto' },
        { label: 'Planes', href: '#planes' },
        { label: 'Preguntas', href: '#preguntas' },
        { label: 'Discover', href: appRoute('directory.index', undefined, '/directorio') },
    ];

    return (
        <nav className="sticky top-0 z-50 w-full border-b border-transparent bg-[#eeeeec]/80 backdrop-blur-xl dark:bg-[#131316]/80">
            <div className="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
                <Brand />

                <div className="hidden items-center gap-1 sm:flex">
                    {navLinks.slice(0, 2).map((item) => (
                        <a key={item.label} href={item.href} className="rounded-md px-3 py-2 text-sm text-zinc-600 transition-colors hover:bg-black/5 hover:text-zinc-950 dark:text-zinc-400 dark:hover:bg-white/5 dark:hover:text-zinc-50">
                            {item.label}
                        </a>
                    ))}
                    <div className="relative">
                        <button type="button" onClick={() => setProductOpen((value) => !value)} className="flex items-center gap-1 rounded-md px-3 py-2 text-sm text-zinc-600 transition-colors hover:bg-black/5 hover:text-zinc-950 dark:text-zinc-400 dark:hover:bg-white/5 dark:hover:text-zinc-50" aria-expanded={productOpen}>
                            Producto
                            <ChevronDown className={`size-3.5 transition-transform ${productOpen ? 'rotate-180' : ''}`} aria-hidden="true" />
                        </button>
                        <AnimatePresence>
                            {productOpen ? (
                                <motion.div initial={{ opacity: 0, y: -6 }} animate={{ opacity: 1, y: 0 }} exit={{ opacity: 0, y: -6 }} className="absolute left-0 top-11 w-80 rounded-xl border border-zinc-300 bg-[#eeeeec] p-2 shadow-xl dark:border-zinc-800 dark:bg-[#1c1c1c]">
                                    {productEngines.map(({ icon: Icon, name, role }) => (
                                        <a key={name} href="#producto" onClick={() => setProductOpen(false)} className="flex gap-3 rounded-lg p-3 text-left transition-colors hover:bg-black/5 dark:hover:bg-white/5">
                                            <Icon className="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                                            <span><span className="block text-sm font-semibold">{name}</span><span className="mt-0.5 block text-xs leading-5 text-zinc-500">{role}</span></span>
                                        </a>
                                    ))}
                                </motion.div>
                            ) : null}
                        </AnimatePresence>
                    </div>
                    {navLinks.slice(2).map((item) => (
                        <a key={item.label} href={item.href} className="rounded-md px-3 py-2 text-sm text-zinc-600 transition-colors hover:bg-black/5 hover:text-zinc-950 dark:text-zinc-400 dark:hover:bg-white/5 dark:hover:text-zinc-50">
                            {item.label}
                        </a>
                    ))}
                </div>

                <div className="flex items-center gap-2">
                    {!auth?.user && (
                        <Link href={appRoute('login', undefined, '/login')} className="hidden rounded-md px-3 py-2 text-sm font-medium text-zinc-700 transition-colors hover:bg-black/5 dark:text-zinc-300 dark:hover:bg-white/5 lg:inline-flex">
                            Iniciar sesión
                        </Link>
                    )}
                    {(auth?.user || canRegister !== false) && (
                        <Link href={primaryHref} className="hidden items-center gap-1.5 rounded-md bg-zinc-950 px-3.5 py-2 text-sm font-medium text-white shadow-sm transition-transform hover:scale-[1.02] dark:bg-zinc-50 dark:text-zinc-950 sm:inline-flex">
                            {primaryLabel}
                            <ArrowRight className="size-3.5" aria-hidden="true" />
                        </Link>
                    )}
                    <button type="button" onClick={onThemeChange} className="flex size-10 items-center justify-center rounded-md text-zinc-700 transition-colors hover:bg-black/5 dark:text-zinc-300 dark:hover:bg-white/5" aria-label={dark ? 'Usar tema claro' : 'Usar tema oscuro'}>
                        {dark ? <Sun className="size-4" /> : <Moon className="size-4" />}
                    </button>
                    <button type="button" onClick={() => setOpen((value) => !value)} className="flex size-10 items-center justify-center rounded-md text-zinc-700 hover:bg-black/5 dark:text-zinc-300 dark:hover:bg-white/5 sm:hidden" aria-label={open ? 'Cerrar menú' : 'Abrir menú'} aria-expanded={open}>
                        {open ? <X className="size-5" /> : <Menu className="size-5" />}
                    </button>
                </div>
            </div>

            <AnimatePresence>
                {open && (
                    <motion.div initial={{ opacity: 0, height: 0 }} animate={{ opacity: 1, height: 'auto' }} exit={{ opacity: 0, height: 0 }} className="overflow-hidden border-t border-zinc-300/70 px-4 pb-4 dark:border-zinc-800 sm:hidden">
                        <div className="flex flex-col gap-1 pt-3">
                            {navLinks.map((item) => (
                                <a key={item.label} href={item.href} onClick={() => setOpen(false)} className="rounded-lg px-3 py-2.5 text-sm text-zinc-700 hover:bg-black/5 dark:text-zinc-300 dark:hover:bg-white/5">
                                    {item.label}
                                </a>
                            ))}
                            <div className="mt-2 grid grid-cols-2 gap-2">
                                {!auth?.user && <Link href={appRoute('login', undefined, '/login')} className="rounded-lg border border-zinc-300 px-3 py-2.5 text-center text-sm font-medium dark:border-zinc-700">Entrar</Link>}
                                {(auth?.user || canRegister !== false) && <Link href={primaryHref} className="rounded-lg bg-zinc-950 px-3 py-2.5 text-center text-sm font-medium text-white dark:bg-zinc-50 dark:text-zinc-950">{primaryLabel}</Link>}
                            </div>
                        </div>
                    </motion.div>
                )}
            </AnimatePresence>
        </nav>
    );
}

function Hero({ auth, canRegister }) {
    const panelUrl = auth?.panel_url || auth?.user?.panel_url || '/registrar-negocio';
    const primaryHref = auth?.user ? panelUrl : appRoute('tenant.register', undefined, '/registrar-negocio');
    const primaryLabel = auth?.user ? 'Ir a mi panel' : 'Empezar gratis';

    return (
        <div className="relative overflow-hidden" data-testid="landing-page">
            <section className="relative z-10 mx-auto flex max-w-7xl flex-col items-center justify-center gap-10 px-4 pb-20 pt-24 text-center sm:px-6 sm:pb-28 sm:pt-32 lg:px-8">
                <motion.div initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} transition={{ duration: 0.65, ease: 'easeOut' }} className="mx-auto flex max-w-4xl flex-col items-center">
                    <span className="mb-5 inline-flex items-center gap-2 rounded-full border border-zinc-300 bg-white/35 px-3 py-1 text-xs font-medium text-zinc-700 shadow-sm backdrop-blur dark:border-zinc-800 dark:bg-zinc-900/40 dark:text-zinc-300">
                        <Zap className="size-3.5" aria-hidden="true" />
                        Nacida en Honduras · construida para operar globalmente
                    </span>
                    <h1 className="max-w-4xl bg-gradient-to-b from-sky-800 to-zinc-950 bg-clip-text text-4xl font-medium leading-[1.06] tracking-[-0.045em] text-transparent dark:from-sky-100 dark:to-zinc-100 sm:text-6xl lg:text-7xl">
                        Convierte conversaciones en respuestas, oportunidades y acciones
                    </h1>
                    <p className="mt-6 max-w-2xl text-balance text-base leading-7 text-zinc-600 dark:text-zinc-400 sm:text-lg">
                        Dovra es el Business OS conversacional que conecta los datos y reglas de tu negocio con empleados de IA gobernados y resultados comerciales medibles.
                    </p>
                    <div className="mt-8 flex w-full flex-col items-center justify-center gap-3 sm:w-auto sm:flex-row">
                        {(auth?.user || canRegister !== false) && (
                            <motion.div whileHover={{ scale: 1.04 }} className="w-full sm:w-auto">
                                <Link href={primaryHref} data-testid="landing-primary-cta" className="flex h-11 w-full items-center justify-center gap-2 rounded-md bg-zinc-950 px-5 text-sm font-medium text-white shadow-lg shadow-zinc-950/15 transition-colors hover:bg-zinc-800 dark:bg-zinc-50 dark:text-zinc-950 dark:hover:bg-white sm:w-auto">
                                    {primaryLabel}
                                    <ArrowRight className="size-4" aria-hidden="true" />
                                </Link>
                            </motion.div>
                        )}
                        <Link href={appRoute('directory.index', undefined, '/directorio')} data-testid="landing-directory-cta" className="flex h-11 w-full items-center justify-center gap-2 rounded-md border border-zinc-300 bg-white/30 px-5 text-sm font-medium text-zinc-800 shadow-sm backdrop-blur transition-colors hover:bg-white/60 dark:border-zinc-700 dark:bg-zinc-900/30 dark:text-zinc-200 dark:hover:bg-zinc-900/70 sm:w-auto">
                            <Search className="size-4" aria-hidden="true" />
                             Para compradores · Discover
                         </Link>
                     </div>
                     <div className="mt-5 grid w-full max-w-2xl gap-2 text-left text-xs text-zinc-600 dark:text-zinc-400 sm:grid-cols-2">
                         <p className="rounded-lg border border-zinc-300/70 bg-white/25 px-3 py-2.5 dark:border-zinc-800 dark:bg-zinc-900/25"><strong className="text-zinc-900 dark:text-zinc-100">Para negocios:</strong> prepare context, simulate a role and request evidence-gated activation.</p>
                         <p className="rounded-lg border border-zinc-300/70 bg-white/25 px-3 py-2.5 dark:border-zinc-800 dark:bg-zinc-900/25"><strong className="text-zinc-900 dark:text-zinc-100">Para compradores:</strong> encuentra oferta publicada y prepara la siguiente acción.</p>
                     </div>
                     <p className="mt-4 text-xs text-zinc-600 dark:text-zinc-400">Suscripción sin comisión por transacción · IA y canales en vivo según disponibilidad del proveedor y del mercado</p>
                 </motion.div>
            </section>

            <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} transition={{ duration: 1.8, delay: 0.25 }} className="pointer-events-none absolute -top-32 right-[-10%] flex h-full w-3/4 items-center justify-center" aria-hidden="true">
                <div className="h-[600px] w-12 rotate-[32deg] rounded-3xl bg-[#5ba8ff] opacity-70 blur-[72px] dark:bg-[#016fee]" />
            </motion.div>
        </div>
    );
}

function Partners() {
    return (
        <section className="mx-auto flex w-full max-w-3xl flex-col items-center justify-center gap-10 px-4 py-24 text-center md:px-8">
            <motion.div initial={{ y: 20, opacity: 0, filter: 'blur(3px)' }} whileInView={{ y: 0, opacity: 1, filter: 'blur(0px)' }} viewport={{ once: true }} transition={{ duration: 0.5, type: 'spring', bounce: 0 }}>
                <h2 className="bg-gradient-to-b from-zinc-950 to-zinc-500 bg-clip-text text-xl font-semibold text-transparent dark:from-zinc-100 dark:to-zinc-500 sm:text-2xl">Todo conectado en un solo lugar</h2>
            </motion.div>
            <div className="grid w-full min-w-0 grid-cols-3 place-items-center gap-1 sm:grid-cols-6 sm:gap-5">
                {productPillars.map(({ icon: Icon, label }, index) => (
                    <motion.div key={label} initial={{ y: 20, opacity: 0 }} whileInView={{ y: 0, opacity: 1 }} viewport={{ once: true }} transition={{ duration: 1, delay: index * 0.1, type: 'spring', bounce: 0 }} title={label} className="flex size-11 items-center justify-center rounded-xl border border-zinc-300/70 bg-white/25 text-zinc-800 shadow-sm dark:border-zinc-800 dark:bg-zinc-900/25 dark:text-zinc-200">
                        <Icon className="size-6" aria-hidden="true" />
                        <span className="sr-only">{label}</span>
                    </motion.div>
                ))}
            </div>
        </section>
    );
}

function ProductEngines() {
    return (
        <section id="producto" className="min-w-0 px-4 py-20 [overflow-wrap:anywhere] sm:py-28">
            <div className="mx-auto max-w-6xl">
                <motion.div {...reveal} className="mb-12 grid gap-5 lg:grid-cols-[0.9fr_1.1fr] lg:items-end">
                    <div>
                        <p className="text-xs font-semibold uppercase tracking-[0.18em] text-sky-700 dark:text-sky-300">Un sistema, cuatro motores</p>
                        <h2 className="mt-3 max-w-xl text-3xl font-medium leading-tight tracking-[-0.035em] text-zinc-950 dark:text-zinc-50 sm:text-5xl">
                            La operación alimenta a la IA. La IA devuelve resultados a la operación.
                        </h2>
                    </div>
                    <p className="max-w-xl text-sm leading-7 text-zinc-600 dark:text-zinc-400 sm:text-base">
                        Dovra does not connect a prompt to an isolated tool. It connects context → conversation → action → approval → outcome under one governed permission boundary.
                    </p>
                </motion.div>

                <div className="grid gap-px overflow-hidden rounded-2xl border border-zinc-300/80 bg-zinc-300/80 shadow-sm dark:border-zinc-800 dark:bg-zinc-800 md:grid-cols-2">
                    {productEngines.map(({ name, role, icon: Icon, content, outcome }, index) => (
                        <motion.article
                            key={name}
                            initial={{ opacity: 0, y: 16 }}
                            whileInView={{ opacity: 1, y: 0 }}
                            viewport={{ once: true, amount: 0.3 }}
                            transition={{ duration: 0.5, delay: index * 0.08 }}
                            className="group min-w-0 bg-[#eeeeec] p-6 dark:bg-[#131316] sm:p-8"
                        >
                            <div className="flex items-start justify-between gap-4">
                                <div>
                                    <p className="text-xs font-medium uppercase tracking-[0.14em] text-zinc-500">{role}</p>
                                    <h3 className="mt-2 text-xl font-semibold tracking-tight sm:text-2xl">{name}</h3>
                                </div>
                                <div className="flex size-11 shrink-0 items-center justify-center rounded-xl border border-zinc-300 bg-white/50 text-sky-700 transition-transform group-hover:-rotate-3 group-hover:scale-105 dark:border-zinc-700 dark:bg-zinc-900/60 dark:text-sky-300">
                                    <Icon className="size-5" aria-hidden="true" />
                                </div>
                            </div>
                            <p className="mt-6 text-sm leading-7 text-zinc-600 dark:text-zinc-400">{content}</p>
                            <p className="mt-5 border-l-2 border-sky-500 pl-3 text-sm font-medium text-zinc-900 dark:text-zinc-100">{outcome}</p>
                        </motion.article>
                    ))}
                </div>

                <motion.div {...reveal} className="mt-8 grid gap-3 rounded-2xl border border-zinc-300/80 bg-white/25 p-5 dark:border-zinc-800 dark:bg-zinc-900/25 sm:grid-cols-[auto_1fr] sm:items-center sm:gap-6 sm:p-6">
                    <span className="inline-flex w-fit items-center gap-2 rounded-full bg-zinc-950 px-3 py-1.5 text-xs font-medium text-white dark:bg-zinc-50 dark:text-zinc-950">
                        <Check className="size-3.5" aria-hidden="true" />
                        Gobernanza fuera del modelo
                    </span>
                    <p className="text-sm leading-6 text-zinc-700 dark:text-zinc-300">
                        La IA propone; las políticas de Dovra autorizan. Identidad, permisos, riesgo, límites, idempotencia y confirmación se evalúan antes de ejecutar.
                    </p>
                </motion.div>
            </div>
        </section>
    );
}

function Stats() {
    return (
        <section className="min-w-0 px-4 py-16 [overflow-wrap:anywhere] sm:py-20">
            <motion.div {...reveal} className="mx-auto grid max-w-6xl grid-cols-2 gap-x-5 gap-y-10 md:grid-cols-4">
                {stats.map((stat) => (
                    <div key={stat.label} className="min-w-0 text-center">
                        <p className="text-3xl font-bold tracking-tight text-zinc-950 dark:text-zinc-50 sm:text-4xl">{stat.value}</p>
                        <p className="mt-2 text-sm font-medium text-zinc-600 dark:text-zinc-400">{stat.label}</p>
                    </div>
                ))}
            </motion.div>
        </section>
    );
}

function Pricing({ auth, canRegister, proPlanPrice }) {
    const plans = buildPlans(proPlanPrice);
    const panelUrl = auth?.panel_url || auth?.user?.panel_url || '/registrar-negocio';
    const ctaHref = auth?.user ? panelUrl : appRoute('tenant.register', undefined, '/registrar-negocio');

    return (
        <section id="planes" data-testid="landing-pricing" className="mx-auto w-full max-w-7xl px-3 py-16 sm:px-4 sm:py-24 md:px-6">
            <motion.div {...reveal} className="mb-12 flex flex-col items-center gap-3 text-center sm:mb-16">
                <h2 className="bg-gradient-to-b from-zinc-950 to-zinc-500 bg-clip-text text-xl font-semibold text-transparent dark:from-zinc-100 dark:to-zinc-500 sm:text-2xl">Elige cómo quieres empezar</h2>
                <p className="max-w-2xl text-zinc-600 dark:text-zinc-400">Explore es setup y sandbox. Los planes en vivo dependen del price book, proveedor y capacidades habilitadas para cada mercado.</p>
            </motion.div>

            <div className="mx-auto grid min-w-0 max-w-5xl grid-cols-[minmax(0,1fr)] gap-5 [overflow-wrap:anywhere] md:grid-cols-3 md:gap-7">
                {plans.map((plan, index) => (
                    <motion.article key={plan.name} {...reveal} transition={{ duration: 0.55, delay: index * 0.08 }} className={`relative min-w-0 flex h-full flex-col rounded-2xl border bg-white/20 p-5 shadow-sm backdrop-blur-sm dark:bg-zinc-900/20 sm:p-6 ${plan.featured ? 'border-2 border-zinc-950 bg-black/[0.035] shadow-lg md:scale-[1.03] dark:border-zinc-100 dark:bg-white/[0.045]' : 'border-zinc-300/80 dark:border-zinc-800'}`}>
                        {plan.featured && <span className="absolute -top-4 left-1/2 -translate-x-1/2 max-w-full whitespace-normal rounded-full border-2 text-center border-zinc-950 bg-[#eeeeec] px-4 py-1 text-xs font-medium text-zinc-950 dark:border-zinc-100 dark:bg-[#131316] dark:text-zinc-100">Primer Market Pack</span>}
                        <div className="text-center">
                            <h3 className="text-lg font-semibold">{plan.name}</h3>
                            <p className="mt-2 min-h-10 text-sm text-zinc-600 dark:text-zinc-400">{plan.description}</p>
                            <div className="mt-5 flex items-baseline justify-center">
                                <span className="text-3xl font-bold tracking-tight sm:text-4xl">{plan.price}</span>
                                {plan.suffix && <span className="ml-1 text-sm text-zinc-500">{plan.suffix}</span>}
                            </div>
                        </div>
                        <div className="my-6 h-px bg-zinc-300/80 dark:bg-zinc-800" />
                        <ul className="flex-1 space-y-3">
                            {plan.features.map((feature) => (
                                <li key={feature} className="flex items-start gap-2.5 text-sm text-zinc-700 dark:text-zinc-300">
                                    <Check className="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                                    {feature}
                                </li>
                            ))}
                        </ul>
                        {(auth?.user || canRegister !== false) && <Link href={ctaHref} className={`mt-7 flex h-10 items-center justify-center rounded-md px-4 text-sm font-medium transition-transform hover:scale-[1.02] ${plan.featured ? 'bg-zinc-950 text-white dark:bg-zinc-50 dark:text-zinc-950' : 'border border-zinc-300 bg-white/20 text-zinc-950 dark:border-zinc-700 dark:bg-zinc-900/20 dark:text-zinc-100'}`}>{auth?.user ? 'Ir a mi panel' : plan.name === 'Explore' ? 'Abrir Explore' : 'Consultar disponibilidad'}</Link>}
                    </motion.article>
                ))}
            </div>
        </section>
    );
}

function FAQ() {
    const [open, setOpen] = useState(0);

    return (
        <motion.section id="preguntas" data-testid="landing-faq" {...reveal} className="mx-auto flex w-full max-w-7xl flex-col items-center gap-8 px-4 py-24 sm:py-28 md:px-8">
            <div className="flex flex-col items-center gap-3 text-center">
                <h2 className="bg-gradient-to-b from-zinc-950 to-zinc-500 bg-clip-text text-2xl font-bold text-transparent dark:from-zinc-100 dark:to-zinc-500 sm:text-3xl">Preguntas frecuentes</h2>
                <p className="max-w-xl text-zinc-600 dark:text-zinc-400">Lo esencial antes de registrar tu negocio.</p>
            </div>
            <div className="w-full max-w-xl divide-y divide-zinc-300/80 border-y border-zinc-300/80 dark:divide-zinc-800 dark:border-zinc-800">
                {faqs.map((item, index) => {
                    const active = open === index;
                    return (
                        <div key={item.question}>
                            <button type="button" onClick={() => setOpen(active ? -1 : index)} className="flex w-full items-center justify-between gap-4 py-5 text-left text-sm font-medium text-zinc-800 dark:text-zinc-200" aria-expanded={active}>
                                {item.question}
                                <ChevronDown className={`size-4 shrink-0 transition-transform ${active ? 'rotate-180' : ''}`} aria-hidden="true" />
                            </button>
                            <AnimatePresence initial={false}>
                                {active && <motion.div initial={{ height: 0, opacity: 0 }} animate={{ height: 'auto', opacity: 1 }} exit={{ height: 0, opacity: 0 }} className="overflow-hidden"><p className="pb-5 text-sm leading-6 text-zinc-600 dark:text-zinc-400">{item.answer}</p></motion.div>}
                            </AnimatePresence>
                        </div>
                    );
                })}
            </div>
        </motion.section>
    );
}

function Footer({ auth, canRegister }) {
    const panelUrl = auth?.panel_url || auth?.user?.panel_url || '/registrar-negocio';
    return (
        <footer className="w-full border-t border-zinc-300/80 bg-white/15 dark:border-zinc-800 dark:bg-zinc-900/15">
            <div className="mx-auto max-w-6xl px-4 py-10 sm:py-12">
                <div className="grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
                    <div className="space-y-3">
                        <Brand />
                        <p className="max-w-xs text-sm leading-6 text-zinc-600 dark:text-zinc-400">Business OS conversacional global con agentes gobernados y un marketplace basado en evidencia publicada.</p>
                    </div>
                    <div>
                        <h3 className="text-sm font-semibold">Producto</h3>
                        <div className="mt-3 flex flex-col gap-2 text-sm text-zinc-600 dark:text-zinc-400">
                            <a href="#producto" className="hover:text-zinc-950 dark:hover:text-zinc-100">Producto</a>
                            <a href="#planes" className="hover:text-zinc-950 dark:hover:text-zinc-100">Planes</a>
                            <Link href={appRoute('directory.index', undefined, '/directorio')} className="hover:text-zinc-950 dark:hover:text-zinc-100">Directorio</Link>
                        </div>
                    </div>
                    <div>
                        <h3 className="text-sm font-semibold">Cuenta y legal</h3>
                        <div className="mt-3 flex flex-col gap-2 text-sm text-zinc-600 dark:text-zinc-400">
                            <Link href={auth?.user ? panelUrl : appRoute('login', undefined, '/login')} className="hover:text-zinc-950 dark:hover:text-zinc-100">{auth?.user ? 'Mi panel' : 'Iniciar sesión'}</Link>
                            {!auth?.user && canRegister !== false && <Link href={appRoute('tenant.register', undefined, '/registrar-negocio')} className="hover:text-zinc-950 dark:hover:text-zinc-100">Registrar negocio</Link>}
                            <Link href={appRoute('legal.privacy', undefined, '/legal/privacy')} className="hover:text-zinc-950 dark:hover:text-zinc-100">Privacidad</Link>
                            <Link href={appRoute('legal.terms', undefined, '/legal/terms')} className="hover:text-zinc-950 dark:hover:text-zinc-100">Términos</Link>
                        </div>
                    </div>
                </div>
                <div className="mt-8 flex flex-col items-center justify-between gap-2 border-t border-zinc-300/80 pt-6 text-center text-xs text-zinc-600 dark:border-zinc-800 dark:text-zinc-400 sm:flex-row sm:text-left">
                    <span>© {new Date().getFullYear()} Dovra AI. Todos los derechos reservados.</span>
                    <span className="font-medium">Hecho para mover negocios hacia adelante.</span>
                </div>
            </div>
        </footer>
    );
}

export default function Welcome({ auth, canRegister = true, proPlanPrice }) {
    const [dark, setDark] = useState(false);

    return (
        <MotionConfig reducedMotion="user">
            <>
                <Head title={`${PUBLIC_BRAND.name} — ${PUBLIC_BRAND.category}`}>
                <meta name="description" content={PUBLIC_BRAND.commercialPromise} />
            </Head>
            <div className={dark ? 'dark' : ''}>
                <div className="min-h-screen overflow-x-hidden bg-[#eeeeec] font-sans text-zinc-950 selection:bg-sky-300/70 dark:bg-[#131316] dark:text-[#eeeeec] dark:selection:bg-sky-700/70">
                    <SkipLink />
                    <Navbar auth={auth} canRegister={canRegister} dark={dark} onThemeChange={() => setDark((value) => !value)} />
                    <main id="main-content" tabIndex={-1} className="outline-none">
                        <Hero auth={auth} canRegister={canRegister} />
                        <Partners />
                        <ProductEngines />
                        <Stats />
                        <Pricing auth={auth} canRegister={canRegister} proPlanPrice={proPlanPrice} />
                        <FAQ />
                    </main>
                    <Footer auth={auth} canRegister={canRegister} />
                </div>
            </div>
            </>
        </MotionConfig>
    );
}
