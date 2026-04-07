import { Head, Link } from '@inertiajs/react';
import TenantLayout from '@/Layouts/TenantLayout';

function ActionButton({ action, primary = false }) {
    if (!action?.href || !action?.label) {
        return null;
    }

    return (
        <Link
            href={action.href}
            target={action.external ? '_blank' : undefined}
            rel={action.external ? 'noreferrer' : undefined}
            className={`inline-flex items-center justify-center rounded-xl px-5 py-3 text-sm font-semibold transition ${
                primary
                    ? 'bg-slate-950 text-white hover:bg-slate-800'
                    : 'border border-slate-200 bg-white text-slate-900 hover:border-slate-300 hover:bg-slate-50'
            }`}
        >
            {action.label}
        </Link>
    );
}

export default function LegacyArchived({
    title = 'Superficie archivada',
    message = 'Esta vista legacy queda fuera del scope principal de lanzamiento.',
    reason = null,
    primaryAction = null,
    secondaryAction = null,
    supportLinks = [],
}) {
    return (
        <TenantLayout>
            <Head title={title} />

            <div className="min-h-screen bg-gray-50 py-10">
                <div className="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
                    <section className="overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-sm shadow-slate-950/5">
                        <div className="border-b border-slate-200 bg-slate-950 px-8 py-8 text-white">
                            <p className="text-[11px] font-semibold uppercase tracking-[0.24em] text-amber-200">
                                Workspace legacy archivado
                            </p>
                            <h1 className="mt-3 text-4xl font-black tracking-tight">{title}</h1>
                            <p className="mt-4 max-w-2xl text-base leading-8 text-slate-300">{message}</p>
                        </div>

                        <div className="space-y-6 px-8 py-8">
                            {reason && (
                                <div className="rounded-[1.5rem] border border-amber-200 bg-amber-50 px-5 py-4 text-sm leading-7 text-amber-950">
                                    {reason}
                                </div>
                            )}

                            <div className="flex flex-col gap-3 sm:flex-row">
                                <ActionButton action={primaryAction} primary />
                                <ActionButton action={secondaryAction} />
                            </div>

                            {supportLinks.length > 0 && (
                                <div className="rounded-[1.5rem] border border-slate-200 bg-slate-50 p-5">
                                    <p className="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500">
                                        Soporte legacy puntual
                                    </p>
                                    <div className="mt-4 flex flex-col gap-3">
                                        {supportLinks.map((link) => (
                                            <ActionButton key={link.href} action={link} />
                                        ))}
                                    </div>
                                </div>
                            )}
                        </div>
                    </section>
                </div>
            </div>
        </TenantLayout>
    );
}
