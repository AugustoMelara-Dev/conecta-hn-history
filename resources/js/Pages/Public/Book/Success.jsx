import { Head, Link } from '@inertiajs/react';
import { CalendarCheck, CalendarPlus, Home } from 'lucide-react';
import PublicLayout from '@/Layouts/PublicLayout';
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
import { Separator } from '@/Components/ui/separator';

export default function BookingSuccess({ tenant, appointment }) {
    return (
        <PublicLayout>
            <Head title={`Reserva confirmada - ${tenant.name}`} />

            <div className="min-h-screen bg-background px-4 py-12 sm:px-6 lg:px-8">
                <div className="mx-auto flex max-w-xl flex-col gap-6">
                    <Card className="text-center" data-testid="booking-success">
                        <CardHeader className="items-center gap-4">
                            <div className="flex size-16 items-center justify-center rounded-full bg-primary text-primary-foreground">
                                <CalendarCheck aria-hidden="true" />
                            </div>
                            <div className="flex flex-col gap-2">
                                <Badge variant="secondary" className="mx-auto w-fit">
                                    Reserva recibida
                                </Badge>
                                <CardTitle>Reserva confirmada</CardTitle>
                                <CardDescription>
                                    Hemos recibido tu solicitud. El negocio te contactara pronto para confirmar.
                                </CardDescription>
                            </div>
                        </CardHeader>

                        {appointment ? (
                            <CardContent>
                                <div className="rounded-lg border bg-muted/30 p-4 text-left">
                                    <h2 className="text-sm font-semibold text-foreground">
                                        Detalles de tu cita
                                    </h2>
                                    <Separator className="my-3" />
                                    <dl className="grid gap-3 text-sm">
                                        <div className="flex items-center justify-between gap-4">
                                            <dt className="text-muted-foreground">Servicio</dt>
                                            <dd className="font-medium text-foreground">{appointment.service_name}</dd>
                                        </div>
                                        <div className="flex items-center justify-between gap-4">
                                            <dt className="text-muted-foreground">Profesional</dt>
                                            <dd className="font-medium text-foreground">{appointment.provider_name}</dd>
                                        </div>
                                        <div className="flex items-center justify-between gap-4">
                                            <dt className="text-muted-foreground">Fecha</dt>
                                            <dd className="font-medium text-foreground">{appointment.scheduled_at}</dd>
                                        </div>
                                        <div className="flex items-center justify-between gap-4">
                                            <dt className="text-muted-foreground">Hora</dt>
                                            <dd className="font-medium text-foreground">{appointment.time}</dd>
                                        </div>
                                    </dl>
                                </div>
                            </CardContent>
                        ) : null}

                        <CardFooter className="flex flex-col gap-3 sm:flex-row">
                            <Button asChild className="w-full">
                                <Link href="/" data-testid="booking-success-home">
                                    <Home data-icon="inline-start" />
                                    Volver al negocio
                                </Link>
                            </Button>
                            <Button asChild variant="outline" className="w-full">
                                <Link href="/book" data-testid="booking-success-new">
                                    <CalendarPlus data-icon="inline-start" />
                                    Hacer otra reserva
                                </Link>
                            </Button>
                        </CardFooter>
                    </Card>

                    <p className="text-center text-xs text-muted-foreground">
                        Reserva en {tenant.name}
                    </p>
                </div>
            </div>
        </PublicLayout>
    );
}
