/**
 * Tenant/Dashboard.jsx - Command Center
 *
 * FEATURES:
 * - Onboarding progress widget
 * - Quick action shortcuts
 * - Live order feed
 * - Today's stats summary
 *
 * PROPS (from DashboardController@index):
 * @param {Object} props.tenant - Full tenant object
 * @param {Object} props.onboarding - { progress, steps: [] }
 * @param {Object} props.stats - { today_revenue, today_orders, pending_orders }
 * @param {Array} props.latestOrders - Last 5 orders
 */

import { Head, Link } from '@inertiajs/react';
import TenantLayout from '@/Layouts/TenantLayout';
import OnboardingWidget from '@/Components/Dashboard/OnboardingWidget';
import StatCard from '@/Components/Stats/StatCard';

// ═══════════════════════════════════════════════════════════════════════════
// FORMATTERS
// ═══════════════════════════════════════════════════════════════════════════
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

// ═══════════════════════════════════════════════════════════════════════════
// QUICK ACTIONS GRID
// ═══════════════════════════════════════════════════════════════════════════
function QuickActions() {
    const actions = [
        {
            label: 'Nueva Orden',
            href: route('tenant.orders.index'),
            icon: (
                <svg className="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5}
                        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                </svg>
            ),
            color: 'bg-blue-50 text-blue-600 hover:bg-blue-100',
        },
        {
            label: 'Agregar Producto',
            href: route('tenant.products.create'),
            icon: (
                <svg className="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5}
                        d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                </svg>
            ),
            color: 'bg-emerald-50 text-emerald-600 hover:bg-emerald-100',
        },
        {
            label: 'Crear Promo',
            href: route('tenant.marketing.index'),
            icon: (
                <svg className="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5}
                        d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                </svg>
            ),
            color: 'bg-purple-50 text-purple-600 hover:bg-purple-100',
        },
        {
            label: 'Ver Reportes',
            href: route('tenant.reports.index'),
            icon: (
                <svg className="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5}
                        d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                </svg>
            ),
            color: 'bg-amber-50 text-amber-600 hover:bg-amber-100',
        },
    ];

    return (
        <div className="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-8">
            {actions.map((action) => (
                <Link
                    key={action.label}
                    href={action.href}
                    className={`flex flex-col items-center justify-center p-6 rounded-xl transition-all ${action.color}`}
                >
                    {action.icon}
                    <span className="mt-2 text-sm font-medium">{action.label}</span>
                </Link>
            ))}
        </div>
    );
}

// ═══════════════════════════════════════════════════════════════════════════
// LIVE ORDER FEED
// ═══════════════════════════════════════════════════════════════════════════
function LiveOrderFeed({ orders }) {
    const statusColors = {
        pending: 'bg-yellow-100 text-yellow-800',
        processing: 'bg-blue-100 text-blue-800',
        completed: 'bg-green-100 text-green-800',
        cancelled: 'bg-red-100 text-red-800',
    };

    const statusLabels = {
        pending: 'Pendiente',
        processing: 'En proceso',
        completed: 'Completado',
        cancelled: 'Cancelado',
    };

    if (!orders || orders.length === 0) {
        return (
            <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-8 text-center">
                <div className="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg className="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5}
                            d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                    </svg>
                </div>
                <p className="text-gray-500">No hay órdenes recientes</p>
            </div>
        );
    }

    return (
        <div className="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div className="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                <h3 className="font-bold text-gray-900">📋 Últimas Órdenes</h3>
                <Link
                    href={route('tenant.orders.index')}
                    className="text-sm text-blue-600 hover:text-blue-700 font-medium"
                >
                    Ver todas →
                </Link>
            </div>
            <div className="divide-y divide-gray-100">
                {orders.map((order) => (
                    <div key={order.id} className="px-6 py-4 flex items-center justify-between hover:bg-gray-50">
                        <div className="flex items-center gap-4">
                            <div className="w-10 h-10 bg-gray-100 rounded-full flex items-center justify-center text-gray-600 font-bold">
                                #{order.id}
                            </div>
                            <div>
                                <p className="font-medium text-gray-900">
                                    {order.customer_name || 'Cliente'}
                                </p>
                                <p className="text-sm text-gray-500">
                                    {order.items_count} items • {timeFormatter.format(new Date(order.created_at))}
                                </p>
                            </div>
                        </div>
                        <div className="flex items-center gap-4">
                            <span className="font-bold text-gray-900">
                                {currencyFormatter.format(order.total)}
                            </span>
                            <span className={`px-2.5 py-1 text-xs font-bold rounded-full ${statusColors[order.status]}`}>
                                {statusLabels[order.status]}
                            </span>
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}

// ═══════════════════════════════════════════════════════════════════════════
// MAIN PAGE COMPONENT
// ═══════════════════════════════════════════════════════════════════════════
export default function Dashboard({ tenant, onboarding = {}, stats = {}, latestOrders = [] }) {
    return (
        <TenantLayout>
            <Head title="Dashboard" />

            <div className="min-h-screen bg-gray-50">
                {/* Header */}
                <header className="bg-white border-b border-gray-200">
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        <div className="flex items-center justify-between h-16">
                            <div>
                                <h1 className="text-xl font-bold text-gray-900">
                                    👋 Hola, {tenant?.name || 'Usuario'}
                                </h1>
                                <p className="text-sm text-gray-500">
                                    Aquí está el resumen de tu negocio hoy
                                </p>
                            </div>
                            {tenant?.is_pro && (
                                <span className="px-3 py-1 bg-emerald-100 text-emerald-700 text-sm font-bold rounded-full">
                                    PRO
                                </span>
                            )}
                        </div>
                    </div>
                </header>

                <main className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                    {/* Onboarding Widget */}
                    <OnboardingWidget
                        progress={onboarding.progress || 0}
                        steps={onboarding.steps || []}
                    />

                    {/* Quick Actions */}
                    <QuickActions />

                    {/* Stats Row */}
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                        <StatCard
                            title="Ventas de Hoy"
                            value={currencyFormatter.format(stats.today_revenue || 0)}
                            trend={stats.revenue_trend}
                            trendLabel="vs ayer"
                            icon={
                                <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
                                        d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            }
                        />
                        <StatCard
                            title="Órdenes de Hoy"
                            value={stats.today_orders || 0}
                            trend={stats.orders_trend}
                            trendLabel="vs ayer"
                            icon={
                                <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
                                        d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                                </svg>
                            }
                        />
                        <StatCard
                            title="Pendientes"
                            value={stats.pending_orders || 0}
                            icon={
                                <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
                                        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            }
                        />
                    </div>

                    {/* Live Order Feed */}
                    <LiveOrderFeed orders={latestOrders} />
                </main>
            </div>
        </TenantLayout>
    );
}
