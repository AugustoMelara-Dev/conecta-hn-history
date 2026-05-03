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
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import { resolveTenantVisual } from '@/lib/tenantPresentation';
import { PUBLIC_DISPLAY_FONT, PUBLIC_LABEL_FONT } from '@/lib/publicTypography';
import { PUBLIC_BRAND } from '@/lib/publicBrand';
import PublicImage from '@/Components/Public/PublicImage';
import { safeJsonLd } from '@/lib/safeJsonLd';

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

function LandingCtaButton({ href, children, variant = 'default', className = '' }) {
    return (
        <Button asChild variant={variant} className={`h-auto rounded-xl px-8 py-4 text-base font-bold ${className}`}>
            <Link href={href}>{children}</Link>
        </Button>
    );
}

function DashboardHeroMockup() {
    return (
        <div className="relative isolate">
            <div className="absolute -inset-4 rounded-[2rem] bg-muted blur-2xl transition-colors duration-500 group-hover:bg-muted/80" />
            <div className="relative overflow-hidden rounded-[1.75rem] border border-slate-200 bg-slate-100 shadow-2xl shadow-slate-950/12">
                <div className="absolute inset-0 bg-[radial-gradient(circle_at_top_left,rgba(255,255,255,0.9),transparent_28%),linear-gradient(135deg,rgba(13,21,40,0.08),transparent_38%)]" />
                <div className="relative grid gap-4 p-5 md:grid-cols-[92px,minmax(0,1fr)] md:p-6">
                    <div className="hidden rounded-[1.4rem] bg-white/90 p-3 md:block">
                        <div className="flex flex-col gap-3">
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

                    <div className="flex flex-col gap-4">
                        <div className="grid gap-4 sm:grid-cols-[1.2fr,0.8fr]">
                            <Card className="rounded-[1.4rem] border-border/70 bg-background/90 shadow-lg shadow-slate-950/5">
                                <CardHeader className="flex-row items-start justify-between gap-4 p-4">
                                    <div className="grid gap-2">
                                        <SectionEyebrow>Capa operativa</SectionEyebrow>
                                        <CardTitle className="text-2xl font-black text-foreground" style={{ fontFamily: PUBLIC_DISPLAY_FONT }}>
                                            L. 124,820
                                        </CardTitle>
                                        <CardDescription>
                                            Ventas, pedidos, campanas y operacion integrados en una sola vista.
                                        </CardDescription>
                                    </div>
                                    <Badge className="rounded-2xl px-3 py-2 text-xs font-bold" style={{ fontFamily: PUBLIC_LABEL_FONT }}>
                                        Vista referencial
                                    </Badge>
                                </CardHeader>
                                <CardContent className="px-4 pb-4 pt-0">
                                <div className="flex h-28 items-end gap-2">
                                    {[32, 56, 48, 70, 62, 95, 76].map((value) => (
                                        <div key={value} className="flex-1 rounded-full bg-slate-200">
                                            <div className="rounded-full bg-slate-950" style={{ height: `${value}%` }} />
                                        </div>
                                    ))}
                                </div>
                                </CardContent>
                            </Card>

                            <Card className="rounded-[1.4rem] border-slate-800 bg-slate-950 text-white shadow-xl shadow-slate-950/12">
                                <CardHeader className="p-4">
                                    <SectionEyebrow dark>Motor comercial</SectionEyebrow>
                                </CardHeader>
                                <CardContent className="flex flex-col gap-4 p-4 pt-0">
                                    {[
                                        ['Pedidos', '48 activos'],
                                        ['Conversion', '+18%'],
                                        ['Clientes', 'Seguimiento real'],
                                    ].map(([label, value]) => (
                                        <div key={label} className="rounded-2xl border border-white/10 bg-white/5 px-4 py-3">
                                            <p className="text-[11px] font-bold uppercase tracking-[0.18em] text-slate-300" style={{ fontFamily: PUBLIC_LABEL_FONT }}>
                                                {label}
                                            </p>
                                            <p className="mt-2 text-xl font-bold" style={{ fontFamily: PUBLIC_DISPLAY_FONT }}>{value}</p>
                                        </div>
                                    ))}
                                </CardContent>
                            </Card>
                        </div>

                        <div className="grid gap-4 md:grid-cols-3">
                            {[
                                { icon: ShoppingCart, title: 'Catalogo inteligente', body: 'Inventario y visibilidad integrados.' },
                                { icon: MessageSquareMore, title: 'WhatsApp nativo', body: 'Pedidos y mensajes sin salir del flujo.' },
                                { icon: CalendarCheck2, title: 'Reservas y citas', body: 'Agenda operativa para salud y servicios.' },
                            ].map((item) => (
                                <Card key={item.title} className="rounded-[1.35rem] border-border/70 bg-background/90 shadow-lg shadow-slate-950/5">
                                    <CardHeader className="p-4">
                                    <item.icon className="h-5 w-5 text-foreground" aria-hidden="true" />
                                    <CardTitle className="text-lg font-bold text-foreground" style={{ fontFamily: PUBLIC_DISPLAY_FONT }}>
                                        {item.title}
                                    </CardTitle>
                                    <CardDescription className="leading-6">{item.body}</CardDescription>
                                    </CardHeader>
                                </Card>
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
                    <div className="flex flex-col gap-8">
                        <Badge variant="secondary" className="w-fit rounded-full px-3 py-1 text-xs font-bold uppercase tracking-[0.22em]" style={{ fontFamily: PUBLIC_LABEL_FONT }}>
                            {PUBLIC_BRAND.name}
                        </Badge>

                        <h1
                            className="text-5xl font-extrabold leading-[1.05] tracking-tight text-slate-950 md:text-7xl"
                            style={{ fontFamily: PUBLIC_DISPLAY_FONT }}
                        >
                            Gestion y presencia digital{' '}
                            <span className="text-slate-600">para negocios locales.</span>
                        </h1>

                        <p className="max-w-lg text-xl leading-relaxed text-slate-600">
                            Centraliza catalogo, reservas, pedidos y presencia publica en una experiencia clara para tu equipo y tus clientes.
                        </p>

                        <div className="flex flex-col gap-4 pt-4 sm:flex-row">
                            <LandingCtaButton href={route('tenant.register')} className="shadow-lg">
                                Registrar negocio
                            </LandingCtaButton>
                            <LandingCtaButton href={route('public.explore')} variant="secondary">
                                Explorar negocios
                            </LandingCtaButton>
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
                    Impulsando negocios en toda la región
                </p>
                <div className="flex flex-wrap justify-center gap-12 opacity-50 grayscale transition-colors duration-500 hover:grayscale-0">
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
            body: 'Ideal para clínicas y salones. Gestión de agenda automatizada.',
            className: 'bg-white border border-slate-200/60',
        },
        {
            icon: MessageSquareMore,
            title: 'WhatsApp Marketing',
            body: 'Envía promociones directas a tus clientes de forma segmentada.',
            className: 'bg-white border border-slate-200/60',
        },
        {
            icon: ReceiptText,
            title: 'Billing y Cobros',
            body: 'Gestiona pagos reportados, estados de suscripción y seguimiento comercial desde el panel.',
            className: 'bg-slate-200 text-slate-950',
        },
    ];

    return (
        <section id="features" className="bg-slate-100 px-6 py-24">
            <div className="mx-auto max-w-7xl">
                <div className="mb-16">
                    <SectionTitle className="text-4xl">Todo lo que necesitas para operar</SectionTitle>
                    <p className="mt-4 max-w-xl text-slate-600">
                        Catalogo, reservas, campanas, analytics y seguimiento de clientes integrados sin duplicar herramientas ni flujos.
                    </p>
                </div>

                <div className="grid h-auto grid-cols-1 gap-6 md:h-[600px] md:grid-cols-12">
                    <Card className="group relative overflow-hidden rounded-[1.9rem] border-l-4 border-slate-950 bg-background md:col-span-8">
                        <CardContent className="relative z-10 flex h-full flex-col justify-between p-10">
                            <div className="grid gap-4">
                                <BarChart3 className="h-10 w-10 text-foreground" aria-hidden="true" />
                                <CardTitle className="text-3xl font-bold text-foreground" style={{ fontFamily: PUBLIC_DISPLAY_FONT }}>
                                    Reportes y seguimiento comercial
                                </CardTitle>
                                <CardDescription className="max-w-md text-base">
                                    Visualiza ventas, pedidos, crecimiento, embudos y señales operativas desde un solo panel.
                                </CardDescription>
                            </div>

                            <Button asChild variant="link" className="mt-8 w-fit px-0 text-sm font-bold text-foreground" style={{ fontFamily: PUBLIC_LABEL_FONT }}>
                                <Link href={route('public.explore')}>
                                    Ver negocios activos
                                    <ArrowRight data-icon="inline-end" aria-hidden="true" />
                                </Link>
                            </Button>
                        </CardContent>

                        <div className="absolute bottom-[-10%] right-[-10%] h-2/3 w-2/3 opacity-10 transition-transform duration-700 group-hover:scale-110">
                            <div className="grid h-full grid-cols-6 items-end gap-3">
                                {[18, 24, 31, 44, 58, 70].map((value) => (
                                    <div key={value} className="rounded-full bg-slate-950/70" style={{ height: `${value}%` }} />
                                ))}
                            </div>
                        </div>
                    </Card>

                    <Card className="rounded-[1.9rem] border-slate-800 bg-slate-950 text-white md:col-span-4">
                        <CardHeader className="p-10">
                        <ShoppingCart className="h-10 w-10" aria-hidden="true" />
                        <CardTitle className="text-2xl font-bold" style={{ fontFamily: PUBLIC_DISPLAY_FONT }}>
                            Catalogo Digital
                        </CardTitle>
                        <CardDescription className="text-slate-300">
                            Tu inventario sincronizado con WhatsApp y redes sociales para ventas sin friccion.
                        </CardDescription>
                        </CardHeader>
                    </Card>

                    {smallCards.map((card) => (
                        <Card key={card.title} className={`rounded-[1.9rem] ${card.className} md:col-span-4`}>
                            <CardHeader className="p-8">
                            <card.icon className="h-8 w-8 text-foreground" aria-hidden="true" />
                            <CardTitle className="text-xl font-bold text-foreground" style={{ fontFamily: PUBLIC_DISPLAY_FONT }}>
                                {card.title}
                            </CardTitle>
                            <CardDescription>{card.body}</CardDescription>
                            </CardHeader>
                        </Card>
                    ))}
                </div>
            </div>
        </section>
    );
}

function IndustryVisual({ image }) {
    if (image) {
        return (
            <PublicImage
                src={image}
                alt="Negocio destacado en la plataforma"
                width={1200}
                height={900}
                className="h-full w-full object-cover"
            />
        );
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
            body: 'Menús QR dinámicos, gestión de mesas y pedidos a cocina automatizados.',
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
                            <div className="flex flex-col gap-3">
                                <div className="h-2 w-full rounded-full bg-slate-200" />
                                <div className="h-2 w-2/3 rounded-full bg-slate-200" />
                                <div className="mt-4 h-8 w-full rounded-lg bg-slate-950" />
                            </div>
                        </div>
                    </div>

                    <div className="order-1 flex flex-col gap-12 md:order-2">
                        <div>
                            <SectionTitle className="text-4xl">Disenado para tu industria</SectionTitle>
                            <div className="mt-8 flex flex-col gap-8">
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
                    <Card className="flex flex-col justify-between rounded-[2rem] border-slate-200/70 bg-slate-100 transition-shadow hover:shadow-lg">
                        <CardHeader className="p-10 pb-0">
                            <CardTitle className="text-2xl font-bold text-slate-950" style={{ fontFamily: PUBLIC_DISPLAY_FONT }}>
                                Plan Inicial
                            </CardTitle>
                            <CardDescription>Para emprendedores digitalizandose.</CardDescription>
                        </CardHeader>
                        <CardContent className="p-10 pt-6">
                            <div className="mb-8 flex items-baseline gap-1">
                                <span className="text-4xl font-extrabold text-slate-950" style={{ fontFamily: PUBLIC_DISPLAY_FONT }}>
                                    L. {starterPrice}
                                </span>
                                <span className="font-medium text-slate-500">/mes</span>
                            </div>
                            <ul className="flex flex-col gap-4 text-sm">
                                {[
                                    `Catálogo digital (${starterLimit} items)`,
                                    'Pedidos y cierre por WhatsApp',
                                    'Booking o storefront según tu negocio',
                                    'Analítica básica',
                                    'Micrositio público',
                                ].map((item) => (
                                    <li key={item} className="flex items-center gap-3">
                                        <CheckCircle2 className="h-4 w-4 text-slate-950" aria-hidden="true" />
                                        {item}
                                    </li>
                                ))}
                            </ul>
                        </CardContent>

                        <CardFooter className="p-10 pt-0">
                            <Button asChild variant="outline" className="h-auto w-full rounded-xl border-2 border-slate-950 py-4 text-center font-bold text-slate-950 hover:bg-slate-950 hover:text-white">
                                <Link href={route('tenant.register')}>
                                    Empezar Gratis
                                </Link>
                            </Button>
                        </CardFooter>
                    </Card>

                    <Card className="relative flex flex-col justify-between overflow-hidden rounded-[2rem] border-slate-800 bg-slate-950 text-white">
                        <Badge className="absolute right-6 top-6 rounded-full bg-slate-100 px-3 py-1 text-xs font-bold uppercase tracking-[0.18em] text-slate-950 hover:bg-slate-100" style={{ fontFamily: PUBLIC_LABEL_FONT }}>
                            Recomendado
                        </Badge>
                        <CardHeader className="p-10 pb-0">
                            <CardTitle className="text-2xl font-bold" style={{ fontFamily: PUBLIC_DISPLAY_FONT }}>
                                Plan Pro
                            </CardTitle>
                            <CardDescription className="text-slate-300">Para negocios en expansión.</CardDescription>
                        </CardHeader>
                        <CardContent className="p-10 pt-6">
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
                            <ul className="flex flex-col gap-4 text-sm">
                                {[
                                    `Catálogo ilimitado o hasta ${proLimit} items`,
                                    'Reportes y exportaciones',
                                    'Marketing y cupones',
                                    'Branding del micrositio',
                                    'Operación multi-sede',
                                ].map((item) => (
                                    <li key={item} className="flex items-center gap-3">
                                        <CheckCircle2 className="h-4 w-4 text-white" aria-hidden="true" />
                                        {item}
                                    </li>
                                ))}
                            </ul>
                        </CardContent>

                        <CardFooter className="p-10 pt-0">
                            <Button asChild variant="secondary" className="h-auto w-full rounded-xl bg-white py-4 text-center font-bold text-slate-950 hover:bg-slate-100">
                                <Link href={route('tenant.register')}>
                                    Ver plan Pro
                                </Link>
                            </Button>
                        </CardFooter>
                    </Card>
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
                    <Card className="border-transparent bg-transparent shadow-none">
                        <CardContent className="flex flex-col gap-4 p-0">
                        <div className="flex gap-1 text-slate-950">
                            {[...Array(5)].map((_, index) => (
                                <Star key={index} className="h-5 w-5 fill-current" aria-hidden="true" />
                            ))}
                        </div>
                        <p className="text-lg italic font-medium leading-relaxed text-slate-900">
                            "Ahora tenemos pedidos, reservas y seguimiento comercial mucho más ordenados desde el mismo panel."
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
                        </CardContent>
                    </Card>

                    <Card className="flex flex-col items-center justify-center rounded-[1.9rem] bg-background p-10 text-center shadow-sm">
                        <CardTitle className="mb-2 text-6xl font-extrabold text-foreground" style={{ fontFamily: PUBLIC_DISPLAY_FONT }}>
                            {stats?.tenants || 0}+
                        </CardTitle>
                        <CardDescription className="text-sm font-bold uppercase tracking-[0.18em]" style={{ fontFamily: PUBLIC_LABEL_FONT }}>
                            Negocios visibles
                        </CardDescription>
                    </Card>

                    <Card className="flex flex-col items-center justify-center rounded-[1.9rem] bg-background p-10 text-center shadow-sm">
                        <CardTitle className="mb-2 text-6xl font-extrabold text-muted-foreground" style={{ fontFamily: PUBLIC_DISPLAY_FONT }}>
                            Clientes
                        </CardTitle>
                        <CardDescription className="text-sm font-bold uppercase tracking-[0.18em]" style={{ fontFamily: PUBLIC_LABEL_FONT }}>
                            Seguimiento comercial
                        </CardDescription>
                    </Card>
                </div>
            </div>
        </section>
    );
}

function FaqSection() {
    const faqs = [
        {
            title: '¿Cómo funciona la integración con WhatsApp?',
            body: 'Tus clientes navegan tu catálogo digital, agregan productos al carrito y al finalizar, el pedido llega estructurado directamente a tu número de WhatsApp Business, listo para ser procesado.',
        },
        {
            title: '¿Qué incluye hoy billing?',
            body: 'Hoy el panel cubre gestión de suscripción, reporte de pagos, seguimiento comercial y operaciones de cobro dentro del producto.',
        },
        {
            title: '¿Puedo usar mi propio dominio?',
            body: 'Puedes vincular tu dominio personalizado para mantener tu marca en la experiencia pública.',
        },
    ];

    return (
        <section id="faq" className="bg-slate-100/70 px-6 py-24">
            <div className="mx-auto max-w-3xl">
                <SectionTitle className="mb-12 text-center text-3xl">Preguntas frecuentes</SectionTitle>
                <div className="flex flex-col gap-4">
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
                    Opera con un panel claro y una presencia pública profesional.
                </h2>
                <p className="mx-auto mb-12 max-w-2xl text-xl text-slate-300">
                    Activa tu catalogo, reservas, seguimiento de clientes y micrositio sin promesas infladas ni superficies duplicadas.
                </p>
                <div className="flex flex-col justify-center gap-6 sm:flex-row">
                    <Button asChild variant="secondary" className="h-auto rounded-xl bg-white px-10 py-5 text-lg font-bold text-slate-950 shadow-2xl hover:bg-slate-100">
                        <Link href={route('tenant.register')}>
                            Empezar ahora gratis
                        </Link>
                    </Button>
                    <Button asChild variant="outline" className="h-auto rounded-xl border-white/20 bg-slate-950/20 px-10 py-5 text-lg font-bold text-white hover:bg-slate-800 hover:text-white">
                        <a href={`mailto:${PUBLIC_BRAND.supportEmail}?subject=Consulta%20comercial%20${encodeURIComponent(PUBLIC_BRAND.name)}`}>
                            Contactar por correo
                        </a>
                    </Button>
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
        name: PUBLIC_BRAND.name,
        url: homeUrl,
    };

    return (
        <PublicLayout>
            <Head title={`${PUBLIC_BRAND.name} | Gestion digital para negocios locales`}>
                <meta
                    name="description"
                    content="Centraliza catalogo, reservas, pedidos y presencia publica en una experiencia clara para negocios locales."
                />
                <meta property="og:title" content={`${PUBLIC_BRAND.name} | Gestion digital para negocios locales`} />
                <meta
                    property="og:description"
                    content="Centraliza catalogo, reservas, pedidos y presencia publica en una experiencia clara para negocios locales."
                />
                <meta property="og:type" content="website" />
                <link rel="canonical" href={homeUrl} />
                <script type="application/ld+json">{safeJsonLd(schema)}</script>
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
