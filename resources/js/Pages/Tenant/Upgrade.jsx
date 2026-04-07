import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    BadgeCheck,
    CreditCard,
    Layers3,
    ShieldCheck,
    Sparkles,
} from 'lucide-react';
import TenantLayout from '@/Layouts/TenantLayout';

const currencyFormatter = new Intl.NumberFormat('es-HN', {
    style: 'currency',
    currency: 'HNL',
    minimumFractionDigits: 0,
});

function formatPrice(plan) {
    return currencyFormatter.format(Number(plan?.price ?? 0));
}

function FeaturePill({ children, tone = 'light' }) {
    const tones = {
        light: 'border-slate-200 bg-white text-slate-700',
        dark: 'border-white/10 bg-white/10 text-white',
    };

    return (
        <span className={`inline-flex items-center rounded-full border px-3 py-1 text-xs font-semibold ${tones[tone]}`}>
            {children}
        </span>
    );
}

function PlanCard({ plan, featured = false }) {
    const features = Array.isArray(plan?.features) ? plan.features : [];

    return (
        <article
            className={`rounded-[28px] border p-8 shadow-sm ${
                featured
                    ? 'border-slate-950 bg-slate-950 text-white shadow-slate-950/15'
                    : 'border-slate-200 bg-white text-slate-950'
            }`}
        >
            <div className="flex items-start justify-between gap-4">
                <div>
                    <h2 className="text-2xl font-semibold">{plan?.name ?? 'Plan'}</h2>
                    <p className={`mt-2 text-sm leading-6 ${featured ? 'text-slate-300' : 'text-slate-600'}`}>
                        {plan?.description ?? 'Configuracion disponible para tu negocio.'}
                    </p>
                </div>
                {featured ? <FeaturePill tone="dark">Recomendado</FeaturePill> : null}
            </div>

            <div className="mt-8 flex items-end gap-2">
                <span className="text-4xl font-semibold tracking-tight">{formatPrice(plan)}</span>
                <span className={`pb-1 text-sm ${featured ? 'text-slate-300' : 'text-slate-500'}`}>/mes</span>
            </div>

            <ul className="mt-8 space-y-3">
                {features.length ? (
                    features.map((feature) => (
                        <li key={feature} className="flex items-start gap-3 text-sm leading-6">
                            <BadgeCheck className={`mt-0.5 h-4 w-4 flex-shrink-0 ${featured ? 'text-emerald-300' : 'text-emerald-600'}`} aria-hidden="true" />
                            <span>{feature}</span>
                        </li>
                    ))
                ) : (
                    <li className={`text-sm ${featured ? 'text-slate-300' : 'text-slate-500'}`}>
                        Los detalles del plan se administran desde la configuracion del panel.
                    </li>
                )}
            </ul>
        </article>
    );
}

export default function Upgrade({ plans = {} }) {
    const { tenant } = usePage().props;

    const freePlan = plans?.free ?? {
        name: 'Inicial',
        description: 'Para empezar a publicar y ordenar la operacion del negocio.',
        price: 0,
        features: ['Micrositio publico', 'Catalogo base', 'Operacion centralizada'],
    };

    const proPlan = plans?.pro ?? {
        name: 'Pro',
        description: 'Para negocios que ya necesitan mas visibilidad, herramientas y control.',
        price: 499,
        features: ['Mas capacidad operativa', 'Herramientas comerciales', 'Facturacion y upgrades desde /app'],
    };

    const officialBillingHref = tenant?.slug
        ? route('filament.app.pages.manage-billing', { tenant: tenant.slug })
        : route('dashboard');

    return (
        <TenantLayout>
            <Head title="Planes y Billing" />

            <div className="mx-auto max-w-6xl space-y-8">
                <section className="overflow-hidden rounded-[36px] bg-[radial-gradient(circle_at_top_left,rgba(255,255,255,0.12),transparent_24%),linear-gradient(135deg,#020617,#0f172a_55%,#1d4ed8)] px-8 py-10 text-white shadow-2xl shadow-slate-950/15 sm:px-10 sm:py-12">
                    <FeaturePill tone="dark">
                        <Sparkles className="mr-2 h-3.5 w-3.5" aria-hidden="true" />
                        Billing centralizado
                    </FeaturePill>

                    <div className="mt-6 grid gap-8 lg:grid-cols-[1.2fr,0.8fr]">
                        <div>
                            <h1 className="max-w-3xl text-4xl font-semibold tracking-tight text-balance sm:text-5xl">
                                Gestiona upgrades y pagos desde el panel oficial.
                            </h1>
                            <p className="mt-4 max-w-2xl text-base leading-7 text-slate-300 sm:text-lg">
                                La vista legacy de workspace ya no procesa cobros directamente. El flujo activo de
                                billing vive en <strong>/app</strong>, donde se concentran suscripcion, historial de
                                pagos y siguientes acciones.
                            </p>

                            <div className="mt-8 flex flex-col gap-4 sm:flex-row">
                                <Link
                                    href={officialBillingHref}
                                    className="inline-flex items-center justify-center gap-2 rounded-2xl bg-white px-6 py-4 text-sm font-semibold text-slate-950 transition-colors hover:bg-slate-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-slate-950"
                                >
                                    Abrir Billing en /app
                                    <ArrowRight className="h-4 w-4" aria-hidden="true" />
                                </Link>

                                <Link
                                    href={route('filament.app.pages.dashboard', { tenant: tenant?.slug ?? '' })}
                                    className="inline-flex items-center justify-center gap-2 rounded-2xl border border-white/15 bg-white/5 px-6 py-4 text-sm font-semibold text-white transition-colors hover:border-white/30 hover:bg-white/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-slate-950"
                                >
                                    Volver al Panel
                                </Link>
                            </div>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-3 lg:grid-cols-1">
                            <div className="rounded-[24px] border border-white/10 bg-white/5 p-5">
                                <CreditCard className="h-5 w-5 text-sky-300" aria-hidden="true" />
                                <h2 className="mt-4 text-lg font-semibold">Pagos y Evidencias</h2>
                                <p className="mt-2 text-sm leading-6 text-slate-300">
                                    El panel oficial concentra reportes de pago, estados y seguimiento del cobro.
                                </p>
                            </div>

                            <div className="rounded-[24px] border border-white/10 bg-white/5 p-5">
                                <Layers3 className="h-5 w-5 text-violet-300" aria-hidden="true" />
                                <h2 className="mt-4 text-lg font-semibold">Planes Sin Duplicar UI</h2>
                                <p className="mt-2 text-sm leading-6 text-slate-300">
                                    Evitamos mantener una segunda experiencia de checkout en workspace que ya no es la fuente real.
                                </p>
                            </div>

                            <div className="rounded-[24px] border border-white/10 bg-white/5 p-5">
                                <ShieldCheck className="h-5 w-5 text-emerald-300" aria-hidden="true" />
                                <h2 className="mt-4 text-lg font-semibold">Estado Mas Claro</h2>
                                <p className="mt-2 text-sm leading-6 text-slate-300">
                                    Los usuarios ven una sola ruta vigente para billing, reduciendo confusiones y soporte manual.
                                </p>
                            </div>
                        </div>
                    </div>
                </section>

                <section className="grid gap-6 lg:grid-cols-2">
                    <PlanCard plan={freePlan} />
                    <PlanCard plan={proPlan} featured />
                </section>
            </div>
        </TenantLayout>
    );
}
