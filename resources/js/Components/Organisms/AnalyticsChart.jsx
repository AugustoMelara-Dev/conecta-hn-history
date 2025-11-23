import { useEffect, useState } from 'react';
import { AreaChart, Area, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer } from 'recharts';
import { Card } from '@/Components/ui/card';
import { TrendingUp, Users, Eye } from 'lucide-react';

/**
 * AnalyticsChart Component - Pilar 8
 * 
 * Displays tenant visit analytics with Recharts
 * Vercel-style minimalist design
 */
export default function AnalyticsChart({ data = [], summary = {} }) {
    const [mounted, setMounted] = useState(false);

    useEffect(() => {
        setMounted(true);
    }, []);

    if (!mounted) {
        return (
            <div className="animate-pulse space-y-4">
                <div className="h-8 bg-slate-200 rounded w-1/3"></div>
                <div className="h-64 bg-slate-100 rounded"></div>
            </div>
        );
    }

    // Format date for display
    const formatDate = (dateStr) => {
        const date = new Date(dateStr);
        return date.toLocaleDateString('es-HN', { month: 'short', day: 'numeric' });
    };

    // Custom tooltip
    const CustomTooltip = ({ active, payload }) => {
        if (active && payload && payload.length) {
            return (
                <div className="bg-white p-3 rounded-lg shadow-lg border border-slate-200">
                    <p className="text-sm font-medium text-slate-900">
                        {formatDate(payload[0].payload.date)}
                    </p>
                    <p className="text-sm text-slate-600 mt-1">
                        <span className="font-semibold text-blue-600">{payload[0].value}</span> visitas únicas
                    </p>
                    {payload[0].payload.total && (
                        <p className="text-xs text-slate-500 mt-1">
                            {payload[0].payload.total} visitas totales
                        </p>
                    )}
                </div>
            );
        }
        return null;
    };

    return (
        <div className="space-y-6">
            {/* Summary Cards */}
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                <Card className="p-6 bg-white border border-slate-200">
                    <div className="flex items-center gap-3">
                        <div className="p-3 bg-blue-50 rounded-lg">
                            <Users className="w-5 h-5 text-blue-600" />
                        </div>
                        <div>
                            <p className="text-sm text-slate-500">Visitas Únicas</p>
                            <p className="text-2xl font-bold text-slate-900">
                                {summary.total_unique_visits?.toLocaleString() || 0}
                            </p>
                        </div>
                    </div>
                </Card>

                <Card className="p-6 bg-white border border-slate-200">
                    <div className="flex items-center gap-3">
                        <div className="p-3 bg-green-50 rounded-lg">
                            <TrendingUp className="w-5 h-5 text-green-600" />
                        </div>
                        <div>
                            <p className="text-sm text-slate-500">Promedio Diario</p>
                            <p className="text-2xl font-bold text-slate-900">
                                {summary.average_daily_visits || 0}
                            </p>
                        </div>
                    </div>
                </Card>

                <Card className="p-6 bg-white border border-slate-200">
                    <div className="flex items-center gap-3">
                        <div className="p-3 bg-purple-50 rounded-lg">
                            <Eye className="w-5 h-5 text-purple-600" />
                        </div>
                        <div>
                            <p className="text-sm text-slate-500">Día Pico</p>
                            <p className="text-2xl font-bold text-slate-900">
                                {summary.peak_day?.visits || 0}
                            </p>
                            {summary.peak_day && (
                                <p className="text-xs text-slate-400 mt-1">
                                    {formatDate(summary.peak_day.date)}
                                </p>
                            )}
                        </div>
                    </div>
                </Card>
            </div>

            {/* Chart */}
            <Card className="p-6 bg-white border border-slate-200">
                <h3 className="text-lg font-semibold text-slate-900 mb-6">
                    Visitas en los Últimos 30 Días
                </h3>

                {data.length > 0 ? (
                    <ResponsiveContainer width="100%" height={300}>
                        <AreaChart data={data} margin={{ top: 10, right: 10, left: 0, bottom: 0 }}>
                            <defs>
                                <linearGradient id="colorVisits" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="5%" stopColor="#3b82f6" stopOpacity={0.3} />
                                    <stop offset="95%" stopColor="#3b82f6" stopOpacity={0} />
                                </linearGradient>
                            </defs>
                            <CartesianGrid strokeDasharray="3 3" stroke="#e2e8f0" />
                            <XAxis
                                dataKey="date"
                                tickFormatter={formatDate}
                                stroke="#94a3b8"
                                style={{ fontSize: '12px' }}
                            />
                            <YAxis
                                stroke="#94a3b8"
                                style={{ fontSize: '12px' }}
                            />
                            <Tooltip content={<CustomTooltip />} />
                            <Area
                                type="monotone"
                                dataKey="visits"
                                stroke="#3b82f6"
                                strokeWidth={2}
                                fillOpacity={1}
                                fill="url(#colorVisits)"
                            />
                        </AreaChart>
                    </ResponsiveContainer>
                ) : (
                    <div className="h-64 flex items-center justify-center text-slate-400">
                        <div className="text-center">
                            <Eye className="w-12 h-12 mx-auto mb-3 opacity-30" />
                            <p>No hay datos de visitas aún</p>
                            <p className="text-sm mt-1">Los datos aparecerán cuando recibas visitas</p>
                        </div>
                    </div>
                )}
            </Card>
        </div>
    );
}
