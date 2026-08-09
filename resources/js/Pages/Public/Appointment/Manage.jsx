import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { CalendarX, CheckCircle2, Clock, XCircle } from 'lucide-react';
import PublicLayout from '@/Layouts/PublicLayout';
import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert';
import { Avatar, AvatarFallback, AvatarImage } from '@/Components/ui/avatar';
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
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/Components/ui/dialog';
import { Separator } from '@/Components/ui/separator';
import { Spinner } from '@/Components/ui/spinner';

const STATUS_LABELS = {
    pending: 'Pendiente',
    confirmed: 'Confirmada',
    cancelled: 'Cancelada',
    completed: 'Completada',
};

const STATUS_VARIANTS = {
    pending: 'secondary',
    confirmed: 'default',
    cancelled: 'destructive',
    completed: 'outline',
};

function initials(name = '') {
    return name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0])
        .join('')
        .toUpperCase() || 'CN';
}

export default function ManageAppointment({ appointment, tenant }) {
    const { post, processing } = useForm();
    const { flash } = usePage().props;

    const handleCancel = () => {
        if (appointment.cancel_url) {
            post(appointment.cancel_url);
        }
    };

    return (
        <PublicLayout>
            <Head title={`Gestionar cita - ${tenant.name}`} />

            <div className="min-h-screen bg-background px-4 py-12 sm:px-6 lg:px-8">
                <div className="mx-auto flex max-w-xl flex-col gap-6">
                    <Card data-testid="appointment-manage">
                        <CardHeader className="gap-4">
                            <div className="flex items-center gap-3">
                                <Avatar className="size-12">
                                    <AvatarImage src={tenant.logo_url} alt={tenant.name} />
                                    <AvatarFallback>{initials(tenant.name)}</AvatarFallback>
                                </Avatar>
                                <div className="min-w-0 flex-1">
                                    <CardTitle className="text-xl">{tenant.name}</CardTitle>
                                    <CardDescription>Gestion de cita</CardDescription>
                                </div>
                                <Badge variant={STATUS_VARIANTS[appointment.status] ?? 'outline'}>
                                    {STATUS_LABELS[appointment.status] ?? appointment.status}
                                </Badge>
                            </div>
                        </CardHeader>

                        <CardContent className="flex flex-col gap-5">
                            {flash?.success ? (
                                <Alert variant="success" aria-live="polite">
                                    <CheckCircle2 data-icon="inline-start" />
                                    <AlertTitle>Listo</AlertTitle>
                                    <AlertDescription>{flash.success}</AlertDescription>
                                </Alert>
                            ) : null}

                            {flash?.error ? (
                                <Alert variant="destructive" aria-live="polite">
                                    <XCircle data-icon="inline-start" />
                                    <AlertTitle>No se pudo completar</AlertTitle>
                                    <AlertDescription>{flash.error}</AlertDescription>
                                </Alert>
                            ) : null}

                            <div className="flex flex-col gap-2">
                                <h1 className="text-lg font-semibold text-foreground">
                                    Detalles de tu cita
                                </h1>
                                <p className="text-sm text-muted-foreground">
                                    Revisa la informacion antes de solicitar una cancelacion.
                                </p>
                            </div>

                            <div className="rounded-lg border bg-muted/30 p-4">
                                <dl className="grid gap-3 text-sm">
                                    <div className="flex items-center justify-between gap-4">
                                        <dt className="text-muted-foreground">Cliente</dt>
                                        <dd className="text-right font-medium text-foreground">
                                            {appointment.customer_name}
                                        </dd>
                                    </div>
                                    <div className="flex items-center justify-between gap-4">
                                        <dt className="text-muted-foreground">Servicio</dt>
                                        <dd className="text-right font-medium text-foreground">
                                            {appointment.service_name}
                                        </dd>
                                    </div>
                                    <div className="flex items-center justify-between gap-4">
                                        <dt className="text-muted-foreground">Profesional</dt>
                                        <dd className="text-right font-medium text-foreground">
                                            {appointment.provider_name}
                                        </dd>
                                    </div>
                                    <Separator />
                                    <div className="flex items-center justify-between gap-4">
                                        <dt className="text-muted-foreground">Fecha</dt>
                                        <dd className="text-right font-medium text-foreground">
                                            {appointment.scheduled_at}
                                        </dd>
                                    </div>
                                    <div className="flex items-center justify-between gap-4">
                                        <dt className="text-muted-foreground">Hora</dt>
                                        <dd className="text-right font-medium text-foreground">
                                            {appointment.time}
                                        </dd>
                                    </div>
                                </dl>
                            </div>

                            {!appointment.can_cancel ? (
                                <Alert>
                                    <Clock data-icon="inline-start" />
                                    <AlertTitle>Esta cita ya no puede cancelarse</AlertTitle>
                                    <AlertDescription>
                                        Si necesitas ayuda, contacta directamente al negocio.
                                    </AlertDescription>
                                </Alert>
                            ) : null}
                        </CardContent>

                        <CardFooter className="flex flex-col gap-3 sm:flex-row">
                            {appointment.can_cancel ? (
                                <Dialog>
                                    <DialogTrigger asChild>
                                        <Button
                                            type="button"
                                            variant="destructive"
                                            className="w-full"
                                            data-testid="appointment-cancel-open"
                                        >
                                            <CalendarX data-icon="inline-start" />
                                            Cancelar cita
                                        </Button>
                                    </DialogTrigger>
                                    <DialogContent>
                                        <DialogHeader>
                                            <DialogTitle>Cancelar cita</DialogTitle>
                                            <DialogDescription>
                                                Esta accion no se puede deshacer. El negocio vera la cita como cancelada.
                                            </DialogDescription>
                                        </DialogHeader>
                                        <DialogFooter>
                                            <DialogClose asChild>
                                                <Button type="button" variant="outline">
                                                    Mantener cita
                                                </Button>
                                            </DialogClose>
                                            <Button
                                                type="button"
                                                variant="destructive"
                                                disabled={processing}
                                                onClick={handleCancel}
                                                data-testid="appointment-cancel-confirm"
                                            >
                                                {processing ? <Spinner /> : <CalendarX data-icon="inline-start" />}
                                                {processing ? 'Cancelando...' : 'Confirmar cancelacion'}
                                            </Button>
                                        </DialogFooter>
                                    </DialogContent>
                                </Dialog>
                            ) : null}

                            <Button asChild variant="outline" className="w-full">
                                <Link href={tenant.public_url || '/directorio'} data-testid="appointment-back-home">
                                    Volver a {tenant.name}
                                </Link>
                            </Button>
                        </CardFooter>
                    </Card>
                </div>
            </div>
        </PublicLayout>
    );
}
