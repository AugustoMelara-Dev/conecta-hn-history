/**
 * Tenant/Reports/Index.jsx - Business Intelligence Dashboard
 *
 * FEATURES:
 * - DateRangePicker as unified filter source
 * - Stat cards with trend comparison
 * - CSS-based bar chart for daily sales
 * - Export zone with PDF download buttons
 *
 * PROPS (from ReportController@index):
 * @param {Object} props.stats - { revenue, orders, average_ticket, trends }
 * @param {Array} props.dailySales - [{ date, total }, ...]
 * @param {Object} props.filters - { start_date, end_date }
 * @param {Object} props.tenant - { id, name, slug, is_pro }
 */

import { useState, useEffect } from 'react';
import { Head, router } from '@inertiajs/react';
import TenantLayout from '@/Layouts/TenantLayout';
import StatCard from '@/Components/Stats/StatCard';
import { DownloadSalesReportButton } from '@/Components/Reports/ReportActions';

// ═══════════════════════════════════════════════════════════════════════════
// FORMATTERS
// ═══════════════════════════════════════════════════════════════════════════
const currencyFormatter = new Intl.NumberFormat('es-HN', {
    style: 'currency',
    currency: 'HNL',
    minimumFractionDigits: 0,
});

const dateFormatter = new Intl.DateTimeFormat('es-HN', {
    day: '2-digit',
    month: 'short',
});

// ═══════════════════════════════════════════════════════════════════════════
// BAR CHART COMPONENT (Pure CSS)
// ═══════════════════════════════════════════════════════════════════════════
function DailySalesChart({ data }) {
    if (!data || data.length === 0) {
        return (
            <div className="h-64 flex items-center justify-center text-gray-400">
                No hay datos para mostrar
            </div>
        );
    }

    const maxValue = Math.max(...data.map(d => d.total));

    return (
        <div className="h-64 flex items-end gap-2">
            {data.map((day, index) => {
                const height = maxValue > 0 ? (day.total / maxValue) * 100 : 0;
                return (
                    <div
                        key={index}
                        className="flex-1 flex flex-col items-center gap-2"
                    >
                        {/* Bar */}
                        <div
                            className="w-full bg-gradient-to-t from-blue-600 to-blue-400 rounded-t-lg transition-all duration-500 hover:from-blue-700 hover:to-blue-500"
                            style={{ height: `${Math.max(height, 2)}%` }}
                            title={`${currencyFormatter.format(day.total)}`}
                        />
                        {/* Label */}
                        <span className="text-xs text-gray-500 whitespace-nowrap">
                            {dateFormatter.format(new Date(day.date))}
                        </span>
                    </div>
                );
            })}
        </div>
    );
}

// ═══════════════════════════════════════════════════════════════════════════
// DATE RANGE PICKER
// ═══════════════════════════════════════════════════════════════════════════
function DateRangePicker({ startDate, endDate, onChange }) {
    return (
        <div className="flex items-center gap-3 bg-white rounded-xl border border-gray-200 p-2">
            <input
                type="date"
                value={startDate}
                onChange={(e) => onChange({ startDate: e.target.value, endDate })}
                className="px-3 py-2 bg-gray-50 border-0 rounded-lg text-sm focus:ring-2 focus:ring-blue-500"
            />
            <span className="text-gray-400">—</span>
            <input
                type="date"
                value={endDate}
                onChange={(e) => onChange({ startDate, endDate: e.target.value })}
                className="px-3 py-2 bg-gray-50 border-0 rounded-lg text-sm focus:ring-2 focus:ring-blue-500"
            />
        </div>
    );
}

// ═══════════════════════════════════════════════════════════════════════════
// MAIN PAGE COMPONENT
// ═══════════════════════════════════════════════════════════════════════════
export default function ReportsIndex({ stats = {}, dailySales = [], filters = {}, tenant }) {
    // Default to last 30 days
    const [dateRange, setDateRange] = useState({
        startDate: filters.start_date || new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
        endDate: filters.end_date || new Date().toISOString().split('T')[0],
    });

    // Reload data when date range changes
    useEffect(() => {
        const timer = setTimeout(() => {
            router.get(
                route('tenant.reports.index'),
                {
                    start_date: dateRange.startDate,
                    end_date: dateRange.endDate,
                },
                { preserveState: true, preserveScroll: true, replace: true }
            );
        }, 500); // Debounce

        return () => clearTimeout(timer);
    }, [dateRange]);

    return (
        <TenantLayout>
            <Head title="Reportes" />

            <div className="min-h-screen bg-gray-50">
                {/* Header */}
                <header className="bg-white border-b border-gray-200 sticky top-0 z-40">
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        <div className="flex items-center justify-between h-16">
                            <h1 className="text-xl font-bold text-gray-900">📊 Reportes</h1>
                            <DateRangePicker
                                startDate={dateRange.startDate}
                                endDate={dateRange.endDate}
                                onChange={setDateRange}
                            />
                        </div>
                    </div>
                </header>

                <main className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                    {/* Stats Summary */}
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                        <StatCard
                            title="Ingresos Totales"
                            value={currencyFormatter.format(stats.revenue || 0)}
                            trend={stats.revenue_trend}
                            trendLabel="vs período anterior"
                            icon={
                                <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
                                        d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            }
                        />
                        <StatCard
                            title="Total Órdenes"
                            value={stats.orders || 0}
                            trend={stats.orders_trend}
                            icon={
                                <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
                                        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                </svg>
                            }
                        />
                        <StatCard
                            title="Ticket Promedio"
                            value={currencyFormatter.format(stats.average_ticket || 0)}
                            trend={stats.ticket_trend}
                            icon={
                                <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
                                        d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z" />
                                </svg>
                            }
                        />
                    </div>

                    {/* Charts Section */}
                    <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-8">
                        <h2 className="font-bold text-gray-900 mb-6">Ventas Diarias</h2>
                        <DailySalesChart data={dailySales} />
                    </div>

                    {/* Export Zone */}
                    <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                        <h2 className="font-bold text-gray-900 mb-4">📄 Exportar Reportes</h2>
                        <p className="text-gray-500 text-sm mb-6">
                            Descarga reportes en PDF basados en el rango de fechas seleccionado.
                        </p>

                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                            {/* Sales Summary */}
                            <div className="border border-gray-200 rounded-xl p-4 hover:border-blue-300 transition-colors">
                                <h3 className="font-semibold text-gray-900 mb-2">Resumen de Ventas</h3>
                                <p className="text-sm text-gray-500 mb-4">
                                    Ingresos, órdenes y métricas clave del período.
                                </p>
                                <DownloadSalesReportButton
                                    startDate={dateRange.startDate}
                                    endDate={dateRange.endDate}
                                    className="w-full justify-center"
                                />
                            </div>

                            {/* Inventory Manifest */}
                            <div className="border border-gray-200 rounded-xl p-4 hover:border-blue-300 transition-colors">
                                <h3 className="font-semibold text-gray-900 mb-2">Inventario Actual</h3>
                                <p className="text-sm text-gray-500 mb-4">
                                    Lista completa de productos y disponibilidad.
                                </p>
                                <button
                                    onClick={() => window.open(route('reports.inventory-pdf'), '_blank')}
                                    className="w-full inline-flex items-center justify-center gap-2 px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium rounded-xl transition-colors"
                                >
                                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
                                            d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                    Descargar PDF
                                </button>
                            </div>

                            {/* PRO: Advanced Reports */}
                            {!tenant?.is_pro && (
                                <div className="border border-gray-200 rounded-xl p-4 bg-gray-50 opacity-75">
                                    <h3 className="font-semibold text-gray-900 mb-2">🔒 Reportes Avanzados</h3>
                                    <p className="text-sm text-gray-500 mb-4">
                                        Análisis de tendencias y predicciones.
                                    </p>
                                    <button
                                        disabled
                                        className="w-full inline-flex items-center justify-center gap-2 px-4 py-2 bg-gray-200 text-gray-400 font-medium rounded-xl cursor-not-allowed"
                                    >
                                        Solo PRO
                                    </button>
                                </div>
                            )}
                        </div>
                    </div>
                </main>
            </div>
        </TenantLayout>
    );
}
