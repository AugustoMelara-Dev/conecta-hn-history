import { Head, Link, useForm } from '@inertiajs/react';
import {
    ArrowRight,
    BarChart3,
    Bot,
    CalendarCheck,
    Check,
    ChevronRight,
    CreditCard,
    LayoutDashboard,
    MapPin,
    Megaphone,
    Search,
    ShieldCheck,
    ShoppingBag,
    Sparkles,
    Store,
    Users,
} from 'lucide-react';

import {
    Accordion,
    AccordionContent,
    AccordionItem,
    AccordionTrigger,
} from '@/Components/ui/accordion';
import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Separator } from '@/Components/ui/separator';
import PublicLayout from '@/Layouts/PublicLayout';

const highlights = [
    {
        icon: Store,
        title: 'Presencia publica',
        description: 'Micrositio, directorio, catalogo o servicios, contacto y ubicacion sin friccion.',
    },
    {
        icon: ShoppingBag,
        title: 'Ventas y reservas',
        description: 'El cliente compra, reserva o solicita una cotizacion desde la ruta correcta.',
    },
    {
        icon: LayoutDashboard,
        title: 'Control diario',
        description: 'Productos, reservas, clientes, campanas, reportes y cobros en un lugar privado para tu equipo.',
    },
];

const steps = [
    'Registra los datos minimos y elige si venderas productos, atenderas citas o recibiras solicitudes.',
    'Vito publica tu perfil, micrositio, datos de contacto y primer catalogo de forma ordenada.',
    'Completa catalogo, agenda, clientes y crecimiento desde el panel privado del negocio.',
];

const platformPillars = [
    {
        icon: ShoppingBag,
        title: 'Catalogo y pedidos',
        description: 'Para negocios que venden productos, menus, paquetes o inventario visible.',
    },
    {
        icon: CalendarCheck,
        title: 'Agenda y reservas',
        description: 'Para servicios con disponibilidad, equipo, confirmaciones y seguimiento.',
    },
    {
        icon: Megaphone,
        title: 'Leads consultivos',
        description: 'Para negocios que califican oportunidades, cotizan y convierten por CRM.',
    },
    {
        icon: Bot,
        title: 'IA publica y privada',
        description: 'Guia publica para clientes e IA privada solo para equipos autorizados.',
    },
];

const faqs = [
    {
        question: 'Puedo iniciar gratis?',
        answer: 'Si. Free incluye micrositio, presencia en directorio, contacto, WhatsApp, telefono, mapa y capacidad inicial limitada.',
    },
    {
        question: 'Que cambia al pasar a PRO?',
        answer: 'PRO amplia capacidad y puede activar reservas, campanas, reportes, CRM e IA privada cuando tu negocio lo tenga autorizado.',
    },
    {
        question: 'El panel operativo esta en esta pagina?',
        answer: 'No. Esta pagina muestra lo que puedes vender y operar. El trabajo diario vive en el panel privado de tu negocio.',
    },
];

function assetUrl(path) {
    if (!path) {
        return null;
    }

    if (path.startsWith('http') || path.startsWith('/')) {
        return path;
    }

    return `/storage/${path}`;
}

function sameOriginHref(href) {
    if (!href) {
        return href;
    }

    try {
        const url = new URL(href, window.location.origin);

        if (url.origin === window.location.origin || href.startsWith('http')) {
            return `${url.pathname}${url.search}${url.hash}`;
        }
    } catch {
        return href;
    }

    return href;
}

function appRoute(name, params, fallback = '/') {
    try {
        return sameOriginHref(route(name, params));
    } catch {
        return fallback;
    }
}

function tenantHref(tenant) {
    if (tenant?.url) {
        return tenant.url;
    }

    if (tenant?.microsite_url) {
        return tenant.microsite_url;
    }

    if (tenant?.slug) {
        return `/t/${encodeURIComponent(tenant.slug)}`;
    }

    return appRoute('directory.index', undefined, '/directorio');
}

function formatCount(value) {
    const number = Number(value ?? 0);

    if (!Number.isFinite(number)) {
        return '0';
    }

    return new Intl.NumberFormat('es-HN').format(number);
}

function Metric({ icon: Icon, label, value }) {
    return (
        <Card className="border-border/70 shadow-none">
            <CardContent className="flex items-center gap-4 p-4">
                <span className="flex size-10 items-center justify-center rounded-lg bg-secondary text-secondary-foreground">
                    <Icon className="size-5" aria-hidden="true" />
                </span>
                <div>
                    <p className="font-display text-2xl font-bold leading-none">{value}</p>
                    <p className="mt-1 text-sm text-muted-foreground">{label}</p>
                </div>
            </CardContent>
        </Card>
    );
}

function ProductPreview() {
    return (
        <Card className="overflow-hidden border-border/70 bg-background shadow-[0_24px_80px_rgba(15,23,42,0.10)]">
            <CardHeader className="border-b bg-muted/45">
                <div className="flex items-center justify-between gap-4">
                    <div>
                        <CardDescription>Panel operativo</CardDescription>
                        <CardTitle className="font-display text-xl">Resumen de negocio</CardTitle>
                    </div>
                    <Badge variant="outline">Privado</Badge>
                </div>
            </CardHeader>
            <CardContent className="grid gap-4 p-5">
                <div className="grid gap-3 sm:grid-cols-3">
                    {[
                        ['Pedidos', '128', '+14%'],
                        ['Reservas', '36', 'hoy'],
                        ['Leads', '47', 'CRM'],
                    ].map(([label, value, meta]) => (
                        <div key={label} className="rounded-xl border bg-card p-4">
                            <p className="text-xs font-semibold text-muted-foreground">{label}</p>
                            <p className="mt-2 font-display text-3xl font-bold">{value}</p>
                            <p className="mt-1 text-xs text-muted-foreground">{meta}</p>
                        </div>
                    ))}
                </div>

                <div className="rounded-xl border bg-card p-4">
                    <div className="flex items-center justify-between">
                        <p className="font-semibold">Flujo comercial</p>
                        <Badge className="bg-accent text-accent-foreground hover:bg-accent">Activo</Badge>
                    </div>
                    <div className="mt-4 grid gap-3">
                        {[
                            ['Directorio', 'clientes encuentran el negocio'],
                            ['Micrositio', 'catálogo, contacto y rutas públicas'],
                            ['Panel', 'operación interna y crecimiento'],
                        ].map(([title, body]) => (
                            <div key={title} className="flex items-center gap-3 rounded-lg bg-muted/50 p-3">
                                <span className="size-2 rounded-full bg-accent" />
                                <div>
                                    <p className="text-sm font-semibold">{title}</p>
                                    <p className="text-xs text-muted-foreground">{body}</p>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}

function HeroSection({ auth, canRegister, heroImage, stats }) {
    const { data, setData, get } = useForm({ search: '' });
    const panelUrl = auth?.panel_url || auth?.user?.panel_url || '/registrar-negocio';
    const tenantCount = formatCount(stats?.tenants ?? stats?.total_tenants);
    const cityCount = formatCount(stats?.cities ?? stats?.total_cities);

    const handleSearch = (event) => {
        event.preventDefault();

        get(appRoute('directory.index', undefined, '/directorio'), {
            preserveScroll: true,
        });
    };

    return (
        <section className="relative overflow-hidden border-b bg-[#050607] text-white">
            <div className="absolute inset-0 bg-[radial-gradient(circle_at_50%_15%,rgba(82,190,255,0.20),transparent_30%),radial-gradient(circle_at_18%_80%,rgba(255,255,255,0.10),transparent_26%),linear-gradient(180deg,#050607_0%,#0b0f14_68%,#f8fafc_100%)]" aria-hidden="true" />
            <div className="absolute inset-0 opacity-[0.18] [background-image:linear-gradient(rgba(255,255,255,0.12)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,0.10)_1px,transparent_1px)] [background-size:80px_80px]" aria-hidden="true" />
            <div className="absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-white/40 to-transparent" aria-hidden="true" />
            <div
                className="relative mx-auto grid max-w-7xl gap-10 px-4 pb-16 pt-16 sm:px-6 lg:grid-cols-[0.9fr_1.1fr] lg:px-8 lg:pb-24 lg:pt-24"
                data-testid="landing-page"
            >
                <div className="flex flex-col justify-center">
                    <Badge variant="secondary" className="mb-6 w-fit rounded-full border-white/10 bg-white/10 px-4 py-1.5 text-white hover:bg-white/10">
                        Para negocios que quieren vender y atender mejor
                    </Badge>

                    <h1 className="max-w-4xl font-display text-5xl font-bold leading-[0.92] tracking-normal text-white sm:text-7xl lg:text-8xl">
                        Tu negocio abierto, ordenado y vendiendo.
                    </h1>

                    <p className="mt-6 max-w-2xl text-lg leading-8 text-slate-300">
                        Publica tu perfil, recibe clientes, agenda reservas, vende productos y mira lo importante sin perder pedidos entre chats, hojas de calculo y notas sueltas.
                    </p>

                    <form
                        onSubmit={handleSearch}
                        className="mt-8 max-w-2xl"
                        role="search"
                        data-testid="landing-search"
                    >
                        <label htmlFor="landing-search-input" className="sr-only">
                            Buscar negocios en el directorio
                        </label>
                        <div className="flex flex-col gap-3 rounded-2xl border border-white/10 bg-white/10 p-2 shadow-[0_28px_90px_rgba(0,0,0,0.35)] backdrop-blur sm:flex-row">
                            <div className="relative flex-1">
                                <Search
                                    className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-500"
                                    aria-hidden="true"
                                />
                                <Input
                                    id="landing-search-input"
                                    value={data.search}
                                    onChange={(event) => setData('search', event.target.value)}
                                    placeholder="Busca como cliente: café, limpieza, abogado..."
                                    className="h-12 rounded-xl border-0 bg-white pl-9 text-slate-950 shadow-none focus-visible:ring-1"
                                />
                            </div>
                            <Button type="submit" className="h-12 rounded-xl bg-white text-slate-950 hover:bg-slate-200">
                                Buscar
                                <ArrowRight data-icon="inline-end" aria-hidden="true" />
                            </Button>
                        </div>
                    </form>

                    <div className="mt-8 flex flex-col gap-3 sm:flex-row">
                        {auth?.user ? (
                            <Button size="lg" className="rounded-xl bg-white text-slate-950 hover:bg-slate-200" asChild data-testid="landing-primary-cta">
                                <Link href={panelUrl}>
                                    Ir a mi panel
                                    <ArrowRight data-icon="inline-end" aria-hidden="true" />
                                </Link>
                            </Button>
                        ) : (
                            <>
                                {canRegister !== false && (
                                    <Button size="lg" className="rounded-xl bg-white text-slate-950 hover:bg-slate-200" asChild data-testid="landing-primary-cta">
                                        <Link href={appRoute('tenant.register', undefined, '/registrar-negocio')}>
                                            Registrar mi negocio
                                            <ArrowRight data-icon="inline-end" aria-hidden="true" />
                                        </Link>
                                    </Button>
                                )}
                                <Button size="lg" variant="outline" className="rounded-xl border-white/15 bg-white/5 text-white hover:bg-white/10 hover:text-white" asChild data-testid="landing-directory-cta">
                                    <Link href={appRoute('directory.index', undefined, '/directorio')}>Explorar como cliente</Link>
                                </Button>
                            </>
                        )}
                    </div>

                    <div className="mt-8 grid gap-3 sm:grid-cols-3 [&_.border-border\\/70]:border-white/10 [&_.bg-secondary]:bg-white/10 [&_.text-secondary-foreground]:text-white [&_.text-foreground]:text-white [&_.text-muted-foreground]:text-slate-400">
                        <Metric icon={Users} label="Negocios activos" value={tenantCount} />
                        <Metric icon={MapPin} label="Ciudades cubiertas" value={cityCount} />
                        <Metric icon={ShieldCheck} label="Datos protegidos" value="Privado" />
                    </div>
                </div>

                <div className="flex min-h-[440px] flex-col gap-4 lg:pt-8">
                    {heroImage ? (
                        <Card className="overflow-hidden border-white/10 bg-white/5 shadow-[0_34px_120px_rgba(0,0,0,0.45)] backdrop-blur">
                            <CardHeader className="border-b border-white/10">
                                <CardDescription className="text-slate-400">Panel real del negocio</CardDescription>
                                <CardTitle className="font-display text-xl text-white">Operacion diaria en una pantalla</CardTitle>
                            </CardHeader>
                            <CardContent className="p-0">
                                <img src={heroImage} alt="Vista del panel operativo de Vito" className="h-full w-full object-cover" />
                            </CardContent>
                        </Card>
                    ) : (
                        <ProductPreview />
                    )}

                    <div className="grid gap-3 sm:grid-cols-2">
                        <Card className="border-white/10 bg-white/10 text-white shadow-none backdrop-blur">
                            <CardHeader className="pb-3">
                                <CardTitle className="text-base">Free visible</CardTitle>
                                <CardDescription className="text-slate-300">Micrositio, directorio, contacto y capacidad inicial.</CardDescription>
                            </CardHeader>
                        </Card>
                        <Card className="border-white/10 bg-white/10 text-white shadow-none backdrop-blur">
                            <CardHeader className="pb-3">
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <CreditCard className="size-4" aria-hidden="true" />
                                    PRO interno
                                </CardTitle>
                                <CardDescription>Mas catalogo, CRM, campanas, reportes, reservas e IA sin exponer el plan al cliente.</CardDescription>
                            </CardHeader>
                        </Card>
                    </div>
                </div>
            </div>
        </section>
    );
}

function ValueSection() {
    return (
        <section id="beneficios" className="bg-background py-20">
            <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div className="max-w-2xl">
                    <Badge variant="outline" className="rounded-full">Como funciona</Badge>
                    <h2 className="mt-4 font-display text-4xl font-bold">Tres formas de vender, un solo lugar para operar</h2>
                    <p className="mt-4 text-base leading-7 text-muted-foreground">
                        Elige como trabajas: vender productos, recibir citas o calificar solicitudes. El rubro solo ajusta el contenido inicial.
                    </p>
                </div>

                <div className="mt-10 grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    {platformPillars.map((item) => (
                        <Card key={item.title} className="group border-border/70 shadow-none transition-colors hover:border-accent/50">
                            <CardHeader className="gap-4">
                                <span className="flex size-11 items-center justify-center rounded-xl bg-secondary text-secondary-foreground transition-colors group-hover:bg-accent group-hover:text-accent-foreground">
                                    <item.icon className="size-5" aria-hidden="true" />
                                </span>
                                <div>
                                    <CardTitle className="text-lg">{item.title}</CardTitle>
                                    <CardDescription className="mt-2 leading-6">
                                        {item.description}
                                    </CardDescription>
                                </div>
                            </CardHeader>
                        </Card>
                    ))}
                </div>
            </div>
        </section>
    );
}

function CategorySection({ categories }) {
    const visibleCategories = categories?.slice(0, 8) ?? [];

    return (
        <section className="border-y bg-muted/35 py-20">
            <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                    <div>
                        <Badge variant="outline" className="rounded-full">Directorio</Badge>
                        <h2 className="mt-4 font-display text-4xl font-bold">Exploración útil para clientes finales</h2>
                        <p className="mt-4 max-w-2xl text-base leading-7 text-muted-foreground">
                            Cada categoria lleva al directorio actual para mantener la misma experiencia de busqueda.
                        </p>
                    </div>
                    <Button variant="outline" asChild>
                        <Link href={appRoute('directory.index', undefined, '/directorio')}>
                            Ver todo
                            <ArrowRight data-icon="inline-end" aria-hidden="true" />
                        </Link>
                    </Button>
                </div>

                {visibleCategories.length ? (
                    <div className="mt-10 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        {visibleCategories.map((category) => (
                            <Card key={category.id ?? category.slug} className="border-border/70 bg-background shadow-none" data-testid="landing-category-card">
                                <CardContent className="p-0">
                                    <Button variant="ghost" className="h-auto w-full justify-between p-4" asChild>
                                        <Link href={appRoute('directory.index', { category: category.slug }, '/directorio')}>
                                            <span className="text-left">{category.name}</span>
                                            <ChevronRight data-icon="inline-end" aria-hidden="true" />
                                        </Link>
                                    </Button>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                ) : (
                    <Alert className="mt-8">
                        <AlertTitle>Directorio disponible</AlertTitle>
                        <AlertDescription>
                            Cuando haya categorias visibles, apareceran aqui. Mientras tanto, el directorio completo sigue disponible.
                        </AlertDescription>
                    </Alert>
                )}
            </div>
        </section>
    );
}

function TenantsSection({ tenants }) {
    const visibleTenants = tenants?.slice(0, 8) ?? [];

    return (
        <section className="py-20">
            <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                    <div>
                        <Badge variant="outline" className="rounded-full">Micrositios</Badge>
                        <h2 className="mt-4 font-display text-4xl font-bold">Negocios listos para recibir visitas</h2>
                        <p className="mt-4 max-w-2xl text-base leading-7 text-muted-foreground">
                            Cada tarjeta enlaza al micrositio publico del negocio y mantiene el directorio como superficie de usuario final.
                        </p>
                    </div>
                    <Button variant="outline" asChild>
                        <Link href={appRoute('directory.index', undefined, '/directorio')}>
                            Ver directorio
                            <ArrowRight data-icon="inline-end" aria-hidden="true" />
                        </Link>
                    </Button>
                </div>

                {visibleTenants.length ? (
                    <div className="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        {visibleTenants.map((tenant) => {
                            const logoUrl = assetUrl(tenant.logo_url ?? tenant.logo_path);
                            const categoryName = typeof tenant.category === 'string'
                                ? tenant.category
                                : tenant.category?.name;

                            return (
                                <Card key={tenant.id} className="overflow-hidden border-border/70 shadow-none transition-colors hover:border-accent/50" data-testid="landing-tenant-card">
                                    <Link href={tenantHref(tenant)} className="block">
                                        <div className="grid h-36 place-items-center bg-secondary/70">
                                            {logoUrl ? (
                                                <img
                                                    src={logoUrl}
                                                    alt={`Logo de ${tenant.name}`}
                                                    className="size-20 rounded-md object-cover"
                                                    loading="lazy"
                                                />
                                            ) : (
                                                <Store className="size-10 text-muted-foreground" aria-hidden="true" />
                                            )}
                                        </div>
                                        <CardHeader>
                                            <CardTitle className="truncate text-lg">{tenant.name}</CardTitle>
                                            <CardDescription>{categoryName ?? 'Comercio local'}</CardDescription>
                                        </CardHeader>
                                    </Link>
                                </Card>
                            );
                        })}
                    </div>
                ) : (
                    <Alert className="mt-8">
                        <AlertTitle>Negocios en preparacion</AlertTitle>
                        <AlertDescription>
                            Los nuevos negocios apareceran aqui cuando esten visibles. Puedes registrar el tuyo o explorar el directorio.
                        </AlertDescription>
                    </Alert>
                )}
            </div>
        </section>
    );
}

function ProcessSection() {
    return (
        <section className="border-y bg-muted/35 py-20">
            <div className="mx-auto grid max-w-7xl gap-8 px-4 sm:px-6 lg:grid-cols-[0.85fr_1.15fr] lg:px-8">
                <div>
                    <Badge variant="outline" className="rounded-full">Flujo</Badge>
                    <h2 className="mt-4 font-display text-4xl font-bold">Alta ligera, operación completa</h2>
                    <p className="mt-4 text-base leading-7 text-muted-foreground">
                        La experiencia separa claramente clientes finales, alta de negocios y operacion interna.
                    </p>
                </div>

                <Card className="border-border/70 bg-background shadow-none">
                    <CardContent className="p-6">
                        <ol className="grid gap-4">
                            {steps.map((step, index) => (
                                <li key={step} className="flex gap-4">
                                    <span className="flex size-8 shrink-0 items-center justify-center rounded-md bg-primary text-sm font-semibold text-primary-foreground">
                                        {index + 1}
                                    </span>
                                    <p className="pt-1 text-sm leading-6 text-muted-foreground">{step}</p>
                                </li>
                            ))}
                        </ol>
                    </CardContent>
                </Card>
            </div>
        </section>
    );
}

function PricingSection({ canRegister }) {
    return (
        <section id="planes" className="bg-background py-20" data-testid="landing-pricing">
            <div className="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                <div className="max-w-2xl">
                    <Badge variant="outline" className="rounded-full">Planes</Badge>
                    <h2 className="mt-4 font-display text-4xl font-bold">Empieza gratis, crece cuando necesites mas capacidad</h2>
                    <p className="mt-4 text-base leading-7 text-muted-foreground">
                        Free cubre presencia publica y contacto. PRO suma mas catalogo y herramientas operativas cuando tu negocio este listo.
                    </p>
                </div>

                <div className="mt-8 grid gap-4 md:grid-cols-[0.95fr_1.05fr]">
                    <Card className="border-border/70 shadow-none">
                        <CardHeader>
                            <CardTitle>Free</CardTitle>
                            <CardDescription>Para publicar tu negocio y empezar a recibir contactos.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <p className="text-4xl font-semibold">Gratis</p>
                            <Separator className="my-6" />
                            <ul className="flex flex-col gap-3 text-sm text-muted-foreground">
                                {[
                                    'Micrositio publico',
                                    'Presencia en directorio',
                                    'WhatsApp, telefono y mapa',
                                    'Capacidad inicial limitada',
                                ].map((item) => (
                                    <li key={item} className="flex items-center gap-2">
                                        <Check className="size-4 text-foreground" aria-hidden="true" />
                                        {item}
                                    </li>
                                ))}
                            </ul>
                            {canRegister !== false && (
                                <Button className="mt-6 w-full" asChild>
                                    <Link href={appRoute('tenant.register', undefined, '/registrar-negocio')}>Registrar negocio</Link>
                                </Button>
                            )}
                        </CardContent>
                    </Card>

                    <Card className="border-foreground bg-foreground text-background shadow-[0_24px_80px_rgba(15,23,42,0.16)]">
                        <CardHeader>
                            <Badge variant="secondary" className="w-fit">Para crecer</Badge>
                            <CardTitle>PRO</CardTitle>
                            <CardDescription className="text-background/70">Para negocios que necesitan más capacidad y herramientas de operación.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <p className="text-4xl font-semibold">L 399</p>
                            <p className="mt-1 text-sm text-background/70">por mes, según disponibilidad del plan</p>
                            <Separator className="my-6" />
                            <div className="grid gap-3 sm:grid-cols-2">
                                {[
                                    'Mas catalogo y servicios',
                                    'Reservas si tu modo lo requiere',
                                    'CRM y campanas con autorizacion',
                                    'Reportes operativos',
                                    'IA privada si esta autorizada',
                                ].map((item) => (
                                    <div key={item} className="flex items-center gap-2 text-sm text-background/90">
                                        <ShieldCheck className="size-4" aria-hidden="true" />
                                        {item}
                                    </div>
                                ))}
                            </div>
                            <Button className="mt-6 w-full bg-background text-foreground hover:bg-background/90" asChild>
                                <a href="#beneficios">Ver beneficios</a>
                            </Button>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </section>
    );
}

function FaqSection() {
    return (
        <section className="border-t bg-muted/35 py-20" data-testid="landing-faq">
            <div className="mx-auto grid max-w-7xl gap-8 px-4 sm:px-6 lg:grid-cols-[0.9fr_1.1fr] lg:px-8">
                <div>
                    <Badge variant="outline" className="rounded-full">Preguntas</Badge>
                    <h2 className="mt-4 font-display text-4xl font-bold">Preguntas frecuentes</h2>
                    <p className="mt-3 text-muted-foreground">
                        Lo importante antes de registrar tu negocio.
                    </p>
                </div>

                <Accordion type="single" collapsible className="rounded-2xl border bg-card px-4 shadow-none">
                    {faqs.map((item, index) => (
                        <AccordionItem key={item.question} value={`faq-${index}`}>
                            <AccordionTrigger>{item.question}</AccordionTrigger>
                            <AccordionContent className="text-muted-foreground">
                                {item.answer}
                            </AccordionContent>
                        </AccordionItem>
                    ))}
                </Accordion>
            </div>
        </section>
    );
}

function FinalCta({ auth, canRegister }) {
    const panelUrl = auth?.panel_url || auth?.user?.panel_url || '/registrar-negocio';
    const hasBusinessPanel = auth?.user && panelUrl !== '/registrar-negocio';

    return (
        <section className="border-t bg-foreground text-background">
            <div className="mx-auto flex max-w-7xl flex-col gap-6 px-4 py-12 sm:px-6 md:flex-row md:items-center md:justify-between lg:px-8">
                <div>
                    <Badge variant="secondary" className="mb-4 w-fit">
                        Listo para ordenar tu presencia digital
                    </Badge>
                    <h2 className="font-display text-4xl font-bold">Convierte visitas en clientes con una ruta clara.</h2>
                    <p className="mt-3 max-w-2xl text-sm opacity-80">
                        Publica tu negocio, manda clientes al micrositio y opera desde un panel privado.
                    </p>
                </div>
                {auth?.user ? (
                    <Button variant="secondary" size="lg" asChild>
                        <Link href={hasBusinessPanel ? panelUrl : appRoute('tenant.register', undefined, '/registrar-negocio')}>
                            {hasBusinessPanel ? 'Abrir panel negocio' : 'Crear negocio'}
                        </Link>
                    </Button>
                ) : canRegister !== false ? (
                    <Button variant="secondary" size="lg" asChild>
                        <Link href={appRoute('tenant.register', undefined, '/registrar-negocio')}>Registrar negocio</Link>
                    </Button>
                ) : null}
            </div>
        </section>
    );
}

export default function Welcome({
    auth = { user: null },
    categories = [],
    newTenants = [],
    featuredTenants = [],
    heroImage = null,
    stats = {},
    canRegister = true,
}) {
    const tenants = newTenants.length ? newTenants : featuredTenants;

    return (
        <PublicLayout>
            <Head title="Vito Business OS - Plataforma para negocios en Honduras" />

            <HeroSection
                auth={auth}
                canRegister={canRegister}
                heroImage={heroImage}
                stats={stats}
            />
            <ValueSection />
            <CategorySection categories={categories} />
            <TenantsSection tenants={tenants} />
            <ProcessSection />
            <PricingSection canRegister={canRegister} />
            <FaqSection />
            <FinalCta auth={auth} canRegister={canRegister} />
        </PublicLayout>
    );
}
