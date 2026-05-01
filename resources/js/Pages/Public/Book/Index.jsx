import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import axios from 'axios';
import { focusFirstInvalidField, FormRecoveryAlert } from '@/Components/Conversion/FormRecovery';
import SelectableCard from '@/Components/Conversion/SelectableCard';
import PublicLayout from '@/Layouts/PublicLayout';
import SupportDiagnosticCard from '@/Components/SupportDiagnosticCard';
import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Checkbox } from '@/Components/ui/checkbox';
import { Empty, EmptyDescription, EmptyHeader, EmptyIcon, EmptyTitle } from '@/Components/ui/empty';
import { Field, FieldDescription, FieldGroup, FieldLabel } from '@/Components/ui/field';
import { Input } from '@/Components/ui/input';
import { Textarea } from '@/Components/ui/textarea';
import { cn } from '@/lib/utils';
import {
    CalendarClock,
    CheckCircle2,
    ChevronLeft,
    Clock3,
    ShieldCheck,
    Sparkles,
    UserRound,
} from 'lucide-react';

const STEP_LABELS = [
    'Selecciona servicio',
    'Elige quién atiende',
    'Escoge fecha y hora',
    'Confirma tus datos',
];

function StepRail({ step }) {
    return (
        <div className="flex flex-col gap-4" data-testid="booking-step-rail">
            {STEP_LABELS.map((label, index) => {
                const stepNumber = index + 1;
                const isActive = stepNumber === step;
                const isDone = stepNumber < step;

                return (
                    <div
                        key={label}
                        className={cn(
                            'flex items-center gap-3 rounded-lg border px-4 py-3',
                            isActive
                                ? 'border-primary bg-primary text-primary-foreground'
                                : isDone
                                    ? 'border-primary/25 bg-primary/10 text-foreground'
                                    : 'border-border bg-card text-muted-foreground'
                        )}
                    >
                        <div
                            className={cn(
                                'flex h-10 w-10 items-center justify-center rounded-full text-sm font-bold',
                                isActive
                                    ? 'bg-primary-foreground text-primary'
                                    : isDone
                                        ? 'bg-primary text-primary-foreground'
                                        : 'bg-muted text-muted-foreground'
                            )}
                        >
                            {isDone ? <CheckCircle2 className="h-4 w-4" aria-hidden="true" /> : stepNumber}
                        </div>
                        <div>
                            <p className="text-[11px] font-semibold uppercase tracking-[0.18em] opacity-70">Paso {stepNumber}</p>
                            <p className="text-sm font-semibold">{label}</p>
                        </div>
                    </div>
                );
            })}
        </div>
    );
}

function SummaryCard({ tenant, selectedService, selectedProvider, selectedDate, selectedTime }) {
    return (
        <Card className="rounded-lg shadow-sm" data-testid="booking-summary">
            <CardHeader>
                <p className="text-[11px] font-semibold uppercase tracking-[0.2em] text-muted-foreground">Reserva guiada</p>
                <CardTitle className="text-2xl font-black tracking-tight">{tenant.name}</CardTitle>
                <CardDescription className="leading-7">
                    Agenda una cita con una experiencia clara, rápida y pensada para convertir visitas en clientes reales.
                </CardDescription>
            </CardHeader>

            <CardContent className="flex flex-col gap-6">
                <div className="flex flex-col gap-3 rounded-lg bg-muted p-4 text-sm">
                    <div className="flex justify-between gap-4"><span className="text-muted-foreground">Servicio</span><span className="text-right font-semibold text-foreground">{selectedService?.name || 'Pendiente'}</span></div>
                    <div className="flex justify-between gap-4"><span className="text-muted-foreground">Atiende</span><span className="text-right font-semibold text-foreground">{selectedProvider?.name || 'Sin preferencia'}</span></div>
                    <div className="flex justify-between gap-4"><span className="text-muted-foreground">Fecha</span><span className="text-right font-semibold text-foreground">{selectedDate || 'Pendiente'}</span></div>
                    <div className="flex justify-between gap-4"><span className="text-muted-foreground">Hora</span><span className="text-right font-semibold text-foreground">{selectedTime || 'Pendiente'}</span></div>
                    <div className="flex justify-between gap-4"><span className="text-muted-foreground">Precio</span><span className="text-right font-semibold text-foreground">{selectedService?.price || 'Se define al elegir'}</span></div>
                </div>

                <div className="flex flex-col gap-3 rounded-lg border bg-card p-4 text-sm text-card-foreground">
                    <div className="flex items-center gap-3"><ShieldCheck className="h-4 w-4 text-primary" aria-hidden="true" />Confirmación clara antes de atender</div>
                    <div className="flex items-center gap-3"><CalendarClock className="h-4 w-4 text-primary" aria-hidden="true" />Agenda pensada para móvil y escritorio</div>
                    <div className="flex items-center gap-3"><Sparkles className="h-4 w-4 text-primary" aria-hidden="true" />Experiencia lista para reservas rápidas</div>
                </div>
            </CardContent>
        </Card>
    );
}

export default function BookingWizard({ tenant, services, providers, preselectedServiceId = null }) {
    const { flash = {} } = usePage().props;
    const [step, setStep] = useState(1);
    const [selectedService, setSelectedService] = useState(null);
    const [selectedProvider, setSelectedProvider] = useState(null);
    const [hasSelectedProvider, setHasSelectedProvider] = useState(false);
    const [selectedDate, setSelectedDate] = useState('');
    const [availableSlots, setAvailableSlots] = useState([]);
    const [slotTimeline, setSlotTimeline] = useState([]);
    const [loadingSlots, setLoadingSlots] = useState(false);
    const [slotDiagnostic, setSlotDiagnostic] = useState(null);

    const { data, setData, post, processing, errors } = useForm({
        service_id: null,
        service_provider_id: null,
        date: '',
        time: '',
        customer_name: '',
        customer_email: '',
        customer_phone: '',
        customer_notes: '',
        contact_consent: false,
    });

    useEffect(() => {
        if (!preselectedServiceId || selectedService !== null) {
            return;
        }

        const match = services.find((service) => String(service.id) === String(preselectedServiceId));

        if (!match) {
            return;
        }

        setSelectedService(match);
        setData('service_id', match.id);
        setStep(2);
    }, [preselectedServiceId, selectedService, services, setData]);

    useEffect(() => {
        if (!selectedDate || !data.service_id) {
            setAvailableSlots([]);
            setSlotTimeline([]);

            return;
        }

        let cancelled = false;

        const loadSlots = async () => {
            setLoadingSlots(true);

            try {
                const response = await axios.get(route('public.booking.slots', { tenant: tenant.slug }), {
                    params: {
                        date: selectedDate,
                        service_id: data.service_id,
                        provider_id: data.service_provider_id,
                    },
                });

                if (!cancelled) {
                    setAvailableSlots(response.data.slots || []);
                    setSlotTimeline(response.data.timeline || []);
                    setSlotDiagnostic(null);
                }
            } catch (error) {
                if (!cancelled) {
                    console.error('Error fetching slots:', error);
                    setAvailableSlots([]);
                    setSlotTimeline([]);
                    setSlotDiagnostic(error.response?.data?.diagnostic || {
                        surface: 'booking',
                        status: 'No pudimos cargar horarios',
                        probable_cause: error.response?.data?.message || 'La disponibilidad no respondio o la consulta fue limitada.',
                        recommended_action: 'Cambia la fecha o espera un momento antes de reintentar. Si persiste, comparte esta pantalla con soporte.',
                        trace_id: error.response?.headers?.['x-request-id'] || null,
                    });
                }
            } finally {
                if (!cancelled) {
                    setLoadingSlots(false);
                }
            }
        };

        loadSlots();

        return () => {
            cancelled = true;
        };
    }, [data.service_id, data.service_provider_id, selectedDate, tenant.slug]);

    const today = useMemo(() => new Date().toISOString().split('T')[0], []);
    const micrositeHref = route('tenant.microsite.show', { tenant: tenant.slug });
    const bookingFlowError = errors.service_id || errors.service_provider_id;
    const bookingDiagnostic = flash.diagnostic || slotDiagnostic || (bookingFlowError ? {
        surface: 'booking',
        status: 'Reserva incompleta',
        probable_cause: bookingFlowError,
        recommended_action: 'Revisa servicio, persona que atiende, fecha y hora antes de confirmar.',
        trace_id: null,
    } : null);
    const bookingRecoveryMessages = [
        errors.customer_name ? 'Agrega el nombre completo para confirmar la reserva.' : null,
        errors.customer_phone ? 'Agrega un telefono valido para que el negocio pueda confirmar.' : null,
        errors.customer_email ? 'Revisa el correo electronico.' : null,
        errors.contact_consent ? 'Confirma la autorizacion de contacto.' : null,
        errors.time ? 'Selecciona un horario disponible.' : null,
        bookingFlowError ? 'Revisa servicio, persona que atiende, fecha y hora.' : null,
    ];
    const focusBookingFirstError = () => focusFirstInvalidField(document.querySelector('[data-testid="booking-step-confirm"]'));

    const handleServiceSelect = (service) => {
        setSelectedService(service);
        setSelectedProvider(null);
        setHasSelectedProvider(false);
        setSelectedDate('');
        setAvailableSlots([]);
        setSlotTimeline([]);
        setData({
            ...data,
            service_id: service.id,
            service_provider_id: null,
            date: '',
            time: '',
        });
        setStep(2);
    };

    const handleProviderSelect = (provider) => {
        setSelectedProvider(provider);
        setHasSelectedProvider(true);
        setSelectedDate('');
        setAvailableSlots([]);
        setSlotTimeline([]);
        setData({
            ...data,
            service_provider_id: provider?.id ?? null,
            date: '',
            time: '',
        });
        setStep(3);
    };

    const handleDateChange = (event) => {
        const nextDate = event.target.value;
        setSelectedDate(nextDate);
        setData({
            ...data,
            date: nextDate,
            time: '',
        });
    };

    const handleTimeSelect = (slot) => {
        setData('time', slot.time);
        setStep(4);
    };

    const handleSubmit = (event) => {
        event.preventDefault();
        post(route('public.booking.store', { tenant: tenant.slug }), {
            onError: () => window.setTimeout(focusBookingFirstError, 0),
        });
    };

    const goBack = () => {
        if (step === 4) {
            setStep(3);

            return;
        }

        if (step === 3) {
            setData('time', '');
        }

        setStep((current) => Math.max(1, current - 1));
    };

    return (
        <PublicLayout>
            <Head title={`Reservar en ${tenant.name}`} />

            <div className="min-h-screen bg-background py-10">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div className="mb-8 flex flex-wrap items-center justify-between gap-4">
                        <div>
                            <p className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">Reserva pública</p>
                            <h1 className="mt-3 text-4xl font-black tracking-tight text-foreground">Agenda una cita con {tenant.name}</h1>
                            <p className="mt-3 max-w-2xl text-base leading-8 text-muted-foreground">
                                Esta experiencia está pensada para que un negocio de servicios convierta visitas en reservas con claridad y menos fricción.
                            </p>
                        </div>

                        <Button asChild variant="outline" className="rounded-full uppercase tracking-[0.16em]">
                            <Link href={micrositeHref}>
                                <ChevronLeft className="h-4 w-4" aria-hidden="true" />
                                Volver al micrositio
                            </Link>
                        </Button>
                    </div>

                    <div className="grid gap-8 xl:grid-cols-[320px_minmax(0,1fr)]" data-testid="booking-wizard">
                        <div className="flex flex-col gap-6">
                            <StepRail step={step} />
                            <SummaryCard
                                tenant={tenant}
                                selectedService={selectedService}
                                selectedProvider={selectedProvider}
                                selectedDate={selectedDate}
                                selectedTime={data.time}
                            />
                        </div>

                        <Card className="rounded-lg shadow-sm">
                            <CardContent className="p-6 md:p-8">
                            <SupportDiagnosticCard diagnostic={bookingDiagnostic} className="mb-6" />

                            {services.length === 0 ? (
                                <Empty className="border border-dashed bg-muted/50">
                                    <EmptyHeader>
                                        <EmptyIcon icon={CalendarClock} />
                                        <EmptyTitle>Este negocio todavía no tiene agenda pública</EmptyTitle>
                                        <EmptyDescription>
                                            Cuando publique servicios y disponibilidad, esta página se convertirá en su canal directo de reservas.
                                        </EmptyDescription>
                                    </EmptyHeader>
                                </Empty>
                            ) : null}

                            {services.length > 0 && step === 1 ? (
                                <section data-testid="booking-step-services">
                                    <p className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">Paso 1</p>
                                    <h2 className="mt-3 text-3xl font-black tracking-tight text-foreground">Elige el servicio correcto</h2>
                                    <p className="mt-3 max-w-2xl text-sm leading-7 text-muted-foreground">Selecciona la atención que necesitas. El negocio ya presenta duración, precio y contexto para que avances con seguridad.</p>

                                    <div className="mt-8 grid gap-4 lg:grid-cols-2">
                                        {services.map((service) => (
                                            <SelectableCard
                                                key={service.id}
                                                onSelect={() => handleServiceSelect(service)}
                                                selected={data.service_id === service.id}
                                                testId={`booking-service-${service.id}`}
                                            >
                                                <div className="flex items-start justify-between gap-4">
                                                    <div>
                                                        <p className="text-[11px] font-semibold uppercase tracking-[0.18em] text-muted-foreground">Servicio</p>
                                                        <h3 className="mt-2 text-2xl font-black tracking-tight text-foreground">{service.name}</h3>
                                                        <p className="mt-3 text-sm leading-7 text-muted-foreground">{service.description || 'Atención profesional lista para agendar.'}</p>
                                                    </div>
                                                    <div className="rounded-lg bg-primary px-4 py-3 text-right text-primary-foreground">
                                                        <p className="text-[11px] font-semibold uppercase tracking-[0.16em] opacity-70">Precio</p>
                                                        <p className="mt-2 text-xl font-black">{service.price}</p>
                                                    </div>
                                                </div>
                                                <div className="mt-5 flex flex-wrap gap-3 text-xs font-semibold uppercase tracking-[0.16em] text-muted-foreground">
                                                    <span className="inline-flex items-center gap-2 rounded-full bg-muted px-3 py-2"><Clock3 className="h-4 w-4" aria-hidden="true" />{service.duration_formatted}</span>
                                                    <span className="inline-flex items-center gap-2 rounded-full bg-muted px-3 py-2"><Sparkles className="h-4 w-4" aria-hidden="true" />Reserva en pocos pasos</span>
                                                </div>
                                            </SelectableCard>
                                        ))}
                                    </div>
                                </section>
                            ) : null}

                            {services.length > 0 && step === 2 ? (
                                <section data-testid="booking-step-providers">
                                    <Button type="button" variant="ghost" onClick={goBack} className="px-0 text-muted-foreground hover:text-foreground">
                                        <ChevronLeft className="h-4 w-4" aria-hidden="true" />
                                        Cambiar servicio
                                    </Button>

                                    <p className="mt-6 text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">Paso 2</p>
                                    <h2 className="mt-3 text-3xl font-black tracking-tight text-foreground">Elige quién atiende la reserva</h2>
                                    <p className="mt-3 max-w-2xl text-sm leading-7 text-muted-foreground">Si no tienes preferencia, el sistema puede avanzar con la primera persona disponible y mantener el flujo rápido.</p>

                                    <div className="mt-8 grid gap-4 lg:grid-cols-2">
                                        <SelectableCard
                                            onSelect={() => handleProviderSelect(null)}
                                            selected={hasSelectedProvider && selectedProvider === null && data.service_provider_id === null}
                                            className="bg-muted/50 hover:bg-card"
                                            testId="booking-provider-any"
                                        >
                                            <p className="text-[11px] font-semibold uppercase tracking-[0.18em] text-muted-foreground">Opción rápida</p>
                                            <h3 className="mt-2 text-2xl font-black tracking-tight text-foreground">Sin preferencia</h3>
                                            <p className="mt-3 text-sm leading-7 text-muted-foreground">Avanza con la persona disponible para no detener la reserva.</p>
                                        </SelectableCard>

                                        {providers.map((provider) => (
                                            <SelectableCard
                                                key={provider.id}
                                                onSelect={() => handleProviderSelect(provider)}
                                                selected={data.service_provider_id === provider.id}
                                                testId={`booking-provider-${provider.id}`}
                                            >
                                                <p className="text-[11px] font-semibold uppercase tracking-[0.18em] text-muted-foreground">Especialista</p>
                                                <h3 className="mt-2 text-2xl font-black tracking-tight text-foreground">{provider.name}</h3>
                                                <p className="mt-3 inline-flex items-center gap-2 rounded-full bg-muted px-3 py-2 text-xs font-semibold uppercase tracking-[0.16em] text-muted-foreground">
                                                    <UserRound className="h-4 w-4" aria-hidden="true" />
                                                    Disponible para agendar
                                                </p>
                                            </SelectableCard>
                                        ))}
                                    </div>
                                </section>
                            ) : null}

                            {services.length > 0 && step === 3 ? (
                                <section data-testid="booking-step-slots">
                                    <Button type="button" variant="ghost" onClick={goBack} className="px-0 text-muted-foreground hover:text-foreground">
                                        <ChevronLeft className="h-4 w-4" aria-hidden="true" />
                                        Cambiar atención
                                    </Button>

                                    <p className="mt-6 text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">Paso 3</p>
                                    <h2 className="mt-3 text-3xl font-black tracking-tight text-foreground">Escoge fecha y horario</h2>
                                    <p className="mt-3 max-w-2xl text-sm leading-7 text-muted-foreground">Los horarios se calculan según disponibilidad activa para que reserves con menos ida y vuelta.</p>

                                    <div className="mt-8 max-w-sm">
                                        <Field>
                                            <FieldLabel htmlFor="booking-date">Fecha de la cita</FieldLabel>
                                            <Input
                                                id="booking-date"
                                                name="date"
                                                type="date"
                                                min={today}
                                                value={selectedDate}
                                                onChange={handleDateChange}
                                                aria-describedby="booking-date-description"
                                                data-testid="booking-date-input"
                                            />
                                            <FieldDescription id="booking-date-description">
                                                Solo mostramos horarios disponibles para el servicio seleccionado.
                                            </FieldDescription>
                                        </Field>
                                    </div>

                                    <div className="mt-8" data-testid="booking-slots">
                                        {!selectedDate ? (
                                            <Empty className="border border-dashed bg-muted/50">
                                                <EmptyHeader>
                                                    <EmptyIcon icon={CalendarClock} />
                                                    <EmptyTitle>Elige una fecha</EmptyTitle>
                                                    <EmptyDescription>Selecciona el día para cargar los horarios disponibles.</EmptyDescription>
                                                </EmptyHeader>
                                            </Empty>
                                        ) : loadingSlots ? (
                                            <Empty className="border bg-muted/50" role="status" aria-live="polite">
                                                <EmptyHeader>
                                                    <EmptyIcon icon={Clock3} />
                                                    <EmptyTitle>Cargando horarios disponibles...</EmptyTitle>
                                                    <EmptyDescription>Estamos consultando la agenda del negocio.</EmptyDescription>
                                                </EmptyHeader>
                                            </Empty>
                                        ) : availableSlots.length === 0 ? (
                                            <Alert variant="warning" role="status">
                                                <CalendarClock className="h-4 w-4" aria-hidden="true" />
                                                <AlertTitle>No hay horarios disponibles</AlertTitle>
                                                <AlertDescription>Prueba con otro día o cambia la persona que atiende.</AlertDescription>
                                            </Alert>
                                        ) : (
                                            <div className="flex flex-col gap-4">
                                                <p className="text-xs font-semibold text-muted-foreground">
                                                    Mostramos disponibilidad real: horarios ocupados aparecen bloqueados.
                                                </p>
                                                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                                                    {(slotTimeline.length > 0 ? slotTimeline : availableSlots).map((slot) => (
                                                        <SelectableCard
                                                            key={`${slot.time}-${slot.available === false ? 'blocked' : 'free'}`}
                                                            onSelect={() => handleTimeSelect(slot)}
                                                            disabled={slot.available === false}
                                                            selected={data.time === slot.time}
                                                            className="px-4 py-4"
                                                            testId={`booking-slot-${slot.time.replace(':', '-')}`}
                                                        >
                                                            <p className="text-[11px] font-semibold uppercase tracking-[0.18em] opacity-70">
                                                                {slot.available === false ? 'Ocupado' : 'Horario'}
                                                            </p>
                                                            <p className="mt-2 text-lg font-black">{slot.display}</p>
                                                        </SelectableCard>
                                                    ))}
                                                </div>
                                            </div>
                                        )}
                                    </div>
                                </section>
                            ) : null}

                            {services.length > 0 && step === 4 ? (
                                <form onSubmit={handleSubmit} className="flex flex-col gap-6" data-testid="booking-step-confirm" noValidate>
                                    <Button type="button" variant="ghost" onClick={goBack} className="w-fit px-0 text-muted-foreground hover:text-foreground">
                                        <ChevronLeft className="h-4 w-4" aria-hidden="true" />
                                        Cambiar horario
                                    </Button>

                                    <div>
                                        <p className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">Paso 4</p>
                                        <h2 className="mt-3 text-3xl font-black tracking-tight text-foreground">Confirma tus datos</h2>
                                        <p className="mt-3 max-w-2xl text-sm leading-7 text-muted-foreground">Cierra la reserva con la información mínima necesaria para confirmar la atención y dar seguimiento.</p>
                                    </div>

                                    <FieldGroup>
                                        <FormRecoveryAlert
                                            messages={bookingRecoveryMessages}
                                            onFocusFirstError={focusBookingFirstError}
                                            testId="booking-recovery-alert"
                                        />

                                        <div className="grid gap-5 md:grid-cols-2">
                                            <Field data-invalid={errors.customer_name ? 'true' : undefined}>
                                                <FieldLabel htmlFor="customer_name">Nombre completo</FieldLabel>
                                                <Input
                                                    id="customer_name"
                                                    name="customer_name"
                                                    type="text"
                                                    autoComplete="name"
                                                    value={data.customer_name}
                                                    onChange={(event) => setData('customer_name', event.target.value)}
                                                    placeholder="Juan Pérez"
                                                    required
                                                    aria-invalid={Boolean(errors.customer_name)}
                                                    aria-describedby={errors.customer_name ? 'customer-name-error' : undefined}
                                                    data-testid="booking-name-input"
                                                />
                                                {errors.customer_name ? <FieldDescription id="customer-name-error" role="alert" className="text-destructive">{errors.customer_name}</FieldDescription> : null}
                                            </Field>

                                            <Field data-invalid={errors.customer_phone ? 'true' : undefined}>
                                                <FieldLabel htmlFor="customer_phone">Teléfono</FieldLabel>
                                                <Input
                                                    id="customer_phone"
                                                    name="customer_phone"
                                                    type="tel"
                                                    inputMode="tel"
                                                    autoComplete="tel"
                                                    value={data.customer_phone}
                                                    onChange={(event) => setData('customer_phone', event.target.value)}
                                                    placeholder="9999-9999"
                                                    required
                                                    aria-invalid={Boolean(errors.customer_phone)}
                                                    aria-describedby={errors.customer_phone ? 'customer-phone-error' : undefined}
                                                    data-testid="booking-phone-input"
                                                />
                                                {errors.customer_phone ? <FieldDescription id="customer-phone-error" role="alert" className="text-destructive">{errors.customer_phone}</FieldDescription> : null}
                                            </Field>

                                            <Field className="md:col-span-2" data-invalid={errors.customer_email ? 'true' : undefined}>
                                                <FieldLabel htmlFor="customer_email">Correo electrónico</FieldLabel>
                                                <Input
                                                    id="customer_email"
                                                    name="customer_email"
                                                    type="email"
                                                    autoComplete="email"
                                                    value={data.customer_email}
                                                    onChange={(event) => setData('customer_email', event.target.value)}
                                                    placeholder="juan@ejemplo.com"
                                                    required
                                                    aria-invalid={Boolean(errors.customer_email)}
                                                    aria-describedby={errors.customer_email ? 'customer-email-error' : undefined}
                                                    data-testid="booking-email-input"
                                                />
                                                {errors.customer_email ? <FieldDescription id="customer-email-error" role="alert" className="text-destructive">{errors.customer_email}</FieldDescription> : null}
                                            </Field>

                                            <Field className="md:col-span-2">
                                                <FieldLabel htmlFor="customer_notes">Notas opcionales</FieldLabel>
                                                <Textarea
                                                    id="customer_notes"
                                                    name="customer_notes"
                                                    value={data.customer_notes}
                                                    onChange={(event) => setData('customer_notes', event.target.value)}
                                                    rows={4}
                                                    placeholder="Comparte detalles útiles para atender mejor tu cita."
                                                    data-testid="booking-notes-input"
                                                />
                                            </Field>

                                            <Field className="md:col-span-2 rounded-lg border bg-muted/50 px-4 py-4" data-invalid={errors.contact_consent ? 'true' : undefined}>
                                                <label className="flex items-start gap-3 text-sm leading-7 text-foreground" htmlFor="contact_consent">
                                                    <Checkbox
                                                        id="contact_consent"
                                                        name="contact_consent"
                                                        checked={Boolean(data.contact_consent)}
                                                        onCheckedChange={(checked) => setData('contact_consent', checked === true)}
                                                        aria-invalid={Boolean(errors.contact_consent)}
                                                        aria-describedby={errors.contact_consent ? 'contact-consent-error' : undefined}
                                                        data-testid="booking-consent-checkbox"
                                                    />
                                                    <span>
                                                        Confirmo que mis datos son reales y autorizo que este negocio me contacte por WhatsApp o correo para confirmar o reprogramar la cita.
                                                    </span>
                                                </label>
                                                {errors.contact_consent ? <FieldDescription id="contact-consent-error" role="alert" className="text-destructive">{errors.contact_consent}</FieldDescription> : null}
                                            </Field>
                                        </div>
                                    </FieldGroup>

                                    {errors.time ? (
                                        <Alert variant="destructive" role="alert">
                                            <AlertTitle>Selecciona un horario</AlertTitle>
                                            <AlertDescription>{errors.time}</AlertDescription>
                                        </Alert>
                                    ) : null}
                                    {bookingFlowError ? (
                                        <Alert variant="destructive" role="alert">
                                            <AlertTitle>Reserva incompleta</AlertTitle>
                                            <AlertDescription>{bookingFlowError}</AlertDescription>
                                        </Alert>
                                    ) : null}

                                    <Card className="rounded-lg bg-muted/50">
                                        <CardHeader>
                                            <p className="text-[11px] font-semibold uppercase tracking-[0.18em] text-muted-foreground">Resumen final</p>
                                        </CardHeader>
                                        <CardContent className="grid gap-3 text-sm text-muted-foreground md:grid-cols-2">
                                            <div><span className="font-semibold text-foreground">Servicio:</span> {selectedService?.name}</div>
                                            <div><span className="font-semibold text-foreground">Atiende:</span> {selectedProvider?.name || 'Sin preferencia'}</div>
                                            <div><span className="font-semibold text-foreground">Fecha:</span> {selectedDate}</div>
                                            <div><span className="font-semibold text-foreground">Hora:</span> {data.time}</div>
                                        </CardContent>
                                    </Card>

                                    <Button type="submit" disabled={processing} className="w-full rounded-full py-6 uppercase tracking-[0.16em]" data-testid="booking-submit">
                                        {processing ? 'Confirmando reserva...' : 'Confirmar reserva'}
                                    </Button>
                                </form>
                            ) : null}
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </PublicLayout>
    );
}
