import { Head, Link } from '@inertiajs/react';
import TenantLayout from '@/Layouts/TenantLayout';
import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert';
import { Button } from '@/Components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';
import {
    ActionCard,
    EmptyState,
    MetricCard,
    PageHeader,
    ResponsiveContainer,
    SectionShell,
    StatusBadge,
} from '@/Components/System';

const currencyFormatter = new Intl.NumberFormat('es-HN', {
    style: 'currency',
    currency: 'HNL',
    minimumFractionDigits: 2,
});

const dateFormatter = new Intl.DateTimeFormat('es-HN', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    hour12: true,
});

export default function BillingIndex({ tenant, subscription, payments = [], bankOptions = {} }) {
    const hasPendingPayment = payments.some((payment) => payment.status === 'pending');
    const officialBillingHref = tenant?.slug
        ? route('filament.app.pages.manage-billing', { tenant: tenant.slug })
        : '/app';
    const planLabel = tenant?.is_pro ? 'Plan PRO' : 'Plan gratuito';

    return (
        <TenantLayout>
            <Head title="Facturacion" />

            <div className="min-h-screen bg-muted/20">
                <PageHeader
                    title="Facturacion"
                    subtitle="Vista legacy de referencia. La fuente oficial de cobros y upgrades vive en el panel de billing."
                    badge={planLabel}
                    sticky={false}
                />

                <ResponsiveContainer size="6xl" paddedY className="space-y-6">
                    <ActionCard
                        title="Fuente oficial de billing"
                        description="Usa el panel oficial para reportar pagos, validar pendientes y gestionar upgrades."
                        action={(
                            <Button asChild className="w-full sm:w-auto">
                                <Link href={officialBillingHref}>Abrir billing oficial</Link>
                            </Button>
                        )}
                    >
                        <Alert variant="warning" role="status">
                            <AlertTitle>Fuente oficial</AlertTitle>
                            <AlertDescription>
                                Usa el panel oficial para reportar pagos, validar estados pendientes y gestionar upgrades. Aqui no se ejecutan acciones de cobro.
                            </AlertDescription>
                        </Alert>
                    </ActionCard>

                    {tenant?.is_pro ? (
                        <SectionShell
                            title="Suscripcion actual"
                            description="Estado operativo del plan y ultimas transacciones registradas."
                        >
                            <div className="grid gap-3 sm:grid-cols-3">
                                <MetricCard title="Estado" value={subscription?.status ?? 'Sin datos'} />
                                <MetricCard title="Proxima renovacion" value={subscription?.renewal_date ?? 'No disponible'} />
                                <MetricCard title="Plan" value={subscription?.plan ?? 'PRO'} />
                            </div>

                            {hasPendingPayment ? (
                                <Alert variant="warning" role="status" aria-live="polite" className="mt-4">
                                    <AlertTitle>Pago pendiente de revision</AlertTitle>
                                    <AlertDescription>
                                        Hay al menos un pago pendiente. Revisa su progreso en billing oficial para evitar confusiones de activacion.
                                    </AlertDescription>
                                </Alert>
                            ) : null}
                        </SectionShell>
                    ) : (
                        <SectionShell
                            title="Estado del plan"
                            description="Estas en plan gratuito. El upgrade real se gestiona en el panel oficial de billing."
                        >
                            <Button asChild>
                                <Link href={officialBillingHref}>Ver opciones de upgrade en panel oficial</Link>
                            </Button>
                        </SectionShell>
                    )}

                    <SectionShell
                        title="Historial de pagos"
                        description="Registro informativo de transacciones para referencia operativa."
                    >
                        <div className="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Fecha</TableHead>
                                        <TableHead>Referencia</TableHead>
                                        <TableHead>Banco</TableHead>
                                        <TableHead>Monto</TableHead>
                                        <TableHead>Estado</TableHead>
                                        <TableHead>Recibo</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {payments.length > 0 ? payments.map((payment) => (
                                        <TableRow key={payment.id}>
                                            <TableCell className="text-xs text-slate-600">{dateFormatter.format(new Date(payment.created_at))}</TableCell>
                                            <TableCell className="font-mono text-xs">{payment.reference_number}</TableCell>
                                            <TableCell>{bankOptions?.[payment.bank] || payment.bank}</TableCell>
                                            <TableCell className="font-semibold">{currencyFormatter.format(payment.amount)}</TableCell>
                                            <TableCell>
                                                <StatusBadge
                                                    status={payment.status}
                                                    label={payment.status === 'approved' ? 'Aprobado' : payment.status === 'rejected' ? 'Rechazado' : 'Pendiente'}
                                                />
                                            </TableCell>
                                            <TableCell className="text-xs text-slate-500">
                                                {payment.download_url ? (
                                                    <Button asChild variant="outline" size="sm">
                                                        <Link href={payment.download_url}>Descargar</Link>
                                                    </Button>
                                                ) : (
                                                    'Disponible desde billing oficial'
                                                )}
                                            </TableCell>
                                        </TableRow>
                                    )) : (
                                        <TableRow>
                                            <TableCell colSpan={6}>
                                                <EmptyState
                                                    title="No hay pagos registrados"
                                                    description="Cuando reportes pagos en el panel oficial, apareceran aqui como referencia."
                                                    actionLabel="Ir a billing oficial"
                                                    actionHref={officialBillingHref}
                                                />
                                            </TableCell>
                                        </TableRow>
                                    )}
                                </TableBody>
                            </Table>
                        </div>
                    </SectionShell>
                </ResponsiveContainer>
            </div>
        </TenantLayout>
    );
}
