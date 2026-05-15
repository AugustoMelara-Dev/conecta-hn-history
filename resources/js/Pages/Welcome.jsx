import { Head, Link } from '@inertiajs/react';
import {
    ArrowRight,
    BarChart3,
    Bot,
    CalendarCheck,
    Check,
    LayoutDashboard,
    Megaphone,
    ShieldCheck,
    ShoppingBag,
    Store,
} from 'lucide-react';

import {
    Accordion,
    AccordionContent,
    AccordionItem,
    AccordionTrigger,
} from '@/Components/ui/accordion';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/Components/ui/card';
import { Separator } from '@/Components/ui/separator';
import PublicLayout from '@/Layouts/PublicLayout';

const operatingModes = [
    {
        icon: ShoppingBag,
        title: 'Vender productos',
        description: 'Catalogo, pedidos, WhatsApp, pagos manuales y visibilidad publica sin armar otra tienda desde cero.',
        starter: 'Starter Products',
    },
    {
        icon: CalendarCheck,
        title: 'Atender citas',
        description: 'Servicios, disponibilidad, reservas y seguimiento para negocios que trabajan por agenda.',
        starter: 'Starter Appointments',
    },
    {
        icon: Megaphone,
        title: 'Capturar leads',
        description: 'Landing consultiva, solicitudes, CRM y seguimiento para convertir conversaciones en oportunidades.',
        starter: 'Starter Consulting',
    },
];

const proof = [
    ['Menos caos', 'Pedidos, reservas y leads dejan de vivir solo en chats.'],
    ['Mas control', 'El dueno entra al panel del negocio, no a mirar competencia.'],
    ['Mejor salida', 'Free valida presencia; PRO habilita mas operacion con gates.'],
];

const faqs = [
    {
        question: 'El directorio sigue existiendo?',
        answer: 'Si. El directorio queda como superficie para clientes finales en /directorio y en el host configurado para esa app. La landing principal vende el producto al negocio.',
    },
    {
        question: 'Puedo empezar gratis?',
        answer: 'Si. Free permite publicar presencia, contacto y capacidad inicial. PRO aumenta catalogo, reservas, CRM, campanas, reportes e IA privada cuando aplique.',
    },
    {
        question: 'Donde opera el negocio despues del registro?',
        answer: 'El alta termina en el panel canonico /app/{tenant}, o en verificacion de correo/pago pendiente cuando el contrato lo requiere.',
    },
];

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

function HeroPanel() {
    return (
        <Card className="overflow-hidden border-white/10 bg-white/10 text-white shadow-[0_34px_120px_rgba(0,0,0,0.35)] backdrop-blur">
            <CardHeader className="border-b border-white/10">
                <div className="flex items-center justify-between gap-4">
                    <div>
                        <CardDescription className="text-slate-300">Panel operativo privado</CardDescription>
                        <CardTitle className="font-display text-xl text-white">Negocio bajo control</CardTitle>
                    </div>
                    <Badge variant="secondary">B2B</Badge>
                </div>
            </CardHeader>
            <CardContent className="grid gap-4 p-5">
                <div className="grid gap-3 sm:grid-cols-3">
                    {[
                        ['Pedidos', '128', 'catalogo'],
                        ['Reservas', '36', 'agenda'],
                        ['Leads', '47', 'CRM'],
                    ].map(([label, value, meta]) => (
                        <div key={label} className="rounded-lg border border-white/10 bg-white/10 p-4">
                            <p className="text-xs font-semibold text-slate-300">{label}</p>
                            <p className="mt-2 font-display text-3xl font-bold text-white">{value}</p>
                            <p className="mt-1 text-xs text-slate-300">{meta}</p>
                        </div>
                    ))}
                </div>

                <div className="rounded-lg border border-white/10 bg-white/[0.08] p-4">
                    <div className="flex items-center justify-between gap-4">
                        <p className="font-semibold">Flujo canonico</p>
                        <Badge className="bg-white text-slate-950 hover:bg-white">Activo</Badge>
                    </div>
                    <div className="mt-4 grid gap-3">
                        {[
                            ['Landing', 'vende el SaaS al negocio'],
                            ['Registro', 'crea cuenta, tenant y modo comercial'],
                            ['Panel', 'opera en /app/{tenant}, no en React legacy'],
                        ].map(([title, body]) => (
                            <div key={title} className="flex items-center gap-3 rounded-lg bg-white/10 p-3">
                                <span className="size-2 rounded-full bg-white" />
                                <div>
                                    <p className="text-sm font-semibold">{title}</p>
                                    <p className="text-xs text-slate-300">{body}</p>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}

function HeroSection({ auth, canRegister }) {
    const panelUrl = auth?.panel_url || auth?.user?.panel_url || '/registrar-negocio';

    return (
        <section className="relative overflow-hidden border-b bg-[#06070a] text-white">
            <div className="absolute inset-0 bg-[radial-gradient(circle_at_50%_15%,rgba(255,255,255,0.14),transparent_32%),linear-gradient(180deg,#06070a_0%,#111827_72%,#f8fafc_100%)]" aria-hidden="true" />
            <div className="absolute inset-0 opacity-[0.16] [background-image:linear-gradient(rgba(255,255,255,0.14)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,0.12)_1px,transparent_1px)] [background-size:84px_84px]" aria-hidden="true" />

            <div
                className="relative mx-auto grid max-w-7xl gap-10 px-4 pb-16 pt-16 sm:px-6 lg:grid-cols-[0.92fr_1.08fr] lg:px-8 lg:pb-24 lg:pt-24"
                data-testid="landing-page"
            >
                <div className="flex flex-col justify-center">
                    <Badge variant="secondary" className="mb-6 w-fit rounded-full border-white/10 bg-white/10 px-4 py-1.5 text-white hover:bg-white/10">
                        SaaS para operar negocios en Honduras
                    </Badge>

                    <h1 className="max-w-4xl font-display text-5xl font-bold leading-[0.94] tracking-normal text-white sm:text-7xl lg:text-8xl">
                        Vende, agenda y atiende desde un solo sistema.
                    </h1>

                    <p className="mt-6 max-w-2xl text-lg leading-8 text-slate-300">
                        Conecta HN convierte la presencia digital de un negocio en una operacion real: micrositio, catalogo, reservas, CRM, reportes y un panel privado para el equipo.
                    </p>

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
                                <Button size="lg" variant="outline" className="rounded-xl border-white/15 bg-white/5 text-white hover:bg-white/10 hover:text-white" asChild>
                                    <Link href={appRoute('login', undefined, '/login')}>Entrar al panel</Link>
                                </Button>
                            </>
                        )}
                    </div>

                    <div className="mt-8 grid gap-3 sm:grid-cols-3">
                        {proof.map(([title, body]) => (
                            <div key={title} className="rounded-lg border border-white/10 bg-white/10 p-4 backdrop-blur">
                                <p className="font-semibold text-white">{title}</p>
                                <p className="mt-2 text-sm leading-6 text-slate-300">{body}</p>
                            </div>
                        ))}
                    </div>
                </div>

                <div className="flex min-h-[440px] flex-col justify-center gap-4 lg:pt-8">
                    <HeroPanel />
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
                    <Badge variant="outline" className="rounded-full">Producto</Badge>
                    <h2 className="mt-4 font-display text-4xl font-bold">No es un catalogo bonito. Es el sistema comercial del negocio.</h2>
                    <p className="mt-4 text-base leading-7 text-muted-foreground">
                        La landing vende la plataforma; el directorio queda para clientes finales. El dueno entra directo al flujo que crea y opera su negocio.
                    </p>
                </div>

                <div className="mt-10 grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    {[
                        [Store, 'Presencia publica', 'Micrositio, contacto, mapa y ruta publica sin exponer datos privados.'],
                        [LayoutDashboard, 'Panel privado', 'Productos, reservas, clientes, campanas, billing y reportes viven en Filament.'],
                        [BarChart3, 'Conversion real', 'Cada modo comercial orienta el registro hacia lo que el negocio quiere vender.'],
                        [Bot, 'IA con gates', 'Guia publica y agente privado solo con permisos, allowlist y datos tenant-scoped.'],
                    ].map(([Icon, title, body]) => (
                        <Card key={title} className="border-border/70 shadow-none transition-colors hover:border-primary/40">
                            <CardHeader className="gap-4">
                                <span className="flex size-11 items-center justify-center rounded-lg bg-secondary text-secondary-foreground">
                                    <Icon aria-hidden="true" />
                                </span>
                                <div>
                                    <CardTitle className="text-lg">{title}</CardTitle>
                                    <CardDescription className="mt-2 leading-6">{body}</CardDescription>
                                </div>
                            </CardHeader>
                        </Card>
                    ))}
                </div>
            </div>
        </section>
    );
}

function ModesSection({ canRegister }) {
    return (
        <section className="border-y bg-muted/35 py-20">
            <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div className="flex flex-col justify-between gap-4 md:flex-row md:items-end">
                    <div className="max-w-2xl">
                        <Badge variant="outline" className="rounded-full">Modos comerciales</Badge>
                        <h2 className="mt-4 font-display text-4xl font-bold">El registro empieza por como gana dinero el negocio.</h2>
                        <p className="mt-4 text-base leading-7 text-muted-foreground">
                            El rubro ayuda al SEO; el modo comercial define el flujo operativo inicial.
                        </p>
                    </div>
                    {canRegister !== false ? (
                        <Button asChild>
                            <Link href={appRoute('tenant.register', undefined, '/registrar-negocio')}>
                                Empezar alta
                                <ArrowRight data-icon="inline-end" aria-hidden="true" />
                            </Link>
                        </Button>
                    ) : null}
                </div>

                <div className="mt-10 grid gap-4 lg:grid-cols-3">
                    {operatingModes.map((item) => (
                        <Card key={item.title} className="border-border/70 bg-background shadow-none">
                            <CardHeader className="gap-4">
                                <div className="flex items-center justify-between gap-4">
                                    <span className="flex size-11 items-center justify-center rounded-lg bg-primary text-primary-foreground">
                                        <item.icon aria-hidden="true" />
                                    </span>
                                    <Badge variant="secondary">{item.starter}</Badge>
                                </div>
                                <div>
                                    <CardTitle>{item.title}</CardTitle>
                                    <CardDescription className="mt-2 leading-6">{item.description}</CardDescription>
                                </div>
                            </CardHeader>
                        </Card>
                    ))}
                </div>
            </div>
        </section>
    );
}

function ProcessSection() {
    return (
        <section className="bg-background py-20">
            <div className="mx-auto grid max-w-7xl gap-8 px-4 sm:px-6 lg:grid-cols-[0.85fr_1.15fr] lg:px-8">
                <div>
                    <Badge variant="outline" className="rounded-full">Flujo</Badge>
                    <h2 className="mt-4 font-display text-4xl font-bold">Una ruta clara desde landing hasta panel.</h2>
                    <p className="mt-4 text-base leading-7 text-muted-foreground">
                        La experiencia separa ventas B2B, usuarios finales y operacion privada para evitar superficies duplicadas.
                    </p>
                </div>

                <Card className="border-border/70 bg-background shadow-none">
                    <CardContent className="p-6">
                        <ol className="grid gap-4">
                            {[
                                ['Landing SaaS', 'El negocio entiende el producto y empieza en /registrar-negocio.'],
                                ['Alta corta', 'Se captura lo minimo para crear tenant, owner y modo comercial.'],
                                ['Panel Filament', 'La operacion diaria continua en /app/{tenant}.'],
                                ['Directorio separado', 'Clientes finales exploran en /directorio o el host configurado.'],
                            ].map(([title, body], index) => (
                                <li key={title} className="flex gap-4">
                                    <span className="flex size-8 shrink-0 items-center justify-center rounded-md bg-primary text-sm font-semibold text-primary-foreground">
                                        {index + 1}
                                    </span>
                                    <div>
                                        <p className="font-semibold">{title}</p>
                                        <p className="mt-1 text-sm leading-6 text-muted-foreground">{body}</p>
                                    </div>
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
        <section id="planes" className="border-y bg-muted/35 py-20" data-testid="landing-pricing">
            <div className="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                <div className="max-w-2xl">
                    <Badge variant="outline" className="rounded-full">Planes</Badge>
                    <h2 className="mt-4 font-display text-4xl font-bold">Empieza gratis, crece con gates reales.</h2>
                    <p className="mt-4 text-base leading-7 text-muted-foreground">
                        Free valida presencia y contacto. PRO suma capacidad y operacion cuando el negocio, el plan y las capacidades lo permiten.
                    </p>
                </div>

                <div className="mt-8 grid gap-4 md:grid-cols-[0.95fr_1.05fr]">
                    <Card className="border-border/70 bg-background shadow-none">
                        <CardHeader>
                            <CardTitle>Free</CardTitle>
                            <CardDescription>Para publicar el negocio y empezar a recibir contactos.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <p className="text-4xl font-semibold">Gratis</p>
                            <Separator className="my-6" />
                            <ul className="flex flex-col gap-3 text-sm text-muted-foreground">
                                {[
                                    'Micrositio publico',
                                    'Contacto, WhatsApp, telefono y mapa',
                                    'Capacidad inicial limitada',
                                    'Directorio separado para clientes finales',
                                ].map((item) => (
                                    <li key={item} className="flex items-center gap-2">
                                        <Check aria-hidden="true" />
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
                            <CardDescription className="text-background/70">Para negocios que necesitan mas capacidad y herramientas internas.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <p className="text-4xl font-semibold">Disponible por solicitud</p>
                            <p className="mt-1 text-sm text-background/70">Activacion segun modo comercial, capacidad real y configuracion vigente.</p>
                            <Separator className="my-6" />
                            <div className="grid gap-3 sm:grid-cols-2">
                                {[
                                    'Mas catalogo y servicios',
                                    'Reservas si el modo lo requiere',
                                    'CRM y campanas con autorizacion',
                                    'Reportes operativos',
                                    'IA privada por allowlist',
                                ].map((item) => (
                                    <div key={item} className="flex items-center gap-2 text-sm text-background/90">
                                        <ShieldCheck aria-hidden="true" />
                                        {item}
                                    </div>
                                ))}
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </section>
    );
}

function DirectoryBoundarySection() {
    return (
        <section className="bg-background py-16">
            <div className="mx-auto flex max-w-7xl flex-col gap-6 px-4 sm:px-6 md:flex-row md:items-center md:justify-between lg:px-8">
                <div className="max-w-2xl">
                    <Badge variant="outline" className="rounded-full">App de clientes finales</Badge>
                    <h2 className="mt-4 font-display text-3xl font-bold">El directorio existe, pero no compite con la venta del SaaS.</h2>
                    <p className="mt-3 text-sm leading-6 text-muted-foreground">
                        Los clientes finales pueden explorar negocios en la superficie B2C. El dueno del negocio se queda en el flujo de registro y panel.
                    </p>
                </div>
                <Button variant="outline" asChild data-testid="landing-directory-cta">
                    <Link href={appRoute('directory.index', undefined, '/directorio')}>Abrir directorio B2C</Link>
                </Button>
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
                    <p className="mt-3 text-muted-foreground">Lo importante antes de registrar tu negocio.</p>
                </div>

                <Accordion type="single" collapsible className="rounded-lg border bg-card px-4 shadow-none">
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
                        Listo para operar mejor
                    </Badge>
                    <h2 className="font-display text-4xl font-bold">Registra el negocio y entra al panel correcto.</h2>
                    <p className="mt-3 max-w-2xl text-sm opacity-80">
                        Nada de dashboards duplicados ni rutas confusas: landing, alta, panel privado y directorio B2C separados.
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
    canRegister = true,
}) {
    return (
        <PublicLayout>
            <Head title="Conecta HN - Plataforma SaaS para negocios en Honduras" />

            <HeroSection auth={auth} canRegister={canRegister} />
            <ValueSection />
            <ModesSection canRegister={canRegister} />
            <ProcessSection />
            <PricingSection canRegister={canRegister} />
            <DirectoryBoundarySection />
            <FaqSection />
            <FinalCta auth={auth} canRegister={canRegister} />
        </PublicLayout>
    );
}
