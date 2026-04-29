import { Head } from '@inertiajs/react';

const TITLES = {
    403: 'Acceso Denegado',
    404: 'Pagina No Encontrada',
    419: 'Sesion Expirada',
    429: 'Demasiadas Solicitudes',
    500: 'Error Interno del Servidor',
    503: 'Servicio No Disponible',
};

const DESCRIPTIONS = {
    403: 'No tienes permisos para acceder a este recurso.',
    404: 'La pagina que buscas no existe o fue movida.',
    419: 'Tu sesion expiro. Vuelve a intentarlo.',
    429: 'Has hecho demasiadas solicitudes. Espera un momento.',
    500: 'Estamos teniendo un problema temporal. Intenta de nuevo en unos minutos.',
    503: 'El servicio esta en mantenimiento o no disponible temporalmente.',
};

export default function Error({ status = 500 }) {
    const title = TITLES[status] ?? 'Error';
    const description = DESCRIPTIONS[status] ?? 'Ocurrio un error inesperado.';
    const retry = () => window.location.replace(window.location.href);
    const goHome = () => window.location.assign(`${window.location.origin}/`);

    return (
        <>
            <Head title={`${status} | ${title}`} />

            <main className="min-h-screen bg-slate-950 text-white">
                <section className="mx-auto flex min-h-screen max-w-3xl flex-col items-center justify-center px-6 text-center">
                    <p className="text-xs font-bold uppercase tracking-[0.25em] text-slate-400">
                        Vito Business OS
                    </p>

                    <h1 className="mt-4 text-7xl font-extrabold tracking-tight md:text-8xl">
                        {status}
                    </h1>
                    <h2 className="mt-3 text-2xl font-bold md:text-3xl">{title}</h2>
                    <p className="mt-4 max-w-xl text-slate-300">{description}</p>

                    <div className="mt-10 flex flex-col gap-3 sm:flex-row">
                        <button
                            type="button"
                            onClick={retry}
                            className="rounded-xl bg-white px-6 py-3 font-bold text-slate-950 transition hover:opacity-90"
                        >
                            Reintentar
                        </button>
                        <button
                            type="button"
                            onClick={goHome}
                            className="rounded-xl border border-white/30 px-6 py-3 font-bold text-white transition hover:bg-white/10"
                        >
                            Volver al inicio
                        </button>
                    </div>
                </section>
            </main>
        </>
    );
}
