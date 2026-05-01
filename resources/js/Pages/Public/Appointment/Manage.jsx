import { Head, useForm, usePage } from '@inertiajs/react';
import PublicLayout from '@/Layouts/PublicLayout';
import DestructiveAction from '@/Components/App/DestructiveAction';
import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Separator } from '@/Components/ui/separator';
import { Spinner } from '@/Components/ui/spinner';
import { AlertCircle, CalendarClock, CheckCircle2, ChevronLeft } from 'lucide-react';

/**
 * Guest appointment management page.
 *
 * Allows guests to view appointment details and cancel via signed URL.
 */
export default function ManageAppointment({ appointment, tenant }) {
    const { post, processing } = useForm();
    const { flash } = usePage().props;

    const handleConfirmCancel = () => {
        post(appointment.cancel_url);
    };

    const statusLabels = {
        pending: 'Pendiente',
        confirmed: 'Confirmada',
        cancelled: 'Cancelada',
        completed: 'Completada',
    };

    const statusVariants = {
        pending: 'secondary',
        confirmed: 'default',
        cancelled: 'destructive',
        completed: 'outline',
    };

    const detailRows = [
        ['Cliente', appointment.customer_name],
        ['Servicio', appointment.service_name],
        ['Profesional', appointment.provider_name],
        ['Fecha', appointment.scheduled_at],
        ['Hora', appointment.time],
    ];

    return (
        <PublicLayout>
            <Head title={`Gestionar Cita - ${tenant.name}`} />

            <section className="min-h-screen bg-background px-4 py-10 sm:px-6 lg:px-8">
                <div className="mx-auto flex w-full max-w-xl flex-col gap-4">
                    <a
                        href={tenant.microsite_url}
                        className="inline-flex w-fit items-center gap-2 rounded-md text-sm font-medium text-muted-foreground transition-colors hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                    >
                        <ChevronLeft className="h-4 w-4" aria-hidden="true" />
                        Volver a {tenant.name}
                    </a>

                    <Card className="overflow-hidden">
                        <CardHeader className="border-b bg-muted/40">
                            <div className="flex items-start justify-between gap-4">
                                <div className="flex min-w-0 items-center gap-3">
                                    {tenant.logo_url ? (
                                        <img
                                            src={tenant.logo_url}
                                            alt={tenant.name}
                                            className="h-11 w-11 shrink-0 rounded-full border bg-background object-cover"
                                        />
                                    ) : (
                                        <div className="flex h-11 w-11 shrink-0 items-center justify-center rounded-full border bg-background text-sm font-semibold text-muted-foreground">
                                            {tenant.name?.charAt(0) || 'C'}
                                        </div>
                                    )}
                                    <div className="min-w-0">
                                        <p className="truncate text-sm font-medium text-muted-foreground">{tenant.name}</p>
                                        <CardTitle className="mt-1 flex items-center gap-2 text-xl">
                                            <CalendarClock className="h-5 w-5 text-muted-foreground" aria-hidden="true" />
                                            Gestion de cita
                                        </CardTitle>
                                    </div>
                                </div>

                                <Badge variant={statusVariants[appointment.status] || 'outline'}>
                                    {statusLabels[appointment.status] || appointment.status}
                                </Badge>
                            </div>
                        </CardHeader>

                        <CardContent className="space-y-6 pt-6">
                            {flash?.success ? (
                                <Alert variant="success" role="status" aria-live="polite">
                                    <CheckCircle2 className="mr-2 inline h-4 w-4" aria-hidden="true" />
                                    <AlertTitle className="inline">Listo</AlertTitle>
                                    <AlertDescription className="mt-2">{flash.success}</AlertDescription>
                                </Alert>
                            ) : null}

                            {flash?.error ? (
                                <Alert variant="destructive" role="alert" aria-live="assertive">
                                    <AlertCircle className="mr-2 inline h-4 w-4" aria-hidden="true" />
                                    <AlertTitle className="inline">No se pudo completar</AlertTitle>
                                    <AlertDescription className="mt-2">{flash.error}</AlertDescription>
                                </Alert>
                            ) : null}

                            <div className="space-y-4">
                                <div>
                                    <h2 className="text-base font-semibold text-foreground">Detalles de tu cita</h2>
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        Revisa la informacion antes de cancelar o volver al negocio.
                                    </p>
                                </div>

                                <div className="rounded-lg border bg-muted/20 p-4">
                                    <dl className="space-y-0">
                                        {detailRows.map(([label, value], index) => (
                                            <div key={label}>
                                                {index > 0 ? <Separator className="my-3" /> : null}
                                                <div className="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                                                    <dt className="text-sm text-muted-foreground">{label}</dt>
                                                    <dd className="text-sm font-medium text-foreground sm:text-right">{value}</dd>
                                                </div>
                                            </div>
                                        ))}
                                    </dl>
                                </div>
                            </div>

                            <Separator />

                            {appointment.can_cancel ? (
                                <div className="space-y-3">
                                    <DestructiveAction
                                        title="Cancelar cita"
                                        description="Esta cita se marcara como cancelada y el negocio vera el cambio. Esta accion no se puede deshacer."
                                        confirmLabel={processing ? 'Procesando...' : 'Cancelar cita'}
                                        onConfirm={handleConfirmCancel}
                                    >
                                        <Button
                                            type="button"
                                            variant="destructive"
                                            className="w-full"
                                            disabled={processing}
                                        >
                                            {processing ? <Spinner data-icon="inline-start" aria-hidden="true" /> : null}
                                            {processing ? 'Procesando...' : 'Cancelar cita'}
                                        </Button>
                                    </DestructiveAction>
                                    <p className="text-center text-xs text-muted-foreground">
                                        Esta accion no se puede deshacer.
                                    </p>
                                </div>
                            ) : (
                                <Alert>
                                    <AlertTitle>Sin acciones disponibles</AlertTitle>
                                    <AlertDescription>
                                        Esta cita ya no puede ser cancelada.
                                    </AlertDescription>
                                </Alert>
                            )}

                            <Button asChild variant="outline" className="w-full">
                                <a href={tenant.microsite_url}>
                                    Volver a {tenant.name}
                                </a>
                            </Button>
                        </CardContent>
                    </Card>
                </div>
            </section>
        </PublicLayout>
    );
}
