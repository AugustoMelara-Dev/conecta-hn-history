import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import axios from 'axios';
import PublicLayout from '@/Layouts/PublicLayout';
import SupportDiagnosticCard from '@/Components/SupportDiagnosticCard';
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
    'Elige quien atiende',
    'Escoge fecha y hora',
    'Confirma tus datos',
];

function StepRail({ step }) {
    return (
        <div className="space-y-4" data-testid="booking-step-rail">
            {STEP_LABELS.map((label, index) => {
                const stepNumber = index + 1;
                const isActive = stepNumber === step;
                const isDone = stepNumber < step;

                return (
                    <div
                        key={label}
                        className={`flex items-center gap-3 rounded-2xl border px-4 py-3 ${
                            isActive
                                ? 'border-slate-950 bg-slate-950 text-white'
                                : isDone
                                    ? 'border-emerald-200 bg-emerald-50 text-emerald-900'
                                    : 'border-slate-200 bg-white text-slate-500'
                        }`}
                    >
                        <div
                            className={`flex h-10 w-10 items-center justify-center rounded-full text-sm font-bold ${
                                isActive
                                    ? 'bg-white text-slate-950'
                                    : isDone
                                        ? 'bg-emerald-600 text-white'
                                        : 'bg-slate-100 text-slate-600'
                            }`}
                        >
                            {isDone ? <CheckCircle2 className="h-4 w-4" /> : stepNumber}
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
        <aside className="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm shadow-slate-950/5" data-testid="booking-summary">
            <p className="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500">Reserva guiada</p>
            <h2 className="mt-3 text-2xl font-black tracking-tight text-slate-950">{tenant.name}</h2>
            <p className="mt-3 text-sm leading-7 text-slate-600">
                Agenda una cita con una experiencia clara, rapida y pensada para convertir visitas en clientes reales.
            </p>

            <div className="mt-6 space-y-3 rounded-[1.5rem] bg-slate-50 p-4 text-sm">
                <div className="flex justify-between gap-4"><span className="text-slate-500">Servicio</span><span className="text-right font-semibold text-slate-950">{selectedService?.name || 'Pendiente'}</span></div>
                <div className="flex justify-between gap-4"><span className="text-slate-500">Atiende</span><span className="text-right font-semibold text-slate-950">{selectedProvider?.name || 'Sin preferencia'}</span></div>
                <div className="flex justify-between gap-4"><span className="text-slate-500">Fecha</span><span className="text-right font-semibold text-slate-950">{selectedDate || 'Pendiente'}</span></div>
                <div className="flex justify-between gap-4"><span className="text-slate-500">Hora</span><span className="text-right font-semibold text-slate-950">{selectedTime || 'Pendiente'}</span></div>
                <div className="flex justify-between gap-4"><span className="text-slate-500">Precio</span><span className="text-right font-semibold text-slate-950">{selectedService?.price || 'Se define al elegir'}</span></div>
            </div>

            <div className="mt-6 space-y-3 rounded-[1.5rem] border border-slate-200 bg-white p-4 text-sm text-slate-700">
                <div className="flex items-center gap-3"><ShieldCheck className="h-4 w-4 text-emerald-600" />Confirmacion clara antes de atender</div>
                <div className="flex items-center gap-3"><CalendarClock className="h-4 w-4 text-slate-900" />Agenda pensada para movil y escritorio</div>
                <div className="flex items-center gap-3"><Sparkles className="h-4 w-4 text-slate-900" />Experiencia lista para reservas rapidas</div>
            </div>
        </aside>
    );
}

export default function BookingWizard({ tenant, services, providers, preselectedServiceId = null }) {
    const { flash = {} } = usePage().props;
    const [step, setStep] = useState(1);
    const [selectedService, setSelectedService] = useState(null);
    const [selectedProvider, setSelectedProvider] = useState(null);
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

    const handleServiceSelect = (service) => {
        setSelectedService(service);
        setSelectedProvider(null);
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
        post(route('public.booking.store', { tenant: tenant.slug }));
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

            <div className="min-h-screen bg-[radial-gradient(circle_at_top_left,rgba(15,23,42,0.06),transparent_32%),linear-gradient(180deg,#f8fafc_0%,#ffffff_52%,#f8fafc_100%)] py-10">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div className="mb-8 flex flex-wrap items-center justify-between gap-4">
                        <div>
                            <p className="text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-500">Reserva publica</p>
                            <h1 className="mt-3 text-4xl font-black tracking-tight text-slate-950">Agenda una cita con {tenant.name}</h1>
                            <p className="mt-3 max-w-2xl text-base leading-8 text-slate-600">
                                Esta experiencia esta pensada para que un negocio de servicios convierta visitas en reservas con claridad y menos friccion.
                            </p>
                        </div>

                        <Link
                            href={micrositeHref}
                            className="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-5 py-3 text-xs font-semibold uppercase tracking-[0.16em] text-slate-900 transition hover:border-slate-300 hover:bg-slate-50"
                        >
                            <ChevronLeft className="h-4 w-4" />
                            Volver al micrositio
                        </Link>
                    </div>

                    <div className="grid gap-8 xl:grid-cols-[320px_minmax(0,1fr)]" data-testid="booking-wizard">
                        <div className="space-y-6">
                            <StepRail step={step} />
                            <SummaryCard
                                tenant={tenant}
                                selectedService={selectedService}
                                selectedProvider={selectedProvider}
                                selectedDate={selectedDate}
                                selectedTime={data.time}
                            />
                        </div>

                        <div className="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm shadow-slate-950/5 md:p-8">
                            <SupportDiagnosticCard diagnostic={bookingDiagnostic} className="mb-6" />

                            {services.length === 0 ? (
                                <div className="rounded-[1.75rem] border border-dashed border-slate-300 bg-slate-50 px-6 py-16 text-center">
                                    <CalendarClock className="mx-auto h-12 w-12 text-slate-400" />
                                    <h2 className="mt-5 text-2xl font-black tracking-tight text-slate-950">Este negocio todavia no tiene agenda publica</h2>
                                    <p className="mt-3 text-sm leading-7 text-slate-600">Cuando publique servicios y disponibilidad, esta pagina se convertira en su canal directo de reservas.</p>
                                </div>
                            ) : null}

                            {services.length > 0 && step === 1 ? (
                                <section data-testid="booking-step-services">
                                    <p className="text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-500">Paso 1</p>
                                    <h2 className="mt-3 text-3xl font-black tracking-tight text-slate-950">Elige el servicio correcto</h2>
                                    <p className="mt-3 max-w-2xl text-sm leading-7 text-slate-600">Selecciona la atencion que necesitas. El negocio ya presenta duracion, precio y contexto para que avances con seguridad.</p>

                                    <div className="mt-8 grid gap-4 lg:grid-cols-2">
                                        {services.map((service) => (
                                            <button
                                                key={service.id}
                                                type="button"
                                                onClick={() => handleServiceSelect(service)}
                                                className="group rounded-[1.75rem] border border-slate-200 bg-white p-5 text-left transition hover:-translate-y-0.5 hover:border-slate-950 hover:shadow-lg hover:shadow-slate-950/5"
                                                data-testid={`booking-service-${service.id}`}
                                            >
                                                <div className="flex items-start justify-between gap-4">
                                                    <div>
                                                        <p className="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">Servicio</p>
                                                        <h3 className="mt-2 text-2xl font-black tracking-tight text-slate-950">{service.name}</h3>
                                                        <p className="mt-3 text-sm leading-7 text-slate-600">{service.description || 'Atencion profesional lista para agendar.'}</p>
                                                    </div>
                                                    <div className="rounded-[1.25rem] bg-slate-950 px-4 py-3 text-right text-white">
                                                        <p className="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-300">Precio</p>
                                                        <p className="mt-2 text-xl font-black">{service.price}</p>
                                                    </div>
                                                </div>
                                                <div className="mt-5 flex flex-wrap gap-3 text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">
                                                    <span className="inline-flex items-center gap-2 rounded-full bg-slate-100 px-3 py-2"><Clock3 className="h-4 w-4" />{service.duration_formatted}</span>
                                                    <span className="inline-flex items-center gap-2 rounded-full bg-slate-100 px-3 py-2"><Sparkles className="h-4 w-4" />Reserva en pocos pasos</span>
                                                </div>
                                            </button>
                                        ))}
                                    </div>
                                </section>
                            ) : null}

                            {services.length > 0 && step === 2 ? (
                                <section data-testid="booking-step-providers">
                                    <button type="button" onClick={goBack} className="inline-flex items-center gap-2 text-sm font-semibold text-slate-500 transition hover:text-slate-950">
                                        <ChevronLeft className="h-4 w-4" />
                                        Cambiar servicio
                                    </button>

                                    <p className="mt-6 text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-500">Paso 2</p>
                                    <h2 className="mt-3 text-3xl font-black tracking-tight text-slate-950">Elige quien atiende la reserva</h2>
                                    <p className="mt-3 max-w-2xl text-sm leading-7 text-slate-600">Si no tienes preferencia, el sistema puede avanzar con la primera persona disponible y mantener el flujo rapido.</p>

                                    <div className="mt-8 grid gap-4 lg:grid-cols-2">
                                        <button
                                            type="button"
                                            onClick={() => handleProviderSelect(null)}
                                            className="rounded-[1.75rem] border border-slate-200 bg-slate-50 p-5 text-left transition hover:border-slate-950 hover:bg-white"
                                            data-testid="booking-provider-any"
                                        >
                                            <p className="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">Opcion rapida</p>
                                            <h3 className="mt-2 text-2xl font-black tracking-tight text-slate-950">Sin preferencia</h3>
                                            <p className="mt-3 text-sm leading-7 text-slate-600">Avanza con la persona disponible para no detener la reserva.</p>
                                        </button>

                                        {providers.map((provider) => (
                                            <button
                                                key={provider.id}
                                                type="button"
                                                onClick={() => handleProviderSelect(provider)}
                                                className="rounded-[1.75rem] border border-slate-200 bg-white p-5 text-left transition hover:-translate-y-0.5 hover:border-slate-950 hover:shadow-lg hover:shadow-slate-950/5"
                                                data-testid={`booking-provider-${provider.id}`}
                                            >
                                                <p className="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">Especialista</p>
                                                <h3 className="mt-2 text-2xl font-black tracking-tight text-slate-950">{provider.name}</h3>
                                                <p className="mt-3 inline-flex items-center gap-2 rounded-full bg-slate-100 px-3 py-2 text-xs font-semibold uppercase tracking-[0.16em] text-slate-600">
                                                    <UserRound className="h-4 w-4" />
                                                    Disponible para agendar
                                                </p>
                                            </button>
                                        ))}
                                    </div>
                                </section>
                            ) : null}

                            {services.length > 0 && step === 3 ? (
                                <section data-testid="booking-step-slots">
                                    <button type="button" onClick={goBack} className="inline-flex items-center gap-2 text-sm font-semibold text-slate-500 transition hover:text-slate-950">
                                        <ChevronLeft className="h-4 w-4" />
                                        Cambiar atencion
                                    </button>

                                    <p className="mt-6 text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-500">Paso 3</p>
                                    <h2 className="mt-3 text-3xl font-black tracking-tight text-slate-950">Escoge fecha y horario</h2>
                                    <p className="mt-3 max-w-2xl text-sm leading-7 text-slate-600">Los horarios se calculan segun disponibilidad activa para que reserves con menos ida y vuelta.</p>

                                    <div className="mt-8 max-w-sm">
                                        <label htmlFor="booking-date" className="mb-2 block text-sm font-semibold text-slate-700">Fecha de la cita</label>
                                        <input
                                            id="booking-date"
                                            type="date"
                                            min={today}
                                            value={selectedDate}
                                            onChange={handleDateChange}
                                            className="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-slate-950 outline-none transition focus:border-slate-950"
                                            data-testid="booking-date-input"
                                        />
                                    </div>

                                    <div className="mt-8" data-testid="booking-slots">
                                        {!selectedDate ? (
                                            <div className="rounded-[1.75rem] border border-dashed border-slate-300 bg-slate-50 px-6 py-10 text-center text-sm text-slate-500">Elige una fecha para cargar los horarios disponibles.</div>
                                        ) : loadingSlots ? (
                                            <div className="rounded-[1.75rem] border border-slate-200 bg-slate-50 px-6 py-10 text-center text-sm text-slate-500">Cargando horarios disponibles...</div>
                                        ) : availableSlots.length === 0 ? (
                                            <div className="rounded-[1.75rem] border border-dashed border-amber-200 bg-amber-50 px-6 py-10 text-center text-sm text-amber-900">No hay horarios disponibles para esta fecha. Prueba con otro dia.</div>
                                        ) : (
                                            <div className="space-y-4">
                                                <p className="text-xs font-semibold text-slate-500">
                                                    Mostramos disponibilidad real: horarios ocupados aparecen bloqueados.
                                                </p>
                                                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                                                    {(slotTimeline.length > 0 ? slotTimeline : availableSlots).map((slot) => (
                                                        <button
                                                            key={`${slot.time}-${slot.available === false ? 'blocked' : 'free'}`}
                                                            type="button"
                                                            onClick={() => handleTimeSelect(slot)}
                                                            disabled={slot.available === false}
                                                            className={`rounded-2xl border px-4 py-4 text-left transition ${
                                                                slot.available === false
                                                                    ? 'cursor-not-allowed border-slate-200 bg-slate-100 text-slate-400'
                                                                    : data.time === slot.time
                                                                        ? 'border-slate-950 bg-slate-950 text-white'
                                                                        : 'border-slate-200 bg-white text-slate-950 hover:border-slate-950'
                                                            }`}
                                                            data-testid={`booking-slot-${slot.time.replace(':', '-')}`}
                                                        >
                                                            <p className="text-[11px] font-semibold uppercase tracking-[0.18em] opacity-70">
                                                                {slot.available === false ? 'Ocupado' : 'Horario'}
                                                            </p>
                                                            <p className="mt-2 text-lg font-black">{slot.display}</p>
                                                        </button>
                                                    ))}
                                                </div>
                                            </div>
                                        )}
                                    </div>
                                </section>
                            ) : null}

                            {services.length > 0 && step === 4 ? (
                                <form onSubmit={handleSubmit} className="space-y-6" data-testid="booking-step-confirm">
                                    <button type="button" onClick={goBack} className="inline-flex items-center gap-2 text-sm font-semibold text-slate-500 transition hover:text-slate-950">
                                        <ChevronLeft className="h-4 w-4" />
                                        Cambiar horario
                                    </button>

                                    <p className="mt-6 text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-500">Paso 4</p>
                                    <h2 className="mt-3 text-3xl font-black tracking-tight text-slate-950">Confirma tus datos</h2>
                                    <p className="mt-3 max-w-2xl text-sm leading-7 text-slate-600">Cierra la reserva con la informacion minima necesaria para confirmar la atencion y dar seguimiento.</p>

                                    <div className="grid gap-5 md:grid-cols-2">
                                        <div>
                                            <label htmlFor="customer_name" className="mb-2 block text-sm font-semibold text-slate-700">Nombre completo</label>
                                            <input id="customer_name" type="text" value={data.customer_name} onChange={(event) => setData('customer_name', event.target.value)} className="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-slate-950 outline-none transition focus:border-slate-950" placeholder="Juan Perez" required data-testid="booking-name-input" />
                                            {errors.customer_name ? <p className="mt-2 text-sm text-rose-600">{errors.customer_name}</p> : null}
                                        </div>

                                        <div>
                                            <label htmlFor="customer_phone" className="mb-2 block text-sm font-semibold text-slate-700">Telefono</label>
                                            <input id="customer_phone" type="tel" value={data.customer_phone} onChange={(event) => setData('customer_phone', event.target.value)} className="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-slate-950 outline-none transition focus:border-slate-950" placeholder="9999-9999" required data-testid="booking-phone-input" />
                                            {errors.customer_phone ? <p className="mt-2 text-sm text-rose-600">{errors.customer_phone}</p> : null}
                                        </div>

                                        <div className="md:col-span-2">
                                            <label htmlFor="customer_email" className="mb-2 block text-sm font-semibold text-slate-700">Correo electronico</label>
                                            <input id="customer_email" type="email" value={data.customer_email} onChange={(event) => setData('customer_email', event.target.value)} className="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-slate-950 outline-none transition focus:border-slate-950" placeholder="juan@ejemplo.com" required data-testid="booking-email-input" />
                                            {errors.customer_email ? <p className="mt-2 text-sm text-rose-600">{errors.customer_email}</p> : null}
                                        </div>

                                        <div className="md:col-span-2">
                                            <label htmlFor="customer_notes" className="mb-2 block text-sm font-semibold text-slate-700">Notas opcionales</label>
                                            <textarea id="customer_notes" value={data.customer_notes} onChange={(event) => setData('customer_notes', event.target.value)} className="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-slate-950 outline-none transition focus:border-slate-950" rows={4} placeholder="Comparte detalles utiles para atender mejor tu cita." data-testid="booking-notes-input" />
                                        </div>

                                        <div className="md:col-span-2 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                                            <label className="flex items-start gap-3 text-sm leading-7 text-slate-700">
                                                <input
                                                    type="checkbox"
                                                    checked={Boolean(data.contact_consent)}
                                                    onChange={(event) => setData('contact_consent', event.target.checked)}
                                                    className="mt-1 h-4 w-4 rounded border-slate-300 text-slate-950 focus:ring-slate-300"
                                                    data-testid="booking-consent-checkbox"
                                                />
                                                <span>
                                                    Confirmo que mis datos son reales y autorizo que este negocio me contacte por WhatsApp o correo para confirmar o reprogramar la cita.
                                                </span>
                                            </label>
                                            {errors.contact_consent ? <p className="mt-2 text-sm text-rose-600">{errors.contact_consent}</p> : null}
                                        </div>
                                    </div>

                                    {errors.time ? <div className="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{errors.time}</div> : null}
                                    {bookingFlowError ? <div className="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{bookingFlowError}</div> : null}

                                    <div className="rounded-[1.75rem] border border-slate-200 bg-slate-50 p-5">
                                        <p className="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">Resumen final</p>
                                        <div className="mt-4 grid gap-3 text-sm text-slate-700 md:grid-cols-2">
                                            <div><span className="font-semibold text-slate-950">Servicio:</span> {selectedService?.name}</div>
                                            <div><span className="font-semibold text-slate-950">Atiende:</span> {selectedProvider?.name || 'Sin preferencia'}</div>
                                            <div><span className="font-semibold text-slate-950">Fecha:</span> {selectedDate}</div>
                                            <div><span className="font-semibold text-slate-950">Hora:</span> {data.time}</div>
                                        </div>
                                    </div>

                                    <button type="submit" disabled={processing} className="inline-flex w-full items-center justify-center gap-2 rounded-full bg-slate-950 px-6 py-4 text-sm font-semibold uppercase tracking-[0.16em] text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60" data-testid="booking-submit">
                                        {processing ? 'Confirmando reserva...' : 'Confirmar reserva'}
                                    </button>
                                </form>
                            ) : null}
                        </div>
                    </div>
                </div>
            </div>
        </PublicLayout>
    );
}
