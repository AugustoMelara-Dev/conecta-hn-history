import { useEffect, useMemo, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import {
    BarChart3,
    CalendarDays,
    ClipboardList,
    Download,
    FileBarChart,
    Lock,
    PackageSearch,
    ReceiptText,
    TrendingUp,
} from 'lucide-react';

import { DownloadSalesReportButton } from '@/Components/Reports/ReportActions';
import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/Components/ui/card';
import {
    Field,
    FieldDescription,
    FieldGroup,
    FieldLabel,
} from '@/Components/ui/field';
import { Input } from '@/Components/ui/input';
import { Separator } from '@/Components/ui/separator';
import TenantLayout from '@/Layouts/TenantLayout';

const currencyFormatter = new Intl.NumberFormat('es-HN', {
    style: 'currency',
    currency: 'HNL',
    minimumFractionDigits: 0,
});

const dateFormatter = new Intl.DateTimeFormat('es-HN', {
    day: '2-digit',
    month: 'short',
});

function safeRoute(name, fallback) {
    try {
        if (route().has(name)) {
            return route(name, undefined, false);
        }
    } catch {
        return fallback;
    }

    return fallback;
}

function safeNamedRoute(name, fallback) {
    try {
        if (route().has(name)) {
            return route(name);
        }
    } catch {
        return fallback;
    }

    return fallback;
}

function formatCount(value) {
    return Number(value || 0).toLocaleString('es-HN');
}

function normalizeSales(data = []) {
    return Array.isArray(data)
        ? data.map((day) => ({
            date: day.date,
            total: Number(day.total || 0),
        }))
        : [];
}

function StatSummaryCard({ title, value, description, icon: Icon, trend }) {
    const hasTrend = trend !== undefined && trend !== null;

    return (
        <Card>
            <CardHeader>
                <div className="flex items-start justify-between gap-4">
                    <div className="flex flex-col gap-2">
                        <CardDescription>{title}</CardDescription>
                        <CardTitle>{value}</CardTitle>
                    </div>
                    <span className="flex size-10 items-center justify-center rounded-md bg-muted text-muted-foreground">
                        <Icon aria-hidden="true" />
                    </span>
                </div>
            </CardHeader>
            <CardContent className="flex flex-col gap-3">
                <p className="text-sm leading-6 text-muted-foreground">{description}</p>
                {hasTrend ? (
                    <Badge variant="secondary" className="w-fit">
                        {Number(trend) >= 0 ? '+' : ''}
                        {trend}% vs periodo anterior
                    </Badge>
                ) : null}
            </CardContent>
        </Card>
    );
}

function DailySalesChart({ data }) {
    const normalizedData = normalizeSales(data);
    const maxValue = Math.max(...normalizedData.map((day) => day.total), 0);

    if (normalizedData.length === 0 || maxValue <= 0) {
        return (
            <Alert data-testid="reports-sales-chart" aria-live="polite">
                <BarChart3 aria-hidden="true" />
                <AlertTitle>Sin ventas para este rango</AlertTitle>
                <AlertDescription>
                    Cambia las fechas o vuelve cuando existan ordenes completadas en el periodo.
                </AlertDescription>
            </Alert>
        );
    }

    return (
        <div className="flex flex-col gap-4" data-testid="reports-sales-chart" aria-label="Ventas diarias">
            <div className="flex flex-col gap-3 rounded-lg border bg-muted/30 p-4">
                {normalizedData.map((day) => (
                    <div
                        key={`${day.date}-${day.total}`}
                        className="grid gap-2 sm:grid-cols-[7rem_1fr_8rem] sm:items-center"
                    >
                        <span className="text-sm font-medium">
                            {dateFormatter.format(new Date(day.date))}
                        </span>
                        <progress
                            className="h-2 w-full overflow-hidden rounded-full"
                            max={maxValue}
                            value={day.total}
                            aria-label={`Ventas del ${dateFormatter.format(new Date(day.date))}`}
                        >
                            {currencyFormatter.format(day.total)}
                        </progress>
                        <span className="text-sm text-muted-foreground sm:text-right">
                            {currencyFormatter.format(day.total)}
                        </span>
                    </div>
                ))}
            </div>
            <p className="text-sm text-muted-foreground" aria-live="polite">
                Total maximo diario en el rango: {currencyFormatter.format(maxValue)}.
            </p>
        </div>
    );
}

function DateRangePicker({ startDate, endDate, onChange }) {
    return (
        <FieldGroup className="grid gap-4 sm:grid-cols-2" data-testid="reports-date-filter">
            <Field>
                <FieldLabel htmlFor="reports-start-date">Fecha inicial</FieldLabel>
                <Input
                    id="reports-start-date"
                    type="date"
                    value={startDate}
                    onChange={(event) => onChange({ startDate: event.target.value, endDate })}
                    aria-describedby="reports-date-help"
                />
            </Field>
            <Field>
                <FieldLabel htmlFor="reports-end-date">Fecha final</FieldLabel>
                <Input
                    id="reports-end-date"
                    type="date"
                    value={endDate}
                    onChange={(event) => onChange({ startDate, endDate: event.target.value })}
                    aria-describedby="reports-date-help"
                />
            </Field>
            <FieldDescription id="reports-date-help" className="sm:col-span-2">
                Los datos se actualizan automaticamente y las descargas usan este mismo rango.
            </FieldDescription>
        </FieldGroup>
    );
}

export default function ReportsIndex({ stats = {}, dailySales = [], filters = {}, tenant }) {
    const [dateRange, setDateRange] = useState({
        startDate: filters.start_date || new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
        endDate: filters.end_date || new Date().toISOString().split('T')[0],
    });

    const reportsUrl = useMemo(() => safeRoute('tenant.reports.index', '/workspace/reports'), []);
    const inventoryPdfUrl = useMemo(() => safeNamedRoute('reports.inventory-pdf', '/workspace/reports/inventory-pdf'), []);

    useEffect(() => {
        const timer = window.setTimeout(() => {
            router.get(
                reportsUrl,
                {
                    start_date: dateRange.startDate,
                    end_date: dateRange.endDate,
                },
                { preserveState: true, preserveScroll: true, replace: true }
            );
        }, 500);

        return () => window.clearTimeout(timer);
    }, [dateRange, reportsUrl]);

    const salesCount = normalizeSales(dailySales).length;

    return (
        <TenantLayout>
            <Head title="Reportes" />

            <main className="min-h-screen bg-background" data-testid="reports-page">
                <div className="mx-auto flex max-w-7xl flex-col gap-8 px-4 py-8 sm:px-6 lg:px-8">
                    <Card>
                        <CardHeader className="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
                            <div className="flex max-w-2xl flex-col gap-3">
                                <Badge variant="secondary" className="w-fit">
                                    Compat activo
                                </Badge>
                                <div className="flex flex-col gap-2">
                                    <CardTitle className="text-3xl">Reportes</CardTitle>
                                    <CardDescription>
                                        Revisa ventas, ordenes y exportaciones PDF sin salir del flujo actual de trabajo.
                                    </CardDescription>
                                </div>
                            </div>
                            <div className="w-full max-w-xl">
                                <DateRangePicker
                                    startDate={dateRange.startDate}
                                    endDate={dateRange.endDate}
                                    onChange={setDateRange}
                                />
                            </div>
                        </CardHeader>
                    </Card>

                    <section className="grid gap-4 md:grid-cols-3" aria-label="Resumen de reportes">
                        <StatSummaryCard
                            title="Ingresos totales"
                            value={currencyFormatter.format(stats.revenue || 0)}
                            description="Ventas registradas para el rango seleccionado."
                            icon={ReceiptText}
                            trend={stats.revenue_trend}
                        />
                        <StatSummaryCard
                            title="Ordenes"
                            value={formatCount(stats.orders)}
                            description="Cantidad de pedidos incluidos en el periodo."
                            icon={ClipboardList}
                            trend={stats.orders_trend}
                        />
                        <StatSummaryCard
                            title="Ticket promedio"
                            value={currencyFormatter.format(stats.average_ticket || 0)}
                            description="Promedio de ingreso por orden confirmada."
                            icon={TrendingUp}
                            trend={stats.ticket_trend}
                        />
                    </section>

                    <Card>
                        <CardHeader>
                            <div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                <div className="flex flex-col gap-1">
                                    <CardTitle>Ventas diarias</CardTitle>
                                    <CardDescription>
                                        {salesCount > 0
                                            ? `${salesCount} dias con datos en el rango seleccionado.`
                                            : 'No hay puntos de venta para graficar en este rango.'}
                                    </CardDescription>
                                </div>
                                <Badge variant="outline">{dateRange.startDate} - {dateRange.endDate}</Badge>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <DailySalesChart data={dailySales} />
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Exportar reportes</CardTitle>
                            <CardDescription>
                                Las descargas se generan en backend y se abren en una nueva pestana.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="grid gap-4 md:grid-cols-3">
                                <Card className="flex flex-col">
                                    <CardHeader>
                                        <FileBarChart aria-hidden="true" className="text-muted-foreground" />
                                        <CardTitle className="text-lg">Resumen de ventas</CardTitle>
                                        <CardDescription>
                                            Ingresos, ordenes y metricas clave del periodo filtrado.
                                        </CardDescription>
                                    </CardHeader>
                                    <CardFooter className="mt-auto">
                                        <DownloadSalesReportButton
                                            startDate={dateRange.startDate}
                                            endDate={dateRange.endDate}
                                            className="w-full"
                                            data-testid="reports-sales-pdf"
                                        />
                                    </CardFooter>
                                </Card>

                                <Card className="flex flex-col">
                                    <CardHeader>
                                        <PackageSearch aria-hidden="true" className="text-muted-foreground" />
                                        <CardTitle className="text-lg">Inventario actual</CardTitle>
                                        <CardDescription>
                                            Lista de productos, disponibilidad y estado de inventario.
                                        </CardDescription>
                                    </CardHeader>
                                    <CardFooter className="mt-auto">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            className="w-full"
                                            onClick={() => window.open(inventoryPdfUrl, '_blank')}
                                            data-download-url={inventoryPdfUrl}
                                            data-testid="reports-inventory-pdf"
                                        >
                                            <Download data-icon="inline-start" aria-hidden="true" />
                                            Descargar PDF
                                        </Button>
                                    </CardFooter>
                                </Card>

                                {!tenant?.is_pro ? (
                                    <Card className="flex flex-col border-dashed">
                                        <CardHeader>
                                            <Lock aria-hidden="true" className="text-muted-foreground" />
                                            <CardTitle className="text-lg">Reportes avanzados</CardTitle>
                                            <CardDescription>
                                                Analisis de tendencias y predicciones para planes Pro.
                                            </CardDescription>
                                        </CardHeader>
                                        <CardContent>
                                            <Separator />
                                        </CardContent>
                                        <CardFooter className="mt-auto">
                                            <Button type="button" variant="secondary" className="w-full" disabled>
                                                Solo PRO
                                            </Button>
                                        </CardFooter>
                                    </Card>
                                ) : (
                                    <Alert>
                                        <CalendarDays aria-hidden="true" />
                                        <AlertTitle>Plan Pro activo</AlertTitle>
                                        <AlertDescription>
                                            Los reportes avanzados se mantienen en el pipeline de producto.
                                        </AlertDescription>
                                    </Alert>
                                )}
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </main>
        </TenantLayout>
    );
}
