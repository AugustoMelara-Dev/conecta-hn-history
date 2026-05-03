import { Head, Link } from '@inertiajs/react';
import { Package, Plus, ReceiptText, ShoppingBag, TrendingUp } from 'lucide-react';

import { EmptyState } from '@/Components/Feedback/UXStates';
import StatCard from '@/Components/Stats/StatCard';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import TenantLayout from '@/Layouts/TenantLayout';

const currencyFormatter = new Intl.NumberFormat('es-HN', {
    style: 'currency',
    currency: 'HNL',
    minimumFractionDigits: 0,
});

const timeFormatter = new Intl.DateTimeFormat('es-HN', {
    hour: '2-digit',
    minute: '2-digit',
    hour12: true,
});

function normalizeNumber(value) {
    return Number.isFinite(Number(value)) ? Number(value) : 0;
}

export default function Dashboard({ tenant, onboarding = {}, stats = {}, latestOrders = [] }) {
    const todayRevenue = normalizeNumber(stats.today_revenue);
    const todayOrders = normalizeNumber(stats.today_orders);
    const pendingOrders = normalizeNumber(stats.pending_orders);
    const hasOperationalData = todayRevenue > 0 || todayOrders > 0 || pendingOrders > 0 || latestOrders.length > 0;

    return (
        <TenantLayout>
            <Head title="Dashboard" />

            <div className="space-y-6">
                <header className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h1 className="text-2xl font-bold text-slate-900">
                                Hola, {tenant?.name ?? 'tu negocio'}
                            </h1>
                            <p className="mt-1 text-sm text-slate-600">
                                Este panel resume operaciones reales del día y te deja avanzar con acciones clave.
                            </p>
                        </div>
                        <div className="flex gap-2">
                            <Button asChild>
                                <Link href={route('tenant.orders.index')}>Ver pedidos</Link>
                            </Button>
                            <Button variant="outline" asChild>
                                <Link href={route('tenant.products.create')}>Crear producto</Link>
                            </Button>
                        </div>
                    </div>
                </header>

                <section aria-label="Onboarding">
                    <Card>
                        <CardHeader>
                            <CardTitle>Progreso de activación</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {Array.isArray(onboarding.steps) && onboarding.steps.length > 0 ? (
                                <div className="space-y-3">
                                    <p className="text-sm text-slate-600">
                                        Completado: {normalizeNumber(onboarding.progress)}%
                                    </p>
                                    <ul className="space-y-2">
                                        {onboarding.steps.map((step, index) => (
                                            <li key={`${step?.key ?? 'step'}-${index}`} className="rounded-lg border border-slate-200 p-3 text-sm">
                                                <p className="font-medium text-slate-900">{step?.title ?? 'Paso de activación'}</p>
                                                <p className="text-slate-600">{step?.description ?? 'Completa este paso para avanzar.'}</p>
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            ) : (
                                <EmptyState
                                    icon={Package}
                                    eyebrow="Activación"
                                    title="No hay hitos de activación pendientes"
                                    description="Tu negocio no reporta pasos abiertos de onboarding en este momento."
                                />
                            )}
                        </CardContent>
                    </Card>
                </section>

                <section className="grid grid-cols-1 gap-6 md:grid-cols-3" aria-label="Métricas">
                    <StatCard
                        title="Ventas de hoy"
                        value={currencyFormatter.format(todayRevenue)}
                        trend={stats.revenue_trend}
                        trendLabel="vs ayer"
                        icon={<TrendingUp className="h-6 w-6" aria-hidden="true" />}
                    />
                    <StatCard
                        title="Órdenes de hoy"
                        value={todayOrders}
                        trend={stats.orders_trend}
                        trendLabel="vs ayer"
                        icon={<ShoppingBag className="h-6 w-6" aria-hidden="true" />}
                    />
                    <StatCard
                        title="Pendientes"
                        value={pendingOrders}
                        icon={<ReceiptText className="h-6 w-6" aria-hidden="true" />}
                    />
                </section>

                <section aria-label="Órdenes recientes">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between">
                            <CardTitle>Órdenes recientes</CardTitle>
                            <Button variant="ghost" asChild>
                                <Link href={route('tenant.orders.index')}>Abrir lista</Link>
                            </Button>
                        </CardHeader>
                        <CardContent>
                            {latestOrders.length > 0 ? (
                                <div className="space-y-3">
                                    {latestOrders.map((order) => (
                                        <div key={order.id} className="flex flex-col gap-2 rounded-lg border border-slate-200 p-3 sm:flex-row sm:items-center sm:justify-between">
                                            <div>
                                                <p className="font-semibold text-slate-900">#{order.id} · {order.customer_name || 'Cliente'}</p>
                                                <p className="text-sm text-slate-600">
                                                    {normalizeNumber(order.items_count)} items · {timeFormatter.format(new Date(order.created_at))}
                                                </p>
                                            </div>
                                            <p className="text-sm font-semibold text-slate-900">
                                                {currencyFormatter.format(normalizeNumber(order.total))}
                                            </p>
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <EmptyState
                                    icon={ReceiptText}
                                    eyebrow="Pedidos"
                                    title="Aún no hay órdenes recientes"
                                    description="Cuando entren pedidos, aparecerán aquí con su monto y hora."
                                    actionLabel="Crear primer producto"
                                    actionHref={route('tenant.products.create')}
                                />
                            )}
                        </CardContent>
                    </Card>
                </section>

                {!hasOperationalData ? (
                    <section aria-label="Siguiente paso">
                        <Card>
                            <CardContent className="pt-6">
                                <EmptyState
                                    icon={Plus}
                                    eyebrow="Siguiente paso"
                                    title="Tu panel está listo, falta actividad real"
                                    description="Publica productos y abre pedidos para que este dashboard refleje operación en vivo."
                                    actionLabel="Ir a productos"
                                    actionHref={route('tenant.products.index')}
                                />
                            </CardContent>
                        </Card>
                    </section>
                ) : null}
            </div>
        </TenantLayout>
    );
}
