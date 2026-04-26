import { Head, Link } from '@inertiajs/react';
import PublicLayout from '@/Layouts/PublicLayout';
import SupportDiagnosticCard from '@/Components/SupportDiagnosticCard';
import { CalendarClock, CheckCircle2, Clock3, Sparkles } from 'lucide-react';

export default function BookingSuccess({ tenant, appointment, diagnostic = null }) {
    const micrositeHref = route('tenant.microsite.show', { tenant: tenant.slug });
    const bookingHref = route('public.booking.index', { tenant: tenant.slug });

    return (
        <PublicLayout>
            <Head title={`Reserva confirmada - ${tenant.name}`} />

            <div className="min-h-screen bg-[radial-gradient(circle_at_top_left,rgba(16,185,129,0.12),transparent_26%),linear-gradient(180deg,#f8fafc_0%,#ffffff_52%,#f8fafc_100%)] px-4 py-14">
                <div className="mx-auto max-w-3xl">
                    <section
                        className="overflow-hidden rounded-[2.25rem] border border-slate-200 bg-white shadow-xl shadow-slate-950/5"
                        data-testid="booking-success"
                    >
                        <div className="border-b border-slate-200 bg-slate-950 px-8 py-8 text-white">
                            <div className="inline-flex h-16 w-16 items-center justify-center rounded-full bg-emerald-500/20 text-emerald-300">
                                <CheckCircle2 className="h-8 w-8" />
                            </div>
                            <p className="mt-6 text-[11px] font-semibold uppercase tracking-[0.24em] text-emerald-200">
                                Reserva confirmada
                            </p>
                            <h1 className="mt-3 text-4xl font-black tracking-tight">
                                Tu cita ya quedo apartada
                            </h1>
                            <p className="mt-4 max-w-2xl text-base leading-8 text-slate-300">
                                El negocio ya recibio tu solicitud. Esta pantalla sirve como confirmacion clara y profesional para el cliente final.
                            </p>
                        </div>

                        <div className="space-y-8 px-8 py-8">
                            {appointment ? (
                                <div className="rounded-[1.75rem] border border-slate-200 bg-slate-50 p-6">
                                    <p className="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">
                                        Detalles de la cita
                                    </p>

                                    <div className="mt-5 grid gap-4 md:grid-cols-2">
                                        <div className="rounded-[1.25rem] bg-white p-4">
                                            <p className="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Servicio</p>
                                            <p className="mt-2 text-lg font-black text-slate-950">{appointment.service_name}</p>
                                        </div>
                                        <div className="rounded-[1.25rem] bg-white p-4">
                                            <p className="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Atiende</p>
                                            <p className="mt-2 text-lg font-black text-slate-950">{appointment.provider_name}</p>
                                        </div>
                                        <div className="rounded-[1.25rem] bg-white p-4">
                                            <p className="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Fecha</p>
                                            <p className="mt-2 inline-flex items-center gap-2 text-lg font-black text-slate-950">
                                                <CalendarClock className="h-4 w-4 text-slate-500" />
                                                {appointment.scheduled_at}
                                            </p>
                                        </div>
                                        <div className="rounded-[1.25rem] bg-white p-4">
                                            <p className="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Hora</p>
                                            <p className="mt-2 inline-flex items-center gap-2 text-lg font-black text-slate-950">
                                                <Clock3 className="h-4 w-4 text-slate-500" />
                                                {appointment.time}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            ) : (
                                <div className="rounded-[1.75rem] border border-slate-200 bg-slate-50 p-6 text-sm leading-7 text-slate-600">
                                    Recibimos tu reserva y el negocio podra confirmarla directamente contigo en breve.
                                </div>
                            )}

                            <div className="rounded-[1.75rem] border border-slate-200 bg-white p-6 text-sm text-slate-700">
                                <div className="flex items-center gap-3">
                                    <Sparkles className="h-4 w-4 text-slate-900" />
                                    Esta confirmacion forma parte de una experiencia premium para negocios que quieren verse serios desde el primer contacto.
                                </div>
                            </div>

                            <SupportDiagnosticCard diagnostic={diagnostic} />

                            <div className="flex flex-col gap-3 sm:flex-row">
                                <Link
                                    href={micrositeHref}
                                    className="inline-flex flex-1 items-center justify-center rounded-full bg-slate-950 px-6 py-4 text-sm font-semibold uppercase tracking-[0.16em] text-white transition hover:bg-slate-800"
                                >
                                    Volver al negocio
                                </Link>
                                <Link
                                    href={bookingHref}
                                    className="inline-flex flex-1 items-center justify-center rounded-full border border-slate-200 bg-white px-6 py-4 text-sm font-semibold uppercase tracking-[0.16em] text-slate-950 transition hover:border-slate-300 hover:bg-slate-50"
                                >
                                    Hacer otra reserva
                                </Link>
                            </div>

                            <p className="text-center text-xs uppercase tracking-[0.18em] text-slate-400">
                                Reserva procesada en {tenant.name}
                            </p>
                        </div>
                    </section>
                </div>
            </div>
        </PublicLayout>
    );
}
