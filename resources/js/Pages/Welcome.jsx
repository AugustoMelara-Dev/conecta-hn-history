import { Head, Link } from '@inertiajs/react';
import { useMemo } from 'react';
import {
    ArrowRight,
    BarChart3,
    CalendarCheck2,
    CheckCircle2,
    ChevronDown,
    MessageSquareMore,
    ReceiptText,
    ShoppingCart,
    Star,
    Store,
    Stethoscope,
    UtensilsCrossed,
} from 'lucide-react';
import PublicLayout from '@/Layouts/PublicLayout';
import { resolveTenantVisual } from '@/lib/tenantPresentation';
import { PUBLIC_DISPLAY_FONT, PUBLIC_LABEL_FONT } from '@/lib/publicTypography';

function formatCurrency(value) {
    return Number(value ?? 0).toLocaleString('es-HN', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    });
}

function safeRoute(name, fallback = '/') {
    try {
        if (typeof route === 'function') {
            const router = route();
            if (typeof router?.has === 'function' && router.has(name)) {
                return route(name);
            }
        }
    } catch (_error) {
        // Fall back to a safe URL when Ziggy route metadata is missing.
    }

    return fallback;
}

function SectionEyebrow({ children, dark = false }) {
    return (
        <p
            className={`text-xs font-bold uppercase tracking-[0.22em] ${dark ? 'text-slate-300' : 'text-slate-500'}`}
            style={{ fontFamily: PUBLIC_LABEL_FONT }}
        >
            {children}
        </p>
    );
}

function SectionTitle({ children, dark = false, className = '' }) {
    return (
        <h2
            className={`font-extrabold tracking-tight ${dark ? 'text-white' : 'text-slate-950'} ${className}`}
            style={{ fontFamily: PUBLIC_DISPLAY_FONT }}
        >
            {children}
        </h2>
    );
}

function DashboardHeroMockup() {
    return (
        <div className="relative isolate">
            <div className="absolute -inset-4 rounded-[2rem] bg-slate-300/40 blur-2xl transition-all duration-500 group-hover:bg-slate-300/60" />
            <div className="relative overflow-hidden rounded-[1.75rem] border border-slate-200 bg-slate-100 shadow-2xl shadow-slate-950/12">
                <div className="absolute inset-0 bg-[radial-gradient(circle_at_top_left,rgba(255,255,255,0.9),transparent_28%),linear-gradient(135deg,rgba(13,21,40,0.08),transparent_38%)]" />
                <div className="relative grid gap-4 p-5 md:grid-cols-[92px,minmax(0,1fr)] md:p-6">
                    <div className="hidden rounded-[1.4rem] bg-white/90 p-3 md:block">
                        <div className="space-y-3">
                            {[...Array(5)].map((_, index) => (
                                <div
                                    key={index}
                                    className={`flex h-11 items-center justify-center rounded-xl ${index === 0 ? 'bg-slate-950 text-white' : 'bg-slate-100 text-slate-400'}`}
                                >
                                    <div className={`h-4 w-4 rounded-full ${index === 0 ? 'bg-white' : 'bg-slate-300'}`} />
                                </div>
                            ))}
                        </div>
                    </div>

                    <div className="space-y-4">
                        <div className="grid gap-4 sm:grid-cols-[1.2fr,0.8fr]">
                            <div className="rounded-[1.4rem] bg-white/92 p-4 shadow-lg shadow-slate-950/5">
                                <div className="flex items-start justify-between gap-4">
                                    <div>
                                        <SectionEyebrow>Capa operativa</SectionEyebrow>
                                        <p className="mt-2 text-2xl font-black text-slate-950" style={{ fontFamily: PUBLIC_DISPLAY_FONT }}>
                                            L. 124,820
                                        </p>
                                        <p className="mt-2 text-sm text-slate-500">
                                            Ventas, pedidos, campanas y operacion integrados en una sola vista.
                                        </p>
                                    </div>
                                    <div className="rounded-2xl bg-slate-950 px-3 py-2 text-xs font-bold text-white" style={{ fontFamily: PUBLIC_LABEL_FONT }}>
                                        LIVE
                                    </div>
                                </div>
                                <div className="mt-6 flex h-28 items-end gap-2">
                                    {[32, 56, 48, 70, 62, 95, 76].map((value) => (
                                        <div key={value} className="flex-1 rounded-full bg-slate-200">
                                            <div className="rounded-full bg-slate-950" style={{ height: `${value}%` }} />
                                        </div>
                                    ))}
                                </div>
                            </div>

                            <div className="rounded-[1.4rem] bg-slate-950 p-4 text-white shadow-xl shadow-slate-950/12">
                                <SectionEyebrow dark>Motor comercial</SectionEyebrow>
                                <div className="mt-5 space-y-4">
                                    {[
                                        ['Pedidos', '48 activos'],
                                        ['Conversion', '+18%'],
                                        ['Clientes', '2.4k'],
                                    ].map(([label, value]) => (
                                        <div key={label} className="rounded-2xl border border-white/10 bg-white/5 px-4 py-3">
                                            <p className="text-[11px] font-bold uppercase tracking-[0.18em] text-slate-300" style={{ fontFamily: PUBLIC_LABEL_FONT }}>
                                                {label}
                                            </p>
                                            <p className="mt-2 text-xl font-bold" style={{ fontFamily: PUBLIC_DISPLAY_FONT }}>{value}</p>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        </div>

                        <div className="grid gap-4 md:grid-cols-3">
                            {[
                                { icon: ShoppingCart, title: 'Catalogo inteligente', body: 'Inventario y visibilidad integrados.' },
                                { icon: MessageSquareMore, title: 'WhatsApp nativo', body: 'Pedidos y mensajes sin salir del flujo.' },
                                { icon: CalendarCheck2, title: 'Reservas y citas', body: 'Agenda operativa para salud y servicios.' },
                            ].map((item) => (
                                <div key={item.title} className="rounded-[1.35rem] bg-white/92 p-4 shadow-lg shadow-slate-950/5">
                                    <item.icon className="h-5 w-5 text-slate-950" />
                                    <p className="mt-4 text-lg font-bold text-slate-950" style={{ fontFamily: PUBLIC_DISPLAY_FONT }}>
                                        {item.title}
                                    </p>
                                    <p className="mt-2 text-sm leading-6 text-slate-500">{item.body}</p>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}

function HeroSection() {
    return (
        <header className="px-6 pb-24 pt-32 md:pb-32 md:pt-48">
            <div className="mx-auto max-w-7xl">
                <div className="grid items-center gap-12 md:grid-cols-2">
                    <div className="space-y-8">
                        <div className="inline-flex items-center rounded-full bg-slate-200 px-3 py-1 text-xs font-bold uppercase tracking-[0.22em] text-slate-800" style={{ fontFamily: PUBLIC_LABEL_FONT }}>
                            Vito Business OS
                        </div>

                        <h1
                            className="text-5xl font-extrabold leading-[1.05] tracking-tight text-slate-950 md:text-7xl"
                            style={{ fontFamily: PUBLIC_DISPLAY_FONT }}
                        >
                            El Sistema Operativo para{' '}
                            <span className="text-slate-600">Negocios que Escalan.</span>
                        </h1>

                        <p className="max-w-lg text-xl leading-relaxed text-slate-600">
                            Gestiona catalogo, reservas, pedidos, CRM, billing y micrositio publico desde un solo panel
                            para negocios locales.
                        </p>

                        <div className="flex flex-col gap-4 pt-4 sm:flex-row">
                            <Link
                                href={route('tenant.register')}
                                className="inline-flex items-center justify-center rounded-xl bg-gradient-to-br from-slate-950 via-slate-900 to-slate-700 px-8 py-4 text-lg font-bold text-white shadow-lg transition hover:-translate-y-0.5"
                            >
                                Registrar negocio
                            </Link>
                            <Link
                                href={route('public.explore')}
                                className="inline-flex items-center justify-center rounded-xl bg-slate-200 px-8 py-4 text-lg font-bold text-slate-950 transition hover:bg-slate-300"
                            >
                                Explorar negocios
                            </Link>
                        </div>
                    </div>

                    <div className="group relative">
                        <DashboardHeroMockup />
                    </div>
                </div>
            </div>
        </header>
    );
}

function TrustSection({ businesses = [] }) {
    const labels = businesses.length
        ? businesses.map((business) => business.name.toUpperCase().replace(/\s+/g, '-')).slice(0, 4)
        : ['RESTAURANTE-X', 'CLINIC-LAB', 'RETAIL-PRO', 'MODA-HN'];

    return (
        <section className="border-y border-slate-200/70 bg-white/70 py-12">
            <div className="mx-auto max-w-7xl px-6">
                <p className="mb-8 text-center text-xs uppercase tracking-[0.2em] text-slate-500" style={{ fontFamily: PUBLIC_LABEL_FONT }}>
                    Impulsando negocios en toda la region
                </p>
                <div className="flex flex-wrap justify-center gap-12 opacity-50 grayscale transition-all duration-500 hover:grayscale-0">
                    {labels.map((label) => (
                        <div key={label} className="text-2xl font-bold text-slate-900" style={{ fontFamily: PUBLIC_DISPLAY_FONT }}>
                            {label}
                        </div>
                    ))}
                </div>
            </div>
        </section>
    );
}

function BenefitsSection() {
    const smallCards = [
        {
            icon: CalendarCheck2,
            title: 'Reservas y Citas',
            body: 'Ideal para clinicas y salones. Gestion de agenda automatizada.',
            className: 'bg-white border border-slate-200/60',
        },
        {
            icon: MessageSquareMore,
            title: 'WhatsApp Marketing',
            body: 'Envia promociones directas a tus clientes de forma segmentada.',
            className: 'bg-white border border-slate-200/60',
        },
        {
            icon: ReceiptText,
            title: 'Billing y Cobros',
            body: 'Gestiona pagos reportados, estados de suscripcion y seguimiento comercial desde el panel.',
            className: 'bg-slate-200 text-slate-950',
        },
    ];

    return (
        <section id="features" className="bg-slate-100 px-6 py-24">
            <div className="mx-auto max-w-7xl">
                <div className="mb-16">
                    <SectionTitle className="text-4xl">Todo lo que necesitas para operar</SectionTitle>
                    <p className="mt-4 max-w-xl text-slate-600">
                        Catalogo, reservas, CRM, campañas, analytics y operacion integrados sin duplicar herramientas ni flujos.
                    </p>
                </div>

                <div className="grid h-auto grid-cols-1 gap-6 md:h-[600px] md:grid-cols-12">
                    <article className="group relative overflow-hidden rounded-[1.9rem] border-l-4 border-slate-950 bg-white p-10 md:col-span-8">
                        <div className="relative z-10 flex h-full flex-col justify-between">
                            <div>
                                <BarChart3 className="mb-6 h-10 w-10 text-slate-950" />
                                <h3 className="text-3xl font-bold text-slate-950" style={{ fontFamily: PUBLIC_DISPLAY_FONT }}>
                                    Analytics y Growth Center
                                </h3>
                                <p className="mt-4 max-w-md text-slate-600">
                                    Visualiza ventas, pedidos, crecimiento, embudos y señales operativas desde un solo panel.
                                </p>
                            </div>

                            <button className="mt-8 inline-flex items-center gap-2 text-sm font-bold text-slate-950" style={{ fontFamily: PUBLIC_LABEL_FONT }}>
                                Saber mas
                                <ArrowRight className="h-4 w-4 transition-transform group-hover:translate-x-1" />
                            </button>
                        </div>

                        <div className="absolute bottom-[-10%] right-[-10%] h-2/3 w-2/3 opacity-10 transition-transform duration-700 group-hover:scale-110">
                            <div className="grid h-full grid-cols-6 items-end gap-3">
                                {[18, 24, 31, 44, 58, 70].map((value) => (
                                    <div key={value} className="rounded-full bg-slate-950/70" style={{ height: `${value}%` }} />
                                ))}
                            </div>
                        </div>
                    </article>

                    <article className="rounded-[1.9rem] bg-gradient-to-br from-slate-950 via-slate-900 to-slate-700 p-10 text-white md:col-span-4">
                        <ShoppingCart className="mb-6 h-10 w-10" />
                        <h3 className="text-2xl font-bold" style={{ fontFamily: PUBLIC_DISPLAY_FONT }}>
                            Catalogo Digital
                        </h3>
                        <p className="mt-4 text-sm text-slate-300">
                            Tu inventario sincronizado con WhatsApp y redes sociales para ventas sin friccion.
                        </p>
                    </article>

                    {smallCards.map((card) => (
                        <article key={card.title} className={`rounded-[1.9rem] p-8 ${card.className} md:col-span-4`}>
                            <card.icon className="mb-4 h-8 w-8 text-slate-950" />
                            <h4 className="text-xl font-bold text-slate-950" style={{ fontFamily: PUBLIC_DISPLAY_FONT }}>
                                {card.title}
                            </h4>
                            <p className="mt-2 text-sm text-slate-600">{card.body}</p>
                        </article>
                    ))}
                </div>
            </div>
        </section>
    );
}

function IndustryVisual({ image }) {
    if (image) {
        return <img src={image} alt="Industria destacada" className="h-full w-full object-cover" />;
    }

    return (
        <div className="h-full w-full bg-[radial-gradient(circle_at_top_left,rgba(255,255,255,0.18),transparent_22%),linear-gradient(135deg,#111827,#374151)]" />
    );
}

function IndustriesSection({ image = null }) {
    const industries = [
        {
            icon: UtensilsCrossed,
            title: 'Restaurantes y Cafes',
            body: 'Menus QR dinamicos, gestion de mesas y pedidos a cocina automatizados.',
        },
        {
            icon: Stethoscope,
            title: 'Clinicas y Salud',
            body: 'Expedientes digitales, recordatorios por WhatsApp y control de citas.',
        },
        {
            icon: Store,
            title: 'Retail y Boutique',
            body: 'Control de stock, puntos de venta (POS) y tienda online autogestionable.',
        },
    ];

    return (
        <section id="businesses" className="overflow-hidden px-6 py-24">
            <div className="mx-auto max-w-7xl">
                <div className="grid items-center gap-20 md:grid-cols-2">
                    <div className="order-2 relative h-[500px] md:order-1">
                        <div className="absolute inset-0 overflow-hidden rounded-[1.9rem] bg-slate-200 shadow-xl">
                            <IndustryVisual image={image} />
                            <div className="absolute inset-0 bg-gradient-to-t from-slate-950/25 to-transparent" />
                        </div>

                        <div className="absolute -bottom-10 -right-10 hidden h-64 w-64 rounded-[1.4rem] bg-white p-6 shadow-2xl lg:block">
                            <div className="mb-4 flex items-center gap-3">
                                <div className="flex h-10 w-10 items-center justify-center rounded-full bg-slate-100">
                                    <CheckCircle2 className="h-5 w-5 text-slate-950" />
                                </div>
                                <span className="text-sm font-bold text-slate-950" style={{ fontFamily: PUBLIC_LABEL_FONT }}>
                                    Pedido Recibido
                                </span>
                            </div>
                            <div className="space-y-3">
                                <div className="h-2 w-full rounded-full bg-slate-200" />
                                <div className="h-2 w-2/3 rounded-full bg-slate-200" />
                                <div className="mt-4 h-8 w-full rounded-lg bg-slate-950" />
                            </div>
                        </div>
                    </div>

                    <div className="order-1 space-y-12 md:order-2">
                        <div>
                            <SectionTitle className="text-4xl">Disenado para tu industria</SectionTitle>
                            <div className="mt-8 space-y-8">
                                {industries.map((industry, index) => (
                                    <div key={industry.title} className="flex gap-6">
                                        <div className={`flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-xl ${
                                            index === 0 ? 'bg-slate-950' : index === 1 ? 'bg-slate-700' : 'bg-slate-500'
                                        } text-white`}>
                                            <industry.icon className="h-5 w-5" />
                                        </div>
                                        <div>
                                            <h4 className="text-xl font-bold text-slate-950" style={{ fontFamily: PUBLIC_DISPLAY_FONT }}>
                                                {industry.title}
                                            </h4>
                                            <p className="mt-1 text-slate-600">{industry.body}</p>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    );
}

function PricingSection({ settings = {} }) {
    const starterPrice = formatCurrency(settings.starterPlanPrice ?? settings.freePlanPrice ?? 0);
    const proPrice = formatCurrency(settings.proPlanPrice ?? 499);
    const proOriginalPrice = Number(settings.proPlanOriginalPrice ?? 0);
    const starterLimit = Number(settings.freeProductLimit ?? 50);
    const proLimit = Number(settings.proProductLimit ?? 999);

    return (
        <section id="pricing" className="bg-white px-6 py-24">
            <div className="mx-auto max-w-7xl">
                <div className="mb-16 text-center">
                    <SectionTitle className="text-4xl">Planes que crecen contigo</SectionTitle>
                    <p className="mt-4 text-slate-600">Sin comisiones ocultas. Elige el motor que impulsara tu negocio.</p>
                </div>

                <div className="mx-auto grid max-w-4xl gap-8 md:grid-cols-2">
                    <article className="flex flex-col justify-between rounded-[2rem] border border-slate-200/70 bg-slate-100 p-10 transition-shadow hover:shadow-lg">
                        <div>
                            <h3 className="text-2xl font-bold text-slate-950" style={{ fontFamily: PUBLIC_DISPLAY_FONT }}>
                                Plan Inicial
                            </h3>
                            <p className="mb-6 mt-2 text-sm text-slate-500">Para emprendedores digitalizandose.</p>
                            <div className="mb-8 flex items-baseline gap-1">
                                <span className="text-4xl font-extrabold text-slate-950" style={{ fontFamily: PUBLIC_DISPLAY_FONT }}>
                                    L. {starterPrice}
                                </span>
                                <span className="font-medium text-slate-500">/mes</span>
                            </div>
                            <ul className="mb-10 space-y-4 text-sm">
                                {[
                                    `Catalogo Digital (${starterLimit} items)`,
                                    'Pedidos y cierre por WhatsApp',
                                    'Booking o storefront segun tu negocio',
                                    'Analytics basica',
                                    'Micrositio publico',
                                ].map((item) => (
                                    <li key={item} className="flex items-center gap-3">
                                        <CheckCircle2 className="h-4 w-4 text-slate-950" />
                                        {item}
                                    </li>
                                ))}
                            </ul>
                        </div>

                        <Link
                            href={route('tenant.register')}
                            className="inline-flex w-full items-center justify-center rounded-xl border-2 border-slate-950 py-4 text-center font-bold text-slate-950 transition hover:bg-slate-950 hover:text-white"
                        >
                            Empezar Gratis
                        </Link>
                    </article>

                    <article className="relative flex flex-col justify-between overflow-hidden rounded-[2rem] bg-gradient-to-br from-slate-950 via-slate-900 to-slate-700 p-10 text-white">
                        <div className="absolute right-6 top-6 rounded-full bg-slate-100 px-3 py-1 text-xs font-bold uppercase tracking-[0.18em] text-slate-950" style={{ fontFamily: PUBLIC_LABEL_FONT }}>
                            Recomendado
                        </div>
                        <div>
                            <h3 className="text-2xl font-bold" style={{ fontFamily: PUBLIC_DISPLAY_FONT }}>
                                Plan Business Pro
                            </h3>
                            <p className="mb-6 mt-2 text-sm text-slate-300">Para negocios en expansion.</p>
                            <div className="mb-8 flex items-baseline gap-1">
                                <span className="text-4xl font-extrabold" style={{ fontFamily: PUBLIC_DISPLAY_FONT }}>
                                    L. {proPrice}
                                </span>
                                <span className="font-medium text-slate-300">/mes</span>
                                {proOriginalPrice > Number(settings.proPlanPrice ?? 499) ? (
                                    <span className="ml-3 text-sm font-semibold text-slate-400 line-through">
                                        L. {formatCurrency(proOriginalPrice)}
                                    </span>
                                ) : null}
                            </div>
                            <ul className="mb-10 space-y-4 text-sm">
                                {[
                                    `Catalogo Ilimitado o hasta ${proLimit} items`,
                                    'Growth Center y exports',
                                    'Marketing y cupones',
                                    'Branding del micrositio',
                                    'Operacion multi-sede',
                                ].map((item) => (
                                    <li key={item} className="flex items-center gap-3">
                                        <CheckCircle2 className="h-4 w-4 text-white" />
                                        {item}
                                    </li>
                                ))}
                            </ul>
                        </div>

                        <Link
                            href={route('tenant.register')}
                            className="inline-flex w-full items-center justify-center rounded-xl bg-white py-4 text-center font-bold text-slate-950 transition hover:scale-[1.02]"
                        >
                            Obtener Pro Now
                        </Link>
                    </article>
                </div>
            </div>
        </section>
    );
}

function SocialProofSection({ stats = {} }) {
    return (
        <section className="px-6 py-24">
            <div className="mx-auto max-w-7xl">
                <div className="grid gap-12 md:grid-cols-3">
                    <article className="space-y-4">
                        <div className="flex gap-1 text-slate-950">
                            {[...Array(5)].map((_, index) => (
                                <Star key={index} className="h-5 w-5 fill-current" />
                            ))}
                        </div>
                        <p className="text-lg italic font-medium leading-relaxed text-slate-900">
                            "Ahora tenemos pedidos, reservas y seguimiento comercial mucho mas ordenados desde el mismo panel."
                        </p>
                        <div className="flex items-center gap-4">
                            <div className="flex h-12 w-12 items-center justify-center rounded-full bg-slate-200 text-sm font-bold text-slate-900">
                                CM
                            </div>
                            <div>
                                <p className="text-sm font-bold text-slate-950">Carlos Mendoza</p>
                                <p className="text-xs text-slate-500">Negocio local</p>
                            </div>
                        </div>
                    </article>

                    <article className="flex flex-col items-center justify-center rounded-[1.9rem] bg-white p-10 text-center shadow-sm">
                        <span className="mb-2 text-6xl font-extrabold text-slate-950" style={{ fontFamily: PUBLIC_DISPLAY_FONT }}>
                            {stats?.tenants || 0}+
                        </span>
                        <p className="text-sm font-bold uppercase tracking-[0.18em] text-slate-500" style={{ fontFamily: PUBLIC_LABEL_FONT }}>
                            Negocios visibles
                        </p>
                    </article>

                    <article className="flex flex-col items-center justify-center rounded-[1.9rem] bg-white p-10 text-center shadow-sm">
                        <span className="mb-2 text-6xl font-extrabold text-slate-700" style={{ fontFamily: PUBLIC_DISPLAY_FONT }}>
                            CRM
                        </span>
                        <p className="text-sm font-bold uppercase tracking-[0.18em] text-slate-500" style={{ fontFamily: PUBLIC_LABEL_FONT }}>
                            Seguimiento comercial
                        </p>
                    </article>
                </div>
            </div>
        </section>
    );
}

function FaqSection() {
    const faqs = [
        {
            title: 'Como funciona la integracion con WhatsApp?',
            body: 'Tus clientes navegan tu catalogo digital, agregan productos al carrito y al finalizar, el pedido llega estructurado directamente a tu numero de WhatsApp Business, listo para ser procesado.',
        },
        {
            title: 'Que incluye hoy billing?',
            body: 'Hoy el panel cubre gestion de suscripcion, reporte de pagos, seguimiento comercial y operaciones de cobro dentro del producto.',
        },
        {
            title: 'Puedo usar mi propio dominio?',
            body: 'Absolutamente. Puedes vincular tu dominio personalizado para que tu tienda digital y catalogo luzcan 100% profesionales bajo tu propia marca.',
        },
    ];

    return (
        <section id="faq" className="bg-slate-100/70 px-6 py-24">
            <div className="mx-auto max-w-3xl">
                <SectionTitle className="mb-12 text-center text-3xl">Preguntas Frecuentes</SectionTitle>
                <div className="space-y-4">
                    {faqs.map((faq, index) => (
                        <details key={faq.title} className="group cursor-pointer rounded-[1.4rem] border border-slate-200/60 bg-white p-6" open={index === 0}>
                            <summary className="flex list-none items-center justify-between gap-4 font-bold text-slate-950">
                                <span>{faq.title}</span>
                                <ChevronDown className="h-5 w-5 transition-transform group-open:rotate-180" />
                            </summary>
                            <p className="mt-4 text-sm leading-relaxed text-slate-600">{faq.body}</p>
                        </details>
                    ))}
                </div>
            </div>
        </section>
    );
}

function FinalCtaSection() {
    return (
        <section className="relative overflow-hidden px-6 py-24">
            <div className="absolute inset-0 bg-gradient-to-br from-slate-950 via-slate-900 to-slate-700" />
            <div className="relative z-10 mx-auto max-w-5xl text-center">
                <h2 className="mb-8 text-4xl font-extrabold tracking-tight text-white md:text-6xl" style={{ fontFamily: PUBLIC_DISPLAY_FONT }}>
                    Opera con un panel claro y una presencia publica profesional.
                </h2>
                <p className="mx-auto mb-12 max-w-2xl text-xl text-slate-300">
                    Activa tu catalogo, reservas, CRM y micrositio sin promesas infladas ni superficies duplicadas.
                </p>
                <div className="flex flex-col justify-center gap-6 sm:flex-row">
                    <Link
                        href={route('tenant.register')}
                        className="inline-flex items-center justify-center rounded-xl bg-white px-10 py-5 text-lg font-bold text-slate-950 shadow-2xl transition hover:scale-105"
                    >
                        Empezar ahora gratis
                    </Link>
                    <a
                        href="mailto:soporte@vitobusinessos.com?subject=Demo%20Vito%20Business%20OS"
                        className="inline-flex items-center justify-center rounded-xl border border-white/20 bg-slate-950/20 px-10 py-5 text-lg font-bold text-white transition hover:bg-slate-800"
                    >
                        Agendar demo personalizada
                    </a>
                </div>
            </div>
        </section>
    );
}

export default function Welcome({ newTenants = [], stats = {}, settings = {} }) {
    const heroBusinesses = useMemo(() => newTenants.slice(0, 4), [newTenants]);
    const industryImage = useMemo(
        () => newTenants.map((tenant) => resolveTenantVisual(tenant)).find(Boolean) || null,
        [newTenants],
    );

    const homeUrl = safeRoute('home', '/');

    const schema = {
        '@context': 'https://schema.org',
        '@type': 'WebSite',
        name: 'Vito Business OS',
        url: homeUrl,
    };

    return (
        <PublicLayout>
            <Head title="Vito Business OS | El Sistema Operativo para Negocios Locales">
                <meta
                    name="description"
                    content="Gestiona catalogo, reservas, pedidos, CRM, billing y micrositio publico desde un solo panel para negocios locales."
                />
                <meta property="og:title" content="Vito Business OS | El Sistema Operativo para Negocios Locales" />
                <meta
                    property="og:description"
                    content="Gestiona catalogo, reservas, pedidos, CRM, billing y micrositio publico desde un solo panel para negocios locales."
                />
                <meta property="og:type" content="website" />
                <link rel="canonical" href={homeUrl} />
                <script type="application/ld+json">{JSON.stringify(schema)}</script>
            </Head>

            <HeroSection />
            <TrustSection businesses={heroBusinesses} />
            <BenefitsSection />
            <IndustriesSection image={industryImage} />
            <PricingSection settings={settings} />
            <SocialProofSection stats={stats} />
            <FaqSection />
            <FinalCtaSection />
        </PublicLayout>
    );
}
