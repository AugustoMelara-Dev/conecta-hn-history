import { useEffect, useMemo, useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import axios from 'axios';
import { ArrowLeft, CalendarDays, Check, Clock, Mail, Phone, UserRound } from 'lucide-react';
import PublicLayout from '@/Layouts/PublicLayout';
import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
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
import { Progress } from '@/Components/ui/progress';
import { Separator } from '@/Components/ui/separator';
import { Spinner } from '@/Components/ui/spinner';
import { Textarea } from '@/Components/ui/textarea';
import { cn } from '@/lib/utils';

const STEPS = [
    { id: 1, label: 'Servicio' },
    { id: 2, label: 'Profesional' },
    { id: 3, label: 'Horario' },
    { id: 4, label: 'Datos' },
];

function BookingShell({ tenant, step, children }) {
    const progress = (step / STEPS.length) * 100;

    return (
        <div className="min-h-screen bg-background px-4 py-8 sm:px-6 lg:px-8">
            <div className="mx-auto flex max-w-5xl flex-col gap-6">
                <header className="flex flex-col gap-3 text-center">
                    <Badge variant="outline" className="mx-auto w-fit">
                        Reserva publica
                    </Badge>
                    <div className="flex flex-col gap-2">
                        <h1 className="text-3xl font-semibold tracking-tight text-foreground">
                            Reserva tu cita
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {tenant.name}
                        </p>
                    </div>
                </header>

                <Card className="mx-auto w-full max-w-3xl">
                    <CardHeader className="gap-4">
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            {STEPS.map((item) => (
                                <div
                                    key={item.id}
                                    className={cn(
                                        'flex min-w-24 items-center gap-2 text-sm',
                                        item.id <= step
                                            ? 'text-foreground'
                                            : 'text-muted-foreground'
                                    )}
                                >
                                    <span
                                        className={cn(
                                            'flex size-7 items-center justify-center rounded-full border text-xs font-semibold',
                                            item.id <= step
                                                ? 'border-primary bg-primary text-primary-foreground'
                                                : 'border-border bg-muted'
                                        )}
                                        aria-hidden="true"
                                    >
                                        {item.id < step ? <Check data-icon="inline-start" /> : item.id}
                                    </span>
                                    <span>{item.label}</span>
                                </div>
                            ))}
                        </div>
                        <Progress value={progress} aria-label={`Paso ${step} de ${STEPS.length}`} />
                    </CardHeader>
                    <Separator />
                    <CardContent className="pt-6">
                        {children}
                    </CardContent>
                </Card>
            </div>
        </div>
    );
}

function StepHeader({ title, description, onBack }) {
    return (
        <div className="flex flex-col gap-4">
            {onBack ? (
                <Button type="button" variant="ghost" className="w-fit px-0" onClick={onBack}>
                    <ArrowLeft data-icon="inline-start" />
                    Volver
                </Button>
            ) : null}
            <div className="flex flex-col gap-1">
                <h2 className="text-xl font-semibold text-foreground">{title}</h2>
                <p className="text-sm text-muted-foreground">{description}</p>
            </div>
        </div>
    );
}

function SelectionCard({ children, descriptionId, onClick, selected = false, testId }) {
    return (
        <button
            type="button"
            aria-describedby={descriptionId}
            aria-pressed={selected}
            data-testid={testId}
            onClick={onClick}
            className={cn(
                'w-full rounded-lg border bg-card p-4 text-left text-card-foreground shadow-sm transition-colors hover:bg-accent hover:text-accent-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2',
                selected && 'border-primary'
            )}
        >
            {children}
        </button>
    );
}

export default function BookingWizard({ tenant, services = [], providers = [] }) {
    const [step, setStep] = useState(1);
    const [selectedService, setSelectedService] = useState(null);
    const [selectedProvider, setSelectedProvider] = useState(null);
    const [selectedDate, setSelectedDate] = useState('');
    const [availableSlots, setAvailableSlots] = useState([]);
    const [loadingSlots, setLoadingSlots] = useState(false);
    const [slotsError, setSlotsError] = useState('');

    const { data, setData, post, processing, errors } = useForm({
        service_id: null,
        service_provider_id: null,
        date: '',
        time: '',
        starts_at_utc: '',
        customer_name: '',
        customer_email: '',
        customer_phone: '',
        customer_notes: '',
    });

    const bookingBasePath = '/book';
    const today = new Date().toISOString().split('T')[0];

    const defaultProviderId = useMemo(() => providers[0]?.id ?? null, [providers]);

    const fetchSlots = async (date) => {
        setLoadingSlots(true);
        setSlotsError('');

        try {
            const response = await axios.get(`${bookingBasePath}/slots`, {
                params: {
                    date,
                    service_id: data.service_id,
                    provider_id: data.service_provider_id,
                },
            });
            setAvailableSlots(response.data.slots ?? []);
        } catch (error) {
            setAvailableSlots([]);
            setSlotsError('No pudimos cargar los horarios. Intenta con otra fecha.');
        } finally {
            setLoadingSlots(false);
        }
    };

    useEffect(() => {
        if (selectedDate && data.service_id) {
            fetchSlots(selectedDate);
        }
    }, [selectedDate, data.service_id, data.service_provider_id]);

    const handleServiceSelect = (service) => {
        setSelectedService(service);
        setSelectedProvider(null);
        setAvailableSlots([]);
        setSelectedDate('');
        setData({
            ...data,
            service_id: service.id,
            service_provider_id: null,
            date: '',
            time: '',
            starts_at_utc: '',
        });
        setStep(2);
    };

    const handleProviderSelect = (provider) => {
        setSelectedProvider(provider);
        setData('service_provider_id', provider?.id ?? defaultProviderId);
        setStep(3);
    };

    const handleDateChange = (event) => {
        const date = event.target.value;
        setSelectedDate(date);
        setAvailableSlots([]);
        setData({
            ...data,
            date,
            time: '',
            starts_at_utc: '',
        });
    };

    const handleTimeSelect = (slot) => {
        setData({
            ...data,
            time: slot.time,
            starts_at_utc: slot.starts_at_utc,
        });
        setStep(4);
    };

    const handleSubmit = (event) => {
        event.preventDefault();
        post(bookingBasePath);
    };

    const goBack = () => {
        setStep((current) => Math.max(1, current - 1));
    };

    return (
        <PublicLayout>
            <Head title={`Reservar en ${tenant.name}`} />

            <BookingShell tenant={tenant} step={step}>
                {step === 1 ? (
                    <div className="flex flex-col gap-5" data-testid="booking-step-service">
                        <StepHeader
                            title="Selecciona un servicio"
                            description="Elige lo que necesitas reservar para ver profesionales y horarios disponibles."
                        />

                        {services.length === 0 ? (
                            <Alert>
                                <AlertTitle>No hay servicios disponibles</AlertTitle>
                                <AlertDescription>
                                    Este negocio aun no tiene servicios activos para reservas.
                                </AlertDescription>
                            </Alert>
                        ) : (
                            <div className="grid gap-3">
                                {services.map((service) => (
                                    <SelectionCard
                                        key={service.id}
                                        testId="booking-service-option"
                                        descriptionId={`service-${service.id}-description`}
                                        selected={selectedService?.id === service.id}
                                        onClick={() => handleServiceSelect(service)}
                                    >
                                        <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                            <div className="flex flex-col gap-1">
                                                <span className="font-medium">{service.name}</span>
                                                {service.description ? (
                                                    <span
                                                        id={`service-${service.id}-description`}
                                                        className="text-sm text-muted-foreground"
                                                    >
                                                        {service.description}
                                                    </span>
                                                ) : null}
                                                <span className="flex items-center gap-2 text-sm text-muted-foreground">
                                                    <Clock data-icon="inline-start" />
                                                    {service.duration_formatted}
                                                </span>
                                            </div>
                                            <Badge variant="secondary">{service.price}</Badge>
                                        </div>
                                    </SelectionCard>
                                ))}
                            </div>
                        )}
                    </div>
                ) : null}

                {step === 2 ? (
                    <div className="flex flex-col gap-5" data-testid="booking-step-provider">
                        <StepHeader
                            title="Elige profesional"
                            description="Puedes escoger una persona o dejar que el negocio asigne la disponibilidad mas cercana."
                            onBack={goBack}
                        />

                        {providers.length === 0 ? (
                            <Alert variant="warning">
                                <AlertTitle>No hay profesionales activos</AlertTitle>
                                <AlertDescription>
                                    Este servicio todavia no tiene profesionales disponibles para reservar.
                                </AlertDescription>
                            </Alert>
                        ) : (
                            <div className="grid gap-3">
                                <SelectionCard
                                    testId="booking-provider-any"
                                    selected={selectedProvider === null}
                                    onClick={() => handleProviderSelect(null)}
                                >
                                    <div className="flex flex-col gap-1">
                                        <span className="font-medium">Sin preferencia</span>
                                        <span className="text-sm text-muted-foreground">
                                            Usaremos el primer profesional disponible.
                                        </span>
                                    </div>
                                </SelectionCard>

                                {providers.map((provider) => (
                                    <SelectionCard
                                        key={provider.id}
                                        testId="booking-provider-option"
                                        selected={selectedProvider?.id === provider.id}
                                        onClick={() => handleProviderSelect(provider)}
                                    >
                                        <span className="font-medium">{provider.name}</span>
                                    </SelectionCard>
                                ))}
                            </div>
                        )}
                    </div>
                ) : null}

                {step === 3 ? (
                    <div className="flex flex-col gap-5" data-testid="booking-step-time">
                        <StepHeader
                            title="Escoge fecha y hora"
                            description="Los horarios se cargan segun el servicio y profesional seleccionado."
                            onBack={goBack}
                        />

                        <FieldGroup>
                            <Field data-invalid={Boolean(errors.date)}>
                                <FieldLabel htmlFor="booking-date">Fecha</FieldLabel>
                                <Input
                                    id="booking-date"
                                    type="date"
                                    min={today}
                                    value={selectedDate}
                                    onChange={handleDateChange}
                                    aria-invalid={Boolean(errors.date)}
                                    data-testid="booking-date-input"
                                />
                                {errors.date ? (
                                    <FieldDescription className="text-destructive" aria-live="polite">
                                        {errors.date}
                                    </FieldDescription>
                                ) : null}
                            </Field>

                            {selectedDate ? (
                                <Field>
                                    <FieldLabel>Hora disponible</FieldLabel>
                                    {loadingSlots ? (
                                        <Alert aria-live="polite">
                                            <AlertDescription className="flex items-center gap-2">
                                                <Spinner />
                                                Cargando horarios disponibles...
                                            </AlertDescription>
                                        </Alert>
                                    ) : slotsError ? (
                                        <Alert variant="destructive" aria-live="polite">
                                            <AlertTitle>Horarios no disponibles</AlertTitle>
                                            <AlertDescription>{slotsError}</AlertDescription>
                                        </Alert>
                                    ) : availableSlots.length === 0 ? (
                                        <Alert aria-live="polite">
                                            <AlertTitle>Sin horarios para esta fecha</AlertTitle>
                                            <AlertDescription>
                                                Prueba con otro dia o cambia la preferencia de profesional.
                                            </AlertDescription>
                                        </Alert>
                                    ) : (
                                        <div className="grid grid-cols-2 gap-2 sm:grid-cols-3">
                                            {availableSlots.map((slot) => (
                                                <Button
                                                    key={slot.time}
                                                    type="button"
                                                    variant={data.time === slot.time ? 'default' : 'outline'}
                                                    onClick={() => handleTimeSelect(slot)}
                                                    data-testid="booking-slot-option"
                                                >
                                                    {slot.display}
                                                </Button>
                                            ))}
                                        </div>
                                    )}
                                    {errors.time ? (
                                        <FieldDescription className="text-destructive" aria-live="polite">
                                            {errors.time}
                                        </FieldDescription>
                                    ) : null}
                                </Field>
                            ) : null}
                        </FieldGroup>
                    </div>
                ) : null}

                {step === 4 ? (
                    <form
                        onSubmit={handleSubmit}
                        className="flex flex-col gap-5"
                        data-testid="booking-step-contact"
                    >
                        <StepHeader
                            title="Completa tu reserva"
                            description="Deja tus datos para que el negocio pueda confirmar tu cita."
                            onBack={goBack}
                        />

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Resumen</CardTitle>
                                <CardDescription>Revisa los detalles antes de confirmar.</CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-3 text-sm">
                                <div className="flex items-center justify-between gap-4">
                                    <span className="text-muted-foreground">Servicio</span>
                                    <span className="font-medium">{selectedService?.name}</span>
                                </div>
                                <div className="flex items-center justify-between gap-4">
                                    <span className="text-muted-foreground">Profesional</span>
                                    <span className="font-medium">{selectedProvider?.name || 'Sin preferencia'}</span>
                                </div>
                                <div className="flex items-center justify-between gap-4">
                                    <span className="text-muted-foreground">Fecha</span>
                                    <span className="font-medium">{selectedDate}</span>
                                </div>
                                <div className="flex items-center justify-between gap-4">
                                    <span className="text-muted-foreground">Hora</span>
                                    <span className="font-medium">{data.time}</span>
                                </div>
                                <Separator />
                                <div className="flex items-center justify-between gap-4">
                                    <span className="text-muted-foreground">Precio</span>
                                    <Badge>{selectedService?.price}</Badge>
                                </div>
                            </CardContent>
                        </Card>

                        <FieldGroup>
                            <Field data-invalid={Boolean(errors.customer_name)}>
                                <FieldLabel htmlFor="customer-name">Tu nombre *</FieldLabel>
                                <div className="relative">
                                    <UserRound className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground" aria-hidden="true" />
                                    <Input
                                        id="customer-name"
                                        value={data.customer_name}
                                        onChange={(event) => setData('customer_name', event.target.value)}
                                        className="pl-10"
                                        placeholder="Juan Perez"
                                        required
                                        aria-invalid={Boolean(errors.customer_name)}
                                        data-testid="booking-customer-name"
                                    />
                                </div>
                                {errors.customer_name ? (
                                    <FieldDescription className="text-destructive" aria-live="polite">
                                        {errors.customer_name}
                                    </FieldDescription>
                                ) : null}
                            </Field>

                            <Field data-invalid={Boolean(errors.customer_email)}>
                                <FieldLabel htmlFor="customer-email">Correo electronico *</FieldLabel>
                                <div className="relative">
                                    <Mail className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground" aria-hidden="true" />
                                    <Input
                                        id="customer-email"
                                        type="email"
                                        value={data.customer_email}
                                        onChange={(event) => setData('customer_email', event.target.value)}
                                        className="pl-10"
                                        placeholder="juan@ejemplo.com"
                                        required
                                        aria-invalid={Boolean(errors.customer_email)}
                                        data-testid="booking-customer-email"
                                    />
                                </div>
                                {errors.customer_email ? (
                                    <FieldDescription className="text-destructive" aria-live="polite">
                                        {errors.customer_email}
                                    </FieldDescription>
                                ) : null}
                            </Field>

                            <Field data-invalid={Boolean(errors.customer_phone)}>
                                <FieldLabel htmlFor="customer-phone">Telefono *</FieldLabel>
                                <div className="relative">
                                    <Phone className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground" aria-hidden="true" />
                                    <Input
                                        id="customer-phone"
                                        type="tel"
                                        value={data.customer_phone}
                                        onChange={(event) => setData('customer_phone', event.target.value)}
                                        className="pl-10"
                                        placeholder="9999-9999"
                                        required
                                        aria-invalid={Boolean(errors.customer_phone)}
                                        data-testid="booking-customer-phone"
                                    />
                                </div>
                                {errors.customer_phone ? (
                                    <FieldDescription className="text-destructive" aria-live="polite">
                                        {errors.customer_phone}
                                    </FieldDescription>
                                ) : null}
                            </Field>

                            <Field>
                                <FieldLabel htmlFor="customer-notes">Notas opcionales</FieldLabel>
                                <Textarea
                                    id="customer-notes"
                                    value={data.customer_notes}
                                    onChange={(event) => setData('customer_notes', event.target.value)}
                                    rows={3}
                                    placeholder="Algun comentario especial..."
                                    data-testid="booking-customer-notes"
                                />
                            </Field>
                        </FieldGroup>

                        {errors.time ? (
                            <Alert variant="destructive" aria-live="polite">
                                <AlertTitle>No se pudo reservar ese horario</AlertTitle>
                                <AlertDescription>{errors.time}</AlertDescription>
                            </Alert>
                        ) : null}

                        <Button
                            type="submit"
                            disabled={processing}
                            size="lg"
                            data-testid="booking-submit"
                        >
                            {processing ? <Spinner /> : <CalendarDays data-icon="inline-start" />}
                            {processing ? 'Reservando...' : 'Confirmar reserva'}
                        </Button>
                    </form>
                ) : null}
            </BookingShell>
        </PublicLayout>
    );
}
